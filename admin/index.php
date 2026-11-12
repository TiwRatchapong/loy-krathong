<?php
declare(strict_types=1);

/**
 * หน้าผู้ดูแล (ตัวควบคุมหลัก): ?p=dashboard | krathongs | log | system
 * ตั้งรหัสผ่านที่ config/admin.php (ดู config/admin.sample.php) หรือ environment variable KRATHONG_ADMIN_HASH
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/svg.php';
require_once __DIR__ . '/../includes/profanity.php';
require_once __DIR__ . '/../includes/admin.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
startSecureSession();

// ใช้ path เต็มของหน้านี้เสมอ (กันกรณีเปิดด้วย /admin ที่ไม่มี / ท้าย)
$self = (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
$hash = adminPasswordHash();
$pdo = null;
$dbError = '';
try {
    $pdo = getDatabase();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $dbError = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['admin_csrf'];
$csrfOk = static fn (): bool => isset($_POST['csrf']) && hash_equals($csrf, (string) $_POST['csrf']);

$loginMessage = '';
if (!empty($_SESSION['is_admin'])) {
    if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > ADMIN_IDLE_SECONDS) {
        unset($_SESSION['is_admin']);
        $loginMessage = 'หมดเวลาใช้งาน กรุณาเข้าสู่ระบบอีกครั้ง';
    } else {
        $_SESSION['admin_seen'] = time();
    }
}
$isAdmin = $hash !== '' && $pdo instanceof PDO && !empty($_SESSION['is_admin']);

// ---------- การกระทำ (POST) ----------
if ($hash !== '' && $pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login') {
        if (!$csrfOk()) {
            $loginMessage = 'เซสชันหมดอายุ กรุณาลองใหม่';
        } elseif (!rateLimitAllow($pdo, 'admin_login', 5, 600)) {
            $loginMessage = 'พยายามเข้าสู่ระบบบ่อยเกินไป กรุณารอ 10 นาที';
        } elseif (password_verify((string) ($_POST['password'] ?? ''), $hash)) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_seen'] = time();
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
            adminLog($pdo, 'login_ok');
            redirect($self);
        } else {
            adminLog($pdo, 'login_fail');
            $loginMessage = 'รหัสผ่านไม่ถูกต้อง';
        }
    } elseif ($isAdmin && $csrfOk()) {
        $flash = null;
        $row = (string) ($_POST['row'] ?? '');
        if ($row !== '') {
            [$action, $one] = array_pad(explode(':', $row, 2), 2, '');
            $ids = [(int) $one];
        } else {
            $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), static fn ($v) => $v > 0));
        }
        if ($action === 'logout') {
            adminLog($pdo, 'logout');
            $_SESSION = [];
            session_destroy();
            redirect($self);
        } elseif (in_array($action, ['hide', 'show', 'delete', 'dismiss'], true)) {
            if (!$ids) {
                $flash = ['err', 'กรุณาเลือกกระทงอย่างน้อย 1 ใบ'];
            } else {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $pdo->beginTransaction();
                try {
                    if ($action === 'delete') {
                        $pdo->prepare("DELETE FROM krathongs WHERE id IN ($in)")->execute($ids);   // รายงานของกระทงนี้ถูกลบตามอัตโนมัติ (ON DELETE CASCADE)
                    } elseif ($action === 'dismiss') {
                        // ตรวจแล้วไม่พบปัญหา: ปัดตกรายงานที่ยังรอตรวจสอบ กระทงยังแสดงตามเดิม
                        $pdo->prepare("UPDATE krathong_reports SET status = 'dismissed', handled_at = CURRENT_TIMESTAMP WHERE status = 'open' AND krathong_id IN ($in)")->execute($ids);
                    } else {
                        $pdo->prepare("UPDATE krathongs SET is_hidden = ? WHERE id IN ($in)")->execute(array_merge([$action === 'hide' ? 1 : 0], $ids));
                        if ($action === 'hide') {
                            // ซ่อนกระทงแล้ว = จัดการรายงานที่ค้างอยู่เรียบร้อย
                            $pdo->prepare("UPDATE krathong_reports SET status = 'actioned', handled_at = CURRENT_TIMESTAMP WHERE status = 'open' AND krathong_id IN ($in)")->execute($ids);
                        }
                    }
                    $pdo->commit();
                    adminLog($pdo, $action, implode(',', array_slice($ids, 0, 30)), count($ids) . ' ใบ');
                    $labels = ['hide' => 'ซ่อนกระทงแล้ว ', 'show' => 'แสดงกระทงแล้ว ', 'delete' => 'ลบกระทงแล้ว ', 'dismiss' => 'ปัดตกรายงานแล้ว '];
                    $flash = ['ok', $labels[$action] . count($ids) . ' ใบ'];
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('admin action: ' . $exception->getMessage());
                    $flash = ['err', 'ดำเนินการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'];
                }
            }
        } elseif ($action === 'clear_rate') {
            $pdo->exec('DELETE FROM rate_events');
            adminLog($pdo, 'clear_rate');
            $flash = ['ok', 'ล้างข้อมูล rate limit แล้ว'];
        }
        if ($flash) {
            $_SESSION['flash'] = $flash;
        }
        parse_str((string) ($_POST['qs'] ?? ''), $back);
        $keep = array_intersect_key($back, array_flip(['p', 'q', 'status', 'type', 'sort', 'view', 'page']));
        redirect(adminUrl(array_map('strval', $keep)));
    }
}

// ---------- ยังไม่ล็อกอิน ----------
if (!$isAdmin) {
    adminLoginPage($self, $csrf, $loginMessage, $hash !== '', $dbError);
    exit;
}

$p = (string) ($_GET['p'] ?? 'dashboard');
if (!isset(ADMIN_NAV[$p])) {
    $p = 'dashboard';
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$filters = adminFilters($_GET);
$page = max(1, (int) ($_GET['page'] ?? 1));
$view = in_array($_GET['view'] ?? '', ['handled', 'all'], true) ? (string) $_GET['view'] : 'open';   // แท็บในหน้ารายงาน
$keepQs = http_build_query(array_filter(['p' => $p, 'q' => $filters['q'], 'status' => $filters['status'], 'type' => $filters['type'], 'sort' => $filters['sort'] === 'old' ? 'old' : '', 'view' => $p === 'reports' && $view !== 'open' ? $view : '', 'page' => $page > 1 ? $page : ''], static fn ($v) => $v !== ''));
$openReports = adminOpenReportCount($pdo);

// ---------- ส่งออก CSV ----------
if ($p === 'krathongs' && isset($_GET['export'])) {
    [$sql, , $params] = adminKrathongQuery($filters);
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="krathongs-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM ให้ Excel อ่านภาษาไทยถูกต้อง
    fputcsv($out, ['id', 'ชื่อเล่น', 'คำอธิษฐาน', 'ประเภทคำอธิษฐาน', 'กระทง', 'ชุดดอกไม้', 'สถานะ', 'สร้างเมื่อ (เวลาไทย)'], ',', '"', '\\');
    $safe = static fn (string $v): string => preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;   // กัน CSV/formula injection
    while ($r = $statement->fetch()) {
        fputcsv($out, [$r['id'], $safe((string) $r['nickname']), $safe((string) $r['prayer']), $r['prayer_type'], $r['krathong_name'], $r['set_name'], $r['is_hidden'] ? 'ซ่อน' : 'แสดง', adminTime($r['created_at'], 'Y-m-d H:i:s')], ',', '"', '\\');
    }
    exit;
}

$titles = array_map(static fn ($v) => $v[0], ADMIN_NAV);
adminShellTop($titles[$p], $p, $self, $flash, ['reports' => $openReports]);

// =============================== แดชบอร์ด ===============================
if ($p === 'dashboard') {
    $s = $pdo->query("SELECT COUNT(*) AS total, SUM(is_hidden = 1) AS hidden,
            SUM(date(created_at, '+7 hours') = date('now', '+7 hours')) AS today,
            SUM(created_at >= datetime('now', '-1 day')) AS day1,
            SUM(created_at >= datetime('now', '-7 days')) AS week FROM krathongs")->fetch();
    $total = (int) $s['total'];
    $hidden = (int) $s['hidden'];
    $cards = [
        ['กระทงทั้งหมด', $total, 'ทุกใบที่ถูกสร้าง'], ['วันนี้', (int) $s['today'], 'ตามเวลาไทย'],
        ['24 ชั่วโมงล่าสุด', (int) $s['day1'], 'ย้อนหลังนับจากตอนนี้'], ['7 วันล่าสุด', (int) $s['week'], 'ย้อนหลังนับจากตอนนี้'],
        ['กำลังแสดง', $total - $hidden, 'ผู้เข้าชมมองเห็น'], ['ถูกซ่อน', $hidden, 'ผู้ดูแลซ่อนไว้'],
    ];
    $tz = new DateTimeZone('Asia/Bangkok');
    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $days[(new DateTimeImmutable('now', $tz))->modify("-$i days")->format('Y-m-d')] = 0;
    }
    foreach ($pdo->query("SELECT date(created_at, '+7 hours') AS d, COUNT(*) AS c FROM krathongs WHERE created_at >= datetime('now', '-15 days') GROUP BY d")->fetchAll() as $r) {
        if (isset($days[$r['d']])) {
            $days[$r['d']] = (int) $r['c'];
        }
    }
    $maxDay = max($days) ?: 1;
    $topTypes = $pdo->query("SELECT COALESCE(t.name, 'ไม่ระบุ') AS n, COUNT(*) AS c FROM krathongs k LEFT JOIN krathong_types t ON t.id = k.krathong_type_id GROUP BY k.krathong_type_id ORDER BY c DESC LIMIT 5")->fetchAll(PDO::FETCH_KEY_PAIR);
    $topSets = $pdo->query("SELECT COALESCE(s.name, 'ไม่ระบุ') AS n, COUNT(*) AS c FROM krathongs k LEFT JOIN flower_sets s ON s.id = k.flower_set_id GROUP BY k.flower_set_id ORDER BY c DESC LIMIT 5")->fetchAll(PDO::FETCH_KEY_PAIR);
    $topPrayers = $pdo->query("SELECT prayer, COUNT(*) AS c FROM krathongs WHERE prayer_type = 'preset' GROUP BY prayer ORDER BY c DESC LIMIT 5")->fetchAll(PDO::FETCH_KEY_PAIR);
    $split = $pdo->query('SELECT prayer_type, COUNT(*) FROM krathongs GROUP BY prayer_type')->fetchAll(PDO::FETCH_KEY_PAIR);
    $custom = (int) ($split['custom'] ?? 0);
    $preset = (int) ($split['preset'] ?? 0);
    $recent = $pdo->query('SELECT id, nickname, prayer, created_at, is_hidden FROM krathongs ORDER BY id DESC LIMIT 6')->fetchAll();
    ?>
    <?php if ($openReports > 0): ?>
        <a class="alert alert-warn alert-link" href="<?= e(adminUrl(['p' => 'reports'])) ?>"><span>⚑ มีกระทงที่ถูกรายงาน <b><?= number_format($openReports) ?></b> ใบ รอตรวจสอบ</span><span class="alert-go">ตรวจสอบ →</span></a>
    <?php endif; ?>
    <section class="cards">
        <?php foreach ($cards as [$label, $value, $hint]): ?>
            <div class="card stat"><span class="stat-label"><?= e($label) ?></span><strong><?= number_format($value) ?></strong><small><?= e($hint) ?></small></div>
        <?php endforeach; ?>
    </section>

    <section class="panel">
        <header class="panel-head"><h2>กระทงที่สร้างต่อวัน (14 วันล่าสุด)</h2><span class="muted">เวลาไทย</span></header>
        <div class="chart" role="img" aria-label="กราฟแท่งจำนวนกระทงต่อวัน 14 วัน">
            <?php foreach ($days as $date => $count): ?>
                <div class="chart-col" title="<?= e($date) ?>: <?= $count ?> ใบ">
                    <span class="chart-val"><?= $count ?></span>
                    <i style="height:<?= max(2, (int) round($count / $maxDay * 100)) ?>%"></i>
                    <span class="chart-day"><?= e((new DateTimeImmutable($date))->format('j/n')) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="grid-2">
        <section class="panel"><header class="panel-head"><h2>กระทงยอดนิยม</h2></header><?= adminBars(array_map('intval', $topTypes)) ?></section>
        <section class="panel"><header class="panel-head"><h2>ชุดดอกไม้ยอดนิยม</h2></header><?= adminBars(array_map('intval', $topSets)) ?></section>
        <section class="panel">
            <header class="panel-head"><h2>คำอธิษฐาน</h2></header>
            <?= adminBars(['เลือกจากรายการ' => $preset, 'เขียนเอง' => $custom]) ?>
            <h3 class="sub">คำอธิษฐานสำเร็จรูปที่ถูกเลือกมากสุด</h3>
            <?= adminBars(array_map('intval', array_combine(array_map(static fn ($t) => mb_strimwidth((string) $t, 0, 46, '…', 'UTF-8'), array_keys($topPrayers)), $topPrayers) ?: [])) ?>
        </section>
        <section class="panel">
            <header class="panel-head"><h2>กระทงล่าสุด</h2><a href="<?= e(adminUrl(['p' => 'krathongs'])) ?>">ดูทั้งหมด →</a></header>
            <?php if (!$recent): ?><p class="muted">ยังไม่มีกระทง</p><?php endif; ?>
            <ul class="recent">
                <?php foreach ($recent as $r): ?>
                    <li class="<?= $r['is_hidden'] ? 'is-hidden' : '' ?>"><div><strong><?= e((string) $r['nickname']) ?></strong><?= $r['is_hidden'] ? ' <span class="badge badge-off">ซ่อน</span>' : '' ?><p><?= e(mb_strimwidth((string) $r['prayer'], 0, 90, '…', 'UTF-8')) ?></p></div><time><?= e(adminTime($r['created_at'], 'd/m H:i')) ?></time></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
    <?php
}

// =============================== กระทงทั้งหมด ===============================
if ($p === 'krathongs') {
    [$sql, $countSql, $params] = adminKrathongQuery($filters);
    $count = $pdo->prepare($countSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / ADMIN_PER_PAGE));
    $page = min($page, $pages);
    $statement = $pdo->prepare($sql . ' LIMIT ' . ADMIN_PER_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_PER_PAGE));
    $statement->execute($params);
    $rows = $statement->fetchAll();
    $keysBySet = [];
    foreach (loadFlowerSets($pdo) as $set) {
        $keysBySet[$set['id']] = $set['keys'];
    }
    renderSvgSprite();
    $base = ['p' => 'krathongs', 'q' => $filters['q'], 'status' => $filters['status'], 'type' => $filters['type'], 'sort' => $filters['sort'] === 'old' ? 'old' : ''];
    ?>
    <form method="get" action="<?= e($self) ?>" class="toolbar">
        <input type="hidden" name="p" value="krathongs">
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="ค้นหาชื่อเล่นหรือคำอธิษฐาน" aria-label="ค้นหา">
        <select name="status" aria-label="สถานะ"><option value="">ทุกสถานะ</option><option value="visible"<?= $filters['status'] === 'visible' ? ' selected' : '' ?>>กำลังแสดง</option><option value="hidden"<?= $filters['status'] === 'hidden' ? ' selected' : '' ?>>ถูกซ่อน</option><option value="reported"<?= $filters['status'] === 'reported' ? ' selected' : '' ?>>มีรายงานรอตรวจ</option></select>
        <select name="type" aria-label="ประเภทคำอธิษฐาน"><option value="">ทุกประเภท</option><option value="preset"<?= $filters['type'] === 'preset' ? ' selected' : '' ?>>เลือกจากรายการ</option><option value="custom"<?= $filters['type'] === 'custom' ? ' selected' : '' ?>>เขียนเอง</option></select>
        <select name="sort" aria-label="เรียงลำดับ"><option value="new">ใหม่สุดก่อน</option><option value="old"<?= $filters['sort'] === 'old' ? ' selected' : '' ?>>เก่าสุดก่อน</option></select>
        <button class="btn btn-primary" type="submit">ค้นหา</button>
        <a class="btn btn-ghost" href="<?= e(adminUrl(['p' => 'krathongs'])) ?>">ล้างตัวกรอง</a>
        <a class="btn btn-ghost" href="<?= e(adminUrl($base + ['export' => 'csv'])) ?>">ส่งออก CSV</a>
    </form>

    <form method="post" action="<?= e($self) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="qs" value="<?= e($keepQs) ?>">
        <div class="bulkbar" id="bulkBar"><span>เลือกแล้ว <b id="bulkCount">0</b> ใบ</span>
            <button class="btn btn-ghost" type="submit" name="action" value="hide">ซ่อน</button>
            <button class="btn btn-ghost" type="submit" name="action" value="show">แสดง</button>
            <button class="btn btn-danger" type="submit" name="action" value="delete" data-confirm="ลบกระทงที่เลือกถาวรหรือไม่? ย้อนกลับไม่ได้">ลบ</button>
        </div>
        <div class="panel table-panel table-panel-cards">
            <div class="table-wrap">
                <table class="table table-krathongs">
                    <thead><tr><th class="c-check"><input type="checkbox" id="checkAll" aria-label="เลือกทั้งหมด"></th><th>กระทง</th><th>ชื่อเล่น</th><th>คำอธิษฐาน</th><th>สร้างเมื่อ</th><th>สถานะ</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): $detail = ['nickname' => $r['nickname'], 'prayer' => $r['prayer'], 'when' => adminTime($r['created_at']), 'krathong' => (string) $r['krathong_name'], 'set' => (string) $r['set_name'], 'ptype' => $r['prayer_type'] === 'custom' ? 'เขียนเอง' : 'เลือกจากรายการ', 'id' => '#' . $r['id'], 'status' => $r['is_hidden'] ? 'ถูกซ่อน' : 'กำลังแสดง']; ?>
                        <tr class="<?= $r['is_hidden'] ? 'is-hidden' : '' ?>">
                            <td class="c-check"><input type="checkbox" class="row-check" name="ids[]" value="<?= (int) $r['id'] ?>" aria-label="เลือกกระทง #<?= (int) $r['id'] ?>"></td>
                            <td class="c-art"><?= renderKrathongArt($r['krathong_key'], $keysBySet[(int) $r['flower_set_id']] ?? [], 'กระทงของ ' . $r['nickname'], 'krathong-art admin-art') ?></td>
                            <td class="c-name"><strong><?= e((string) $r['nickname']) ?></strong><small class="muted">#<?= (int) $r['id'] ?></small><?php if ((int) $r['report_open'] > 0): ?><a class="badge badge-warn" href="<?= e(adminUrl(['p' => 'reports'])) ?>">⚑ รายงาน <?= (int) $r['report_open'] ?></a><?php endif; ?></td>
                            <td class="c-prayer"><?= e(mb_strimwidth((string) $r['prayer'], 0, 110, '…', 'UTF-8')) ?> <?= $r['prayer_type'] === 'custom' ? '<span class="badge">เขียนเอง</span>' : '' ?></td>
                            <td class="c-time nowrap"><?= e(adminTime($r['created_at'])) ?></td>
                            <td class="c-status"><?= $r['is_hidden'] ? '<span class="badge badge-off">ซ่อน</span>' : '<span class="badge badge-on">แสดง</span>' ?></td>
                            <td class="c-actions nowrap">
                                <button type="button" class="btn btn-ghost btn-sm" data-detail="<?= e((string) json_encode($detail, JSON_UNESCAPED_UNICODE)) ?>">ดู</button>
                                <button class="btn btn-ghost btn-sm" type="submit" name="row" value="<?= $r['is_hidden'] ? 'show' : 'hide' ?>:<?= (int) $r['id'] ?>"><?= $r['is_hidden'] ? 'แสดง' : 'ซ่อน' ?></button>
                                <button class="btn btn-danger btn-sm" type="submit" name="row" value="delete:<?= (int) $r['id'] ?>" data-confirm="ลบกระทงของ <?= e((string) $r['nickname']) ?> ถาวรหรือไม่?">ลบ</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="7" class="empty">ไม่พบกระทงตามเงื่อนไขนี้</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <footer class="pager">
                <span class="muted">ทั้งหมด <?= number_format($total) ?> ใบ · หน้า <?= $page ?> / <?= $pages ?></span>
                <span>
                    <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(adminUrl($base + ['page' => $page - 1])) ?>">← ก่อนหน้า</a><?php endif; ?>
                    <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e(adminUrl($base + ['page' => $page + 1])) ?>">ถัดไป →</a><?php endif; ?>
                </span>
            </footer>
        </div>
    </form>

    <dialog class="detail" id="detailDialog">
        <form method="dialog"><button class="detail-close" aria-label="ปิด">×</button></form>
        <p class="eyebrow" data-f="id"></p>
        <h2 data-f="nickname"></h2>
        <blockquote data-f="prayer"></blockquote>
        <dl>
            <dt>กระทง</dt><dd data-f="krathong"></dd>
            <dt>ชุดดอกไม้</dt><dd data-f="set"></dd>
            <dt>ประเภทคำอธิษฐาน</dt><dd data-f="ptype"></dd>
            <dt>สร้างเมื่อ</dt><dd data-f="when"></dd>
            <dt>สถานะ</dt><dd data-f="status"></dd>
        </dl>
    </dialog>
    <?php
}

// =============================== รายงานกระทง ===============================
if ($p === 'reports') {
    [$sql, $countSql] = adminReportsQuery($view);
    $total = (int) $pdo->query($countSql)->fetchColumn();
    $pages = max(1, (int) ceil($total / ADMIN_PER_PAGE));
    $page = min($page, $pages);
    $rows = $pdo->query($sql . ' LIMIT ' . ADMIN_PER_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_PER_PAGE))->fetchAll();

    // รายงานรายใบของกระทงที่แสดงในหน้านี้ (id เป็นจำนวนเต็มจากฐานข้อมูล จึงต่อสตริง IN ได้อย่างปลอดภัย)
    $reportsBy = [];
    if ($rows) {
        $krIds = implode(',', array_map(static fn ($r) => (int) $r['id'], $rows));
        foreach ($pdo->query("SELECT krathong_id, reason, detail, status, created_at FROM krathong_reports WHERE krathong_id IN ($krIds) ORDER BY id DESC")->fetchAll() as $d) {
            $reportsBy[(int) $d['krathong_id']][] = $d;
        }
    }
    $keysBySet = [];
    foreach (loadFlowerSets($pdo) as $set) {
        $keysBySet[$set['id']] = $set['keys'];
    }
    renderSvgSprite();
    $tabs = ['open' => 'รอตรวจสอบ', 'handled' => 'ตรวจสอบแล้ว', 'all' => 'ทั้งหมด'];
    $statusLabels = ['open' => 'รอตรวจสอบ', 'actioned' => 'ซ่อนกระทงแล้ว', 'dismissed' => 'ปัดตกแล้ว'];
    ?>
    <nav class="tabs" aria-label="กรองรายงาน">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="tab<?= $key === $view ? ' active' : '' ?>" href="<?= e(adminUrl(['p' => 'reports', 'view' => $key === 'open' ? '' : $key])) ?>"<?= $key === $view ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($key === 'open' && $openReports > 0): ?> <span class="tab-count"><?= $openReports ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="<?= e($self) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="qs" value="<?= e($keepQs) ?>">
        <div class="rcards">
            <?php foreach ($rows as $r):
                $list = $reportsBy[(int) $r['id']] ?? [];
                $isOpen = (int) $r['open_count'] > 0;
                $chips = [];
                foreach ($list as $d) {
                    if ($isOpen && $d['status'] !== 'open') {
                        continue;   // กระทงที่ยังรอตรวจ สรุปเหตุผลจากรายงานที่ยังไม่จัดการเท่านั้น
                    }
                    $chips[$d['reason']] = ($chips[$d['reason']] ?? 0) + 1;
                }
                arsort($chips);
                ?>
                <article class="rcard<?= $r['is_hidden'] ? ' is-hidden' : '' ?>">
                    <div class="rc-art"><?= renderKrathongArt($r['krathong_key'], $keysBySet[(int) $r['flower_set_id']] ?? [], 'กระทงของ ' . $r['nickname'], 'krathong-art admin-art') ?></div>
                    <div class="rc-body">
                        <header class="rc-head">
                            <strong><?= e((string) $r['nickname']) ?></strong><small class="muted">#<?= (int) $r['id'] ?> · สร้าง <?= e(adminTime($r['created_at'])) ?></small>
                            <span class="rc-badges">
                                <?= $isOpen ? '<span class="badge badge-warn">รอตรวจ ' . (int) $r['open_count'] . ' รายงาน</span>' : '<span class="badge">ตรวจสอบแล้ว</span>' ?>
                                <?= $r['is_hidden'] ? '<span class="badge badge-off">ซ่อนอยู่</span>' : '<span class="badge badge-on">แสดงอยู่</span>' ?>
                            </span>
                        </header>
                        <blockquote class="rc-prayer"><?= e(mb_strimwidth((string) $r['prayer'], 0, 220, '…', 'UTF-8')) ?></blockquote>
                        <ul class="chips" aria-label="เหตุผลที่ถูกรายงาน">
                            <?php foreach ($chips as $reason => $n): ?>
                                <li class="chip"><?= e(REPORT_REASONS[$reason] ?? (string) $reason) ?> <b><?= $n ?></b></li>
                            <?php endforeach; ?>
                        </ul>
                        <details class="rc-more">
                            <summary>ดูรายงานทั้งหมด (<?= (int) $r['total'] ?>)</summary>
                            <ul class="rc-list">
                                <?php foreach ($list as $d): ?>
                                    <li>
                                        <div><b><?= e(REPORT_REASONS[$d['reason']] ?? (string) $d['reason']) ?></b><?= $d['status'] !== 'open' ? ' <span class="badge">' . e($statusLabels[$d['status']] ?? (string) $d['status']) . '</span>' : '' ?></div>
                                        <?php if ($d['detail'] !== ''): ?><p><?= e((string) $d['detail']) ?></p><?php endif; ?>
                                        <time class="muted"><?= e(adminTime($d['created_at'])) ?></time>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    </div>
                    <div class="rc-actions">
                        <?php if (!$r['is_hidden']): ?>
                            <button class="btn btn-primary btn-sm" type="submit" name="row" value="hide:<?= (int) $r['id'] ?>">ซ่อนกระทง</button>
                        <?php else: ?>
                            <button class="btn btn-ghost btn-sm" type="submit" name="row" value="show:<?= (int) $r['id'] ?>">แสดงอีกครั้ง</button>
                        <?php endif; ?>
                        <?php if ($isOpen): ?>
                            <button class="btn btn-ghost btn-sm" type="submit" name="row" value="dismiss:<?= (int) $r['id'] ?>" data-confirm="ปัดตกรายงานของกระทงนี้? (กระทงจะยังแสดงตามเดิม)">ไม่พบปัญหา · ปัดตก</button>
                        <?php endif; ?>
                        <button class="btn btn-danger btn-sm" type="submit" name="row" value="delete:<?= (int) $r['id'] ?>" data-confirm="ลบกระทงของ <?= e((string) $r['nickname']) ?> ถาวรหรือไม่? รายงานทั้งหมดของกระทงนี้จะถูกลบด้วย">ลบถาวร</button>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <div class="panel empty-panel"><p class="empty"><?= $view === 'open' ? 'ไม่มีรายงานที่รอตรวจสอบ 🎉' : 'ไม่พบรายงานในหมวดนี้' ?></p></div>
            <?php endif; ?>
        </div>
        <?php if ($total > 0): ?>
            <footer class="pager pager-plain">
                <span class="muted">ทั้งหมด <?= number_format($total) ?> ใบ · หน้า <?= $page ?> / <?= $pages ?></span>
                <span>
                    <?php $rbase = ['p' => 'reports', 'view' => $view === 'open' ? '' : $view]; ?>
                    <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(adminUrl($rbase + ['page' => $page - 1])) ?>">← ก่อนหน้า</a><?php endif; ?>
                    <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e(adminUrl($rbase + ['page' => $page + 1])) ?>">ถัดไป →</a><?php endif; ?>
                </span>
            </footer>
        <?php endif; ?>
    </form>
    <?php
}

// =============================== บันทึกกิจกรรม ===============================
if ($p === 'log') {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM admin_log')->fetchColumn();
    $pages = max(1, (int) ceil($total / 40));
    $page = min($page, $pages);
    $rows = $pdo->query('SELECT * FROM admin_log ORDER BY id DESC LIMIT 40 OFFSET ' . (($page - 1) * 40))->fetchAll();
    ?>
    <div class="panel table-panel">
        <div class="table-wrap">
            <table class="table table-stack">
                <thead><tr><th>เวลา (ไทย)</th><th>กิจกรรม</th><th>รายการที่เกี่ยวข้อง</th><th>รายละเอียด</th><th>IP (hash)</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr><td class="nowrap" data-label="เวลา (ไทย)"><?= e(adminTime($r['created_at'], 'd/m/Y H:i:s')) ?></td>
                        <td data-label="กิจกรรม"><span class="badge <?= $r['action'] === 'login_fail' || $r['action'] === 'delete' ? 'badge-off' : '' ?>"><?= e(ADMIN_ACTION_LABELS[$r['action']] ?? $r['action']) ?></span></td>
                        <td data-label="รายการที่เกี่ยวข้อง"><?= $r['target'] !== '' ? 'id ' . e((string) $r['target']) : '—' ?></td><td data-label="รายละเอียด"><?= e((string) $r['detail']) ?></td><td class="muted" data-label="IP (hash)"><?= e((string) $r['ip_hash']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="5" class="empty">ยังไม่มีกิจกรรม</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <footer class="pager"><span class="muted">ทั้งหมด <?= number_format($total) ?> รายการ · หน้า <?= $page ?> / <?= $pages ?></span>
            <span><?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(adminUrl(['p' => 'log', 'page' => $page - 1])) ?>">← ใหม่กว่า</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e(adminUrl(['p' => 'log', 'page' => $page + 1])) ?>">เก่ากว่า →</a><?php endif; ?></span></footer>
    </div>
    <?php
}

// =============================== ระบบและความปลอดภัย ===============================
if ($p === 'system') {
    $dbFile = (string) ($pdo->query('PRAGMA database_list')->fetch()['file'] ?? '');
    $dbSize = is_file($dbFile) ? filesize($dbFile) : 0;
    $info = [
        'SQLite' => (string) $pdo->query('SELECT sqlite_version()')->fetchColumn(),
        'schema version' => (string) $pdo->query('PRAGMA user_version')->fetchColumn() . ' / ' . DB_SCHEMA_VERSION,
        'journal mode' => (string) $pdo->query('PRAGMA journal_mode')->fetchColumn(),
        'ขนาดไฟล์ฐานข้อมูล' => number_format($dbSize / 1024, 1) . ' KB',
        'เวลาเซิร์ฟเวอร์ (ไทย)' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('d/m/Y H:i:s'),
    ];
    $rate = $pdo->query('SELECT bucket, SUM(created_at > ' . (time() - 3600) . ') AS h, COUNT(*) AS d FROM rate_events GROUP BY bucket ORDER BY bucket')->fetchAll();
    $bucketLabels = ['create_burst' => 'สร้างกระทง (ช่วงสั้น 10 นาที)', 'create_daily' => 'สร้างกระทง (รายวัน)', 'check_text' => 'ตรวจคำหยาบ', 'admin_login' => 'เข้าสู่ระบบผู้ดูแล', 'report_burst' => 'รายงานกระทง (ช่วงสั้น 10 นาที)', 'report_daily' => 'รายงานกระทง (รายวัน)'];
    $testText = trim((string) ($_GET['t'] ?? ''));
    ?>
    <div class="grid-2">
        <section class="panel"><header class="panel-head"><h2>ฐานข้อมูลและเซิร์ฟเวอร์</h2></header>
            <dl class="kv"><?php foreach ($info as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?></dl>
        </section>
        <section class="panel">
            <header class="panel-head"><h2>การจำกัดอัตรา (Rate limit)</h2></header>
            <?php if ($rate): ?>
                <table class="table table-stack"><thead><tr><th>ประเภท</th><th>1 ชม.</th><th>ทั้งหมด (≤1 วัน)</th></tr></thead><tbody>
                    <?php foreach ($rate as $r): ?><tr><td data-label="ประเภท"><?= e($bucketLabels[$r['bucket']] ?? $r['bucket']) ?></td><td data-label="1 ชม."><?= (int) $r['h'] ?></td><td data-label="ทั้งหมด (≤1 วัน)"><?= (int) $r['d'] ?></td></tr><?php endforeach; ?>
                </tbody></table>
            <?php else: ?><p class="muted">ยังไม่มีเหตุการณ์</p><?php endif; ?>
            <form method="post" action="<?= e($self) ?>" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="qs" value="p=system">
                <button class="btn btn-ghost btn-sm" type="submit" name="action" value="clear_rate" data-confirm="ล้างข้อมูล rate limit ทั้งหมด? (ผู้ที่ถูกจำกัดอยู่จะใช้งานได้ทันที)">ล้างข้อมูล rate limit</button>
            </form>
        </section>
    </div>
    <section class="panel" id="profanityTest">
        <header class="panel-head"><h2>ทดสอบตัวกรองคำหยาบ</h2><span class="muted">ใช้ตัวกรองเดียวกับตอนสร้างกระทง</span></header>
        <form method="get" action="<?= e($self) ?>#profanityTest" class="toolbar" data-inline-test>
            <input type="hidden" name="p" value="system">
            <input type="text" name="t" value="<?= e($testText) ?>" maxlength="300" placeholder="พิมพ์ข้อความที่ต้องการทดสอบ">
            <button class="btn btn-primary" type="submit">ทดสอบ</button>
        </form>
        <div id="profanityResult" role="status" aria-live="polite">
        <?php if ($testText !== ''): $bad = containsProfanity($testText); ?>
            <p class="alert <?= $bad ? 'alert-err' : 'alert-ok' ?>"><?= $bad ? 'พบคำไม่เหมาะสม — ข้อความนี้จะถูกปฏิเสธ' : 'ไม่พบคำไม่เหมาะสม — ข้อความนี้จะผ่านตัวกรอง' ?></p>
        <?php endif; ?>
        </div>
    </section>
    <?php
}

adminShellBottom();
