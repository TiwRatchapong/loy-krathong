<?php
declare(strict_types=1);

require_once __DIR__ . '/reports.php';

const ADMIN_IDLE_SECONDS = 3600;   // ไม่ได้ใช้งานเกิน 1 ชั่วโมง ให้ล็อกเอาต์อัตโนมัติ
const ADMIN_PER_PAGE = 25;
const ADMIN_NAV = [
    'dashboard' => ['แดชบอร์ด', '◧'],
    'krathongs' => ['กระทงทั้งหมด', '❖'],
    'reports' => ['รายงานกระทง', '⚑'],
    'log' => ['บันทึกกิจกรรม', '☰'],
    'system' => ['ระบบและความปลอดภัย', '⚙'],
];
const ADMIN_ACTION_LABELS = [
    'login_ok' => 'เข้าสู่ระบบ', 'login_fail' => 'เข้าสู่ระบบไม่สำเร็จ', 'logout' => 'ออกจากระบบ',
    'hide' => 'ซ่อนกระทง', 'show' => 'แสดงกระทง', 'delete' => 'ลบกระทง', 'dismiss' => 'ปัดตกรายงาน', 'clear_rate' => 'ล้างข้อมูล rate limit',
];

function adminPasswordHash(): string
{
    $env = (string) getenv('KRATHONG_ADMIN_HASH');
    if ($env !== '') {
        return $env;
    }
    $file = __DIR__ . '/../config/admin.php';
    if (is_file($file)) {
        $config = require $file;
        return is_array($config) ? (string) ($config['password_hash'] ?? '') : '';
    }
    return '';
}

function adminLog(PDO $pdo, string $action, string $target = '', string $detail = ''): void
{
    try {
        $pdo->prepare('INSERT INTO admin_log (action, target, detail, ip_hash) VALUES (:a, :t, :d, :ip)')
            ->execute([':a' => $action, ':t' => $target, ':d' => $detail, ':ip' => substr(hash('sha256', 'krathong-rl|' . clientIp()), 0, 10)]);
    } catch (Throwable $exception) {
        error_log('admin log: ' . $exception->getMessage());
    }
}

/** แปลงเวลา UTC ในฐานข้อมูลเป็นเวลาไทย */
function adminTime(?string $utc, string $format = 'd/m/Y H:i'): string
{
    if (!$utc) {
        return '—';
    }
    try {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Bangkok'))->format($format);
    } catch (Throwable $exception) {
        return $utc;
    }
}

/** @return array{q:string,status:string,type:string,sort:string} */
function adminFilters(array $src): array
{
    return [
        'q' => mb_substr(trim((string) ($src['q'] ?? '')), 0, 60),
        'status' => in_array($src['status'] ?? '', ['visible', 'hidden', 'reported'], true) ? (string) $src['status'] : '',
        'type' => in_array($src['type'] ?? '', ['preset', 'custom'], true) ? (string) $src['type'] : '',
        'sort' => ($src['sort'] ?? '') === 'old' ? 'old' : 'new',
    ];
}

function adminUrl(array $params = []): string
{
    $query = http_build_query(array_filter($params, static fn ($v) => $v !== '' && $v !== null && $v !== 1 && $v !== 'dashboard'));
    return (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php') . ($query !== '' ? '?' . $query : '');
}

/** @return array{0:string,1:string,2:array} [SELECT, COUNT, params] */
function adminKrathongQuery(array $f): array
{
    $where = [];
    $params = [];
    if ($f['q'] !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $where[] = "(k.nickname LIKE :q1 ESCAPE '\\' OR k.prayer LIKE :q2 ESCAPE '\\')";
        $params[':q1'] = $like;
        $params[':q2'] = $like;
    }
    if ($f['status'] !== '') {
        if ($f['status'] === 'reported') {
            $where[] = "k.id IN (SELECT krathong_id FROM krathong_reports WHERE status = 'open')";
        } else {
            $where[] = 'k.is_hidden = ' . ($f['status'] === 'hidden' ? '1' : '0');
        }
    }
    if ($f['type'] !== '') {
        $where[] = 'k.prayer_type = :pt';
        $params[':pt'] = $f['type'];
    }
    $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $select = 'SELECT k.id, k.nickname, k.prayer, k.prayer_type, k.created_at, k.is_hidden, k.flower_set_id,
                      (SELECT COUNT(*) FROM krathong_reports r WHERE r.krathong_id = k.id AND r.status = \'open\') AS report_open,
                      t.svg_key AS krathong_key, t.name AS krathong_name, s.name AS set_name
               FROM krathongs k
               LEFT JOIN krathong_types t ON t.id = k.krathong_type_id
               LEFT JOIN flower_sets s ON s.id = k.flower_set_id' . $w
        . ' ORDER BY k.id ' . ($f['sort'] === 'old' ? 'ASC' : 'DESC');
    return [$select, 'SELECT COUNT(*) FROM krathongs k' . $w, $params];
}

/** จำนวนกระทงที่มีรายงานรอตรวจสอบ (นับกระทง ไม่ใช่นับจำนวนรายงาน) */
function adminOpenReportCount(PDO $pdo): int
{
    try {
        return (int) $pdo->query("SELECT COUNT(DISTINCT krathong_id) FROM krathong_reports WHERE status = 'open'")->fetchColumn();
    } catch (Throwable $exception) {
        error_log('admin reports count: ' . $exception->getMessage());
        return 0;
    }
}

/**
 * รายการกระทงที่ถูกรายงาน (1 แถว = 1 กระทง) @param string $view open | handled | all
 *
 * @return array{0:string,1:string} [SELECT, COUNT]
 */
function adminReportsQuery(string $view): array
{
    $having = match ($view) {
        'open' => ' HAVING open_count > 0',
        'handled' => ' HAVING open_count = 0',
        default => '',
    };
    $inner = "SELECT k.id, k.nickname, k.prayer, k.prayer_type, k.created_at, k.is_hidden, k.flower_set_id,
                     t.svg_key AS krathong_key, t.name AS krathong_name, s.name AS set_name,
                     COUNT(r.id) AS total, SUM(r.status = 'open') AS open_count, MAX(r.created_at) AS last_at
              FROM krathong_reports r
              JOIN krathongs k ON k.id = r.krathong_id
              LEFT JOIN krathong_types t ON t.id = k.krathong_type_id
              LEFT JOIN flower_sets s ON s.id = k.flower_set_id
              GROUP BY k.id" . $having;
    return [
        $inner . ' ORDER BY open_count DESC, last_at DESC, k.id DESC',
        'SELECT COUNT(*) FROM (' . $inner . ')',
    ];
}

/** กราฟแท่งแนวนอนแบบ CSS ล้วน @param array<string,int> $data */
function adminBars(array $data): string
{
    if (!$data) {
        return '<p class="muted">ยังไม่มีข้อมูล</p>';
    }
    $max = max($data) ?: 1;
    $out = '<ul class="bars">';
    foreach ($data as $label => $count) {
        $out .= '<li><span class="bars-label">' . e((string) $label) . '</span><span class="bars-track"><i style="width:' . round($count / $max * 100) . '%"></i></span><b>' . number_format($count) . '</b></li>';
    }
    return $out . '</ul>';
}

function adminHead(string $title): void
{
    ?><!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <script>document.documentElement.classList.add('js');</script>
    <meta name="theme-color" content="#0b1714">
    <title><?= e($title) ?> | ผู้ดูแลลอยกระทงออนไลน์</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<?php
}

function adminLoginPage(string $self, string $csrf, string $message, bool $configured, string $dbError): void
{
    adminHead('เข้าสู่ระบบ');
    ?>
<body class="login-body">
    <main class="login-card">
        <img class="login-logo" src="../assets/images/favicon.svg" alt="" width="64" height="64">
        <p class="eyebrow">ADMIN CONSOLE</p>
        <h1>ลอยกระทงออนไลน์</h1>
        <p class="muted">ระบบผู้ดูแล สำหรับจัดการกระทงและตรวจสอบเว็บไซต์</p>

        <?php if ($dbError !== ''): ?><div class="alert alert-err" role="alert"><?= e($dbError) ?></div><?php endif; ?>
        <?php if ($message !== ''): ?><div class="alert alert-err" role="alert"><?= e($message) ?></div><?php endif; ?>

        <?php if (!$configured): ?>
            <div class="alert alert-warn">
                <strong>ยังไม่ได้ตั้งรหัสผ่านผู้ดูแล</strong>
                <ol>
                    <li>สร้าง hash ด้วย <code>php -r "echo password_hash('รหัสผ่าน', PASSWORD_DEFAULT);"</code></li>
                    <li>คัดลอก <code>config/admin.sample.php</code> เป็น <code>config/admin.php</code> แล้วใส่ hash</li>
                </ol>
            </div>
        <?php else: ?>
            <form method="post" action="<?= e($self) ?>" class="login-form" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="login">
                <label for="adminPassword">รหัสผ่าน</label>
                <div class="pass-field">
                    <input type="password" id="adminPassword" name="password" required autofocus autocomplete="current-password" placeholder="••••••••">
                    <button type="button" class="pass-toggle" data-toggle-pass="adminPassword">แสดง</button>
                </div>
                <button class="btn btn-primary btn-block" type="submit">เข้าสู่ระบบ</button>
            </form>
        <?php endif; ?>
        <p class="login-foot"><a href="../index.php">← กลับหน้าเว็บไซต์</a></p>
    </main>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
<?php
}

/** @param array<string,int> $badges จำนวนที่แสดงข้างเมนู เช่น ['reports' => 3] */
function adminShellTop(string $title, string $active, string $self, ?array $flash, array $badges = []): void
{
    adminHead($title);
    ?>
<body>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <a class="side-brand" href="<?= e($self) ?>"><img src="../assets/images/favicon.svg" alt="" width="34" height="34"><span>ลอยกระทง<small>ผู้ดูแลระบบ</small></span></a>
        <nav class="side-nav" aria-label="เมนูผู้ดูแล">
            <?php foreach (ADMIN_NAV as $key => [$label, $icon]): ?>
                <a href="<?= e(adminUrl(['p' => $key])) ?>" class="<?= $key === $active ? 'active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><span aria-hidden="true"><?= $icon ?></span> <?= e($label) ?><?php if (!empty($badges[$key])): ?><span class="nav-badge" aria-label="รอตรวจสอบ <?= (int) $badges[$key] ?> รายการ"><?= (int) $badges[$key] ?></span><?php endif; ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="side-foot">
            <a href="../index.php" target="_blank" rel="noopener">เปิดหน้าเว็บไซต์ ↗</a>
            <a href="../pages/river.php" target="_blank" rel="noopener">ดูแม่น้ำ ↗</a>
        </div>
    </aside>
    <div class="scrim" id="navScrim" aria-hidden="true"></div>
    <div class="main">
        <header class="topbar">
            <button type="button" class="nav-toggle" id="navToggle" aria-label="เปิดเมนู" aria-expanded="false" aria-controls="sidebar"><span></span><span></span><span></span></button>
            <h1><?= e($title) ?></h1>
            <form method="post" action="<?= e($self) ?>">
                <input type="hidden" name="csrf" value="<?= e((string) $_SESSION['admin_csrf']) ?>">
                <button class="btn btn-ghost" type="submit" name="action" value="logout">ออกจากระบบ</button>
            </form>
        </header>
        <div class="content">
            <?php if ($flash): ?><div class="alert flash alert-<?= $flash[0] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($flash[1]) ?></div><?php endif; ?>
<?php
}

function adminShellBottom(): void
{
    ?>
        </div>
    </div>
</div>
<script src="../assets/js/admin.js"></script>
</body>
</html>
<?php
}
