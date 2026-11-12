<?php
declare(strict_types=1);

/** ลิงก์ไฟล์ static พร้อมเลขเวอร์ชันจากเวลาแก้ไขไฟล์ (?v=...) — ตั้ง cache ยาวได้โดยผู้ใช้ไม่ค้างไฟล์เก่าหลังอัปเดต */
function asset(string $path, string $prefix = '../'): string
{
    $file = __DIR__ . '/../' . $path;
    $version = is_file($file) ? (int) filemtime($file) : 0;
    return $prefix . $path . ($version > 0 ? '?v=' . $version : '');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** กำลังใช้ HTTPS หรือไม่ (รองรับหลัง proxy เมื่อตั้ง KRATHONG_TRUST_PROXY=1) */
function isHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return getenv('KRATHONG_TRUST_PROXY') === '1'
        && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/* ---- จำกระทงของผู้ใช้ (ไม่ต้องล็อกอิน) ----
   เบราว์เซอร์แต่ละเครื่องถือ "token สุ่ม 256 บิต" ใน cookie kt_owner (HttpOnly, SameSite=Lax, อายุ 1 ปี ต่ออายุทุกครั้งที่เข้า)
   ฐานข้อมูลเก็บเฉพาะค่า SHA-256 ของ token ในคอลัมน์ krathongs.owner_token_hash (ฐานข้อมูลรั่วก็เอาไปสวมสิทธิ์ไม่ได้)
   (ไม่มีระบบกู้คืน: ล้าง cookie หรือเปลี่ยนเครื่องแล้วจะไม่เห็นว่าอันไหนเป็นของตน) */
const OWNER_COOKIE = 'kt_owner';

function isOwnerToken(string $token): bool
{
    return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
}

function ownerHash(string $token): string
{
    return hash('sha256', $token);
}

function setOwnerCookie(string $token): void
{
    if (headers_sent()) {
        return;
    }
    setcookie(OWNER_COOKIE, $token, [
        'expires' => time() + 365 * 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isHttps(),
    ]);
    $_COOKIE[OWNER_COOKIE] = $token;
}

/** token ของเบราว์เซอร์นี้ (null ถ้ายังไม่มี) */
function currentOwnerToken(): ?string
{
    $token = (string) ($_COOKIE[OWNER_COOKIE] ?? '');
    return isOwnerToken($token) ? $token : null;
}

/** token ของเบราว์เซอร์นี้ ถ้ายังไม่มีให้สร้างใหม่และตั้ง cookie (เรียกก่อนมี output) */
function ensureOwnerToken(): string
{
    $token = currentOwnerToken();
    if ($token === null) {
        $token = bin2hex(random_bytes(32));
    }
    setOwnerCookie($token);
    return $token;
}

/** เริ่มเซสชันพร้อม cookie ที่ปลอดภัยขึ้น (HttpOnly, SameSite=Lax, Secure เมื่อใช้ HTTPS) */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isHttps(),
    ]);
    session_start();
}

/**
 * IP ของผู้ใช้ ใช้ REMOTE_ADDR เป็นหลัก
 * ถ้าเว็บอยู่หลัง Cloudflare/Reverse proxy ให้ตั้ง KRATHONG_TRUST_PROXY=1 เพื่ออ่าน IP จริงจาก header
 * (เปิดเฉพาะเมื่ออยู่หลัง proxy จริงเท่านั้น ไม่เช่นนั้นผู้ใช้ปลอม header เลี่ยงการจำกัดได้)
 */
function clientIp(): string
{
    if (getenv('KRATHONG_TRUST_PROXY') === '1') {
        $cf = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }
        $xff = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
        if ($xff !== '' && filter_var($xff, FILTER_VALIDATE_IP)) {
            return $xff;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/**
 * จำกัดจำนวนครั้งต่อ IP ในช่วงเวลาหนึ่ง คืนค่า true = ทำได้ (และบันทึกครั้งนี้ไว้), false = เกินที่กำหนด
 * เก็บเฉพาะ hash ของ IP ไม่เก็บ IP จริง ถ้าฐานข้อมูลมีปัญหาจะ "ปล่อยผ่าน" เพื่อไม่ให้ผู้ใช้ปกติใช้งานไม่ได้
 */
function rateLimitAllow(PDO $pdo, string $bucket, int $max, int $windowSeconds): bool
{
    try {
        $now = time();
        $ipHash = hash('sha256', 'krathong-rl|' . clientIp());
        $count = $pdo->prepare('SELECT COUNT(*) FROM rate_events WHERE bucket = :b AND ip_hash = :ip AND created_at > :since');
        $count->execute([':b' => $bucket, ':ip' => $ipHash, ':since' => $now - $windowSeconds]);
        if ((int) $count->fetchColumn() >= $max) {
            return false;
        }
        $pdo->prepare('INSERT INTO rate_events (bucket, ip_hash, created_at) VALUES (:b, :ip, :t)')
            ->execute([':b' => $bucket, ':ip' => $ipHash, ':t' => $now]);
        if (random_int(1, 100) === 1) {   // เก็บกวาดข้อมูลเก่าเป็นครั้งคราว
            $pdo->prepare('DELETE FROM rate_events WHERE created_at < :t')->execute([':t' => $now - 86400]);
        }
        return true;
    } catch (Throwable $exception) {
        error_log('rate limit: ' . $exception->getMessage());
        return true;
    }
}

/** URL หลักของเว็บ (ใช้ใน og:url / og:image ที่ต้องเป็น URL เต็ม) ตั้ง KRATHONG_BASE_URL เพื่อกำหนดเอง เช่น https://example.com */
function siteBaseUrl(): string
{
    $configured = (string) getenv('KRATHONG_BASE_URL');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }
    $root = realpath(__DIR__ . '/..') ?: '';
    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
    $subPath = ($docRoot !== '' && str_starts_with($root, $docRoot)) ? str_replace('\\', '/', substr($root, strlen($docRoot))) : '';
    return (isHttps() ? 'https://' : 'http://') . $host . rtrim($subPath, '/');
}

function absoluteUrl(string $path): string
{
    return siteBaseUrl() . '/' . ltrim($path, '/');
}

/**
 * แท็ก favicon + Open Graph/Twitter Card (ให้ลิงก์ที่แชร์ใน LINE/Facebook มี preview)
 *
 * @param string $assetPrefix prefix ของไฟล์ assets เมื่อมองจากหน้าปัจจุบัน ('' หน้าแรก, '../' หน้าใน /pages)
 * @param array{title?:string, description?:string, path?:string} $meta
 */
function renderMetaTags(string $assetPrefix, array $meta = []): void
{
    $title = $meta['title'] ?? 'ลอยกระทงออนไลน์ | คืนมหัศจรรย์ริมสายน้ำ';
    $description = $meta['description'] ?? 'ร่วมลอยกระทงออนไลน์ สร้างกระทง เลือกดอกไม้ และส่งคำอธิษฐานไปกับสายน้ำแห่งความสุข';
    $url = absoluteUrl($meta['path'] ?? '');
    $image = absoluteUrl('assets/images/og-cover.jpg');
    ?>
    <link rel="icon" type="image/svg+xml" href="<?= e($assetPrefix) ?>assets/images/favicon.svg">
    <link rel="canonical" href="<?= e($url) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="ลอยกระทงออนไลน์">
    <meta property="og:locale" content="th_TH">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($url) ?>">
    <meta property="og:image" content="<?= e($image) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($image) ?>">
<?php
}

/**
 * โหลดชุดดอกไม้ทั้งหมดพร้อมรายการดอกไม้ในแต่ละชุด
 *
 * @return array<int, array{id:int, name:string, theme:string, description:string, items:array, keys:string[], flower_names:string[]}>
 */
function loadFlowerSets(PDO $pdo): array
{
    $sets = $pdo->query('SELECT id, name, theme, description FROM flower_sets WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
    $itemStatement = $pdo->prepare(
        'SELECT i.flower_id, i.quantity, f.name, f.svg_key
         FROM flower_set_items i JOIN flowers f ON f.id = i.flower_id
         WHERE i.set_id = :set_id ORDER BY i.quantity DESC, f.sort_order'
    );
    $result = [];
    foreach ($sets as $set) {
        $itemStatement->execute([':set_id' => $set['id']]);
        $items = $itemStatement->fetchAll();
        $keys = [];
        $names = [];
        foreach ($items as $item) {
            for ($i = 0; $i < (int) $item['quantity']; $i++) {
                $keys[] = $item['svg_key'];
            }
            $names[] = preg_replace('/^ดอก/u', '', $item['name']) . ((int) $item['quantity'] > 1 ? ' ×' . (int) $item['quantity'] : '');
        }
        // ชุดที่ถูกต้องต้องมีดอกไม้รวม 5 ดอกพอดี
        if (count($keys) !== 5) {
            continue;
        }
        $set['id'] = (int) $set['id'];
        $set['items'] = $items;
        // เรียงตามช่องบนกระทง [กลาง, ซ้ายใน, ขวาใน, ซ้ายนอก, ขวานอก] ให้ดอกชนิดซ้ำไปอยู่คนละปลาย
        // ไม่วางติดกัน (ซ้ำกันติดกันสีจะกลืนเป็นก้อนเดียว)
        if ($keys[0] === $keys[1]) {
            $keys = [$keys[2], $keys[3], $keys[4], $keys[0], $keys[1]];
        }
        $set['keys'] = $keys;
        $set['flower_names'] = $names;
        $result[] = $set;
    }
    return $result;
}

/**
 * โหลดกระทงที่บันทึกแล้วพร้อมข้อมูลสำหรับวาดภาพ (svg_key ของกระทงและดอกไม้เรียงตามช่อง)
 *
 * @param string $where เงื่อนไข SQL ที่ใช้ alias k (krathongs) เช่น 'k.id = :id' (ว่างได้) — กระทงที่ผู้ดูแลซ่อน (is_hidden = 1) จะไม่ถูกโหลดเสมอ
 * @return array<int, array<string,mixed>>
 */
function loadKrathongs(PDO $pdo, string $where = '', array $params = [], int $limit = 24, int $offset = 0): array
{
    $sql = 'SELECT k.id, k.nickname, k.prayer, k.prayer_type, k.created_at, k.flower_set_id, t.svg_key AS krathong_key, t.name AS krathong_name
            FROM krathongs k LEFT JOIN krathong_types t ON t.id = k.krathong_type_id'
        . ' WHERE k.is_hidden = 0' . ($where !== '' ? ' AND (' . $where . ')' : '')
        . ' ORDER BY k.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();

    $keysBySet = [];
    foreach (loadFlowerSets($pdo) as $set) {
        $keysBySet[$set['id']] = $set['keys'];
    }
    foreach ($rows as &$row) {
        $row['flower_keys'] = $keysBySet[(int) $row['flower_set_id']] ?? [];
    }
    unset($row);
    return $rows;
}

/**
 * จำนวนกระทงที่แสดงสาธารณะ: ทั้งหมด และ "วันนี้" (นับตามเวลาไทย Asia/Bangkok)
 * created_at เก็บเป็น UTC (CURRENT_TIMESTAMP) จึงแปลงเที่ยงคืนเวลาไทยเป็น UTC ก่อนเทียบ
 * ไม่นับกระทงที่ผู้ดูแลซ่อน (is_hidden = 1)
 *
 * @return array{total:int, today:int}
 */
function krathongCounts(PDO $pdo): array
{
    $startUtc = (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))
        ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $statement = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(created_at >= :start), 0) AS today FROM krathongs WHERE is_hidden = 0');
    $statement->execute([':start' => $startUtc]);
    $row = $statement->fetch() ?: [];
    return ['total' => (int) ($row['total'] ?? 0), 'today' => (int) ($row['today'] ?? 0)];
}

/** วาด 1 กระทงบนแม่น้ำ */
function renderRiverKrathong(array $k, bool $isMine): string
{
    $style = sprintf('left:%s%%;top:%s%%;--s:%s;--bob:%ss;z-index:%d',
        $k['x'], $k['y'], $k['scale'], $k['bob'], $isMine ? 500 : (int) round($k['y'] * 4));
    $art = renderKrathongArt($k['krathong_key'], $k['flower_keys'], 'กระทงของ ' . $k['nickname'], 'krathong-art river-art');
    return '<button type="button" class="rk' . ($isMine ? ' rk-mine' : '') . '" style="' . e($style) . '"'
        . ' data-id="' . (int) $k['id'] . '" data-x="' . $k['x'] . '" data-y="' . $k['y'] . '" data-speed="' . $k['speed'] . '" data-heading="' . $k['heading'] . '" data-name="' . e($k['nickname']) . '" data-prayer="' . e($k['prayer']) . '"'
        . ' aria-label="กระทงของ ' . e($k['nickname']) . '">'
        . '<span class="rk-drift"><span class="rk-release"><span class="rk-arc"><span class="rk-bob">' . $art . '</span></span></span>'
        . ($isMine ? '<span class="rk-ring"></span>' : '')
        . '<span class="rk-splash"></span>'
        . '<span class="rk-wish" aria-hidden="true">' . e($k['prayer']) . '</span>'
        . '<span class="rk-tag">' . ($isMine ? '✦ กระทงของคุณ' : e($k['nickname'])) . '</span></span></button>';
}

/** แถบสถิติ "กระทงทั้งหมด / กระทงวันนี้" ($class ใช้ปรับสไตล์ตามหน้า) */
function renderKrathongStats(array $counts, string $class = '', string $liveUrl = ''): string
{
    return '<div class="kt-stats' . ($class !== '' ? ' ' . e($class) : '') . '" role="status"' . ($liveUrl !== '' ? ' data-live="' . e($liveUrl) . '"' : '') . '>'
        . '<div class="kt-stat"><strong data-stat="total">' . number_format($counts['total']) . '</strong><span>กระทงทั้งหมด</span></div>'
        . '<div class="kt-stat"><strong data-stat="today">' . number_format($counts['today']) . '</strong><span>กระทงวันนี้</span></div>'
        . '</div>';
}

/** วาดภาพกระทงพร้อมเทียนและดอกไม้เป็น <svg> (ตำแหน่งดอกไม้ต้องตรงกับ FLOWER_SLOTS ใน create.js) */
function renderKrathongArt(?string $krathongKey, array $flowerKeys, string $label = 'กระทง', string $class = 'krathong-art'): string
{
    // [ศูนย์กลาง x, y, ขนาด, องศาหมุน] ลำดับ: กลาง, ซ้ายใน, ขวาใน, ซ้ายนอก, ขวานอก
    $slots = [[120, 139, 54, 0], [93, 131, 44, -14], [147, 131, 44, 12], [67, 124, 38, -22], [173, 124, 38, 20]];
    $order = [3, 4, 1, 2, 0];
    $out = '<svg class="' . e($class) . '" viewBox="0 20 240 180" role="img" aria-label="' . e($label) . '">'
        . '<ellipse cx="120" cy="104" rx="112" ry="72" fill="url(#gradFlame)" opacity=".45"/>';
    if ($krathongKey) {
        $out .= '<use href="#kt-' . e($krathongKey) . '" x="20" y="62" width="200" height="130"/>';
    }
    $out .= '<path d="M106 134 L93 66" stroke="#7a4a22" stroke-width="2.2" stroke-linecap="round"/><circle cx="93" cy="66" r="2" fill="#ff5a36"/>'
        . '<path d="M134 134 L148 62" stroke="#7a4a22" stroke-width="2.2" stroke-linecap="round"/><circle cx="148" cy="62" r="2" fill="#ff5a36"/>'
        . '<circle class="flame-glow" cx="120" cy="72" r="20" fill="url(#gradFlame)"/>'
        . '<rect x="113.5" y="84" width="13" height="50" rx="2" fill="url(#gradCandle)" stroke="#d9bd7c" stroke-width=".8"/>'
        . '<path d="M120 84 L120 78" stroke="#4a3320" stroke-width="1.4"/>'
        . '<path class="flame-outer" d="M120 80 C112 72 119 64 120 55 C121 64 128 72 120 80Z" fill="#ffae2b"/>'
        . '<path class="flame-inner" d="M120 78 C116.5 73 119.5 69 120 64 C120.5 69 123.5 73 120 78Z" fill="#fff3b0"/>';
    foreach ($order as $slot) {
        if (!isset($flowerKeys[$slot])) {
            continue;
        }
        [$cx, $cy, $size, $rot] = $slots[$slot];
        $out .= '<use href="#fk-' . e($flowerKeys[$slot]) . '" x="' . ($cx - $size / 2) . '" y="' . ($cy - $size / 2)
            . '" width="' . $size . '" height="' . $size . '" transform="rotate(' . $rot . ' ' . $cx . ' ' . $cy . ')"/>';
    }
    return $out . '</svg>';
}

/**
 * ส่วนหัว (แบรนด์ + เมนู + ปุ่มเมนูมือถือ) ที่ใช้ร่วมกันทุกหน้าย่อยใน /pages
 *
 * @param string $active หน้าปัจจุบัน: 'home' | 'create' | 'river' | ''
 */
function renderSiteHeader(string $active = ''): void
{
    $links = ['home' => ['../index.php', 'หน้าแรก'], 'create' => ['create.php', 'สร้างกระทง'], 'river' => ['river.php', 'แม่น้ำของทุกคน']];
    ?>
    <header class="site-header">
        <a class="brand" href="../index.php" aria-label="ลอยกระทงออนไลน์ หน้าแรก"><span class="brand-mark"><?= svgLotusIcon() ?></span><span>ลอยกระทง<span class="brand-light">ออนไลน์</span></span><span class="brand-sep" aria-hidden="true"></span><img class="brand-partner" src="../assets/images/ch5-emblem.png" alt="TV5HD Online" width="38" height="36"></a>
        <button class="menu-toggle" id="menuToggle" type="button" aria-label="เปิดเมนู" aria-expanded="false" aria-controls="mainNav"><span></span><span></span><span></span></button>
        <nav class="main-nav" id="mainNav" aria-label="เมนูหลัก">
            <?php foreach ($links as $key => [$href, $label]): ?>
                <a class="nav-link<?= $key === $active ? ' active' : '' ?>" href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>
    <?php if ($active === 'river'): /* คำเปิดหน้า: เฉพาะหน้าแม่น้ำ */ ?>
    <div class="moon-intro" aria-hidden="true">
        <div class="moon-intro-inner">
            <svg class="moon-intro-icon" viewBox="0 0 64 64" aria-hidden="true"><defs><radialGradient id="miHalo"><stop offset="0" stop-color="#ffe9a8" stop-opacity=".55"/><stop offset="1" stop-color="#ffe9a8" stop-opacity="0"/></radialGradient></defs><circle cx="32" cy="32" r="31" fill="url(#miHalo)"/><path d="M40 10a23 23 0 1 0 0 44 18 18 0 0 1 0-44z" fill="#ffe9a8"/></svg>
            <p class="moon-intro-text"><span>คืนเพ็ญ</span><span>เดือนสิบสอง</span></p>
            <span class="moon-intro-line"></span>
        </div>
    </div>
    <?php endif; ?>
<?php
}

/** ส่วน <head> และส่วนหัวของหน้าที่ใช้ร่วมกัน (view / gallery / river) */
function renderPageTop(string $title, string $bodyClass = 'create-page', string $active = '', array $meta = []): void
{
    ?><!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#102d25">
    <title><?= e($title) ?> | ลอยกระทงออนไลน์</title>
    <?php renderMetaTags('../', $meta + ['title' => $title . ' | ลอยกระทงออนไลน์', 'path' => 'pages/' . basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''))]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600<?= $active === 'river' ? '&family=Charmonman:wght@400' : '' ?>&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="../assets/images/backgrounds/river-lantern-night.webp" type="image/webp">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
    <?php renderSvgSprite(); ?>
    <div class="scene" aria-hidden="true"></div><div class="scene-shade" aria-hidden="true"></div>
    <?php renderSiteHeader($active); ?>
<?php
}

/** @param string|string[] $scripts ชื่อไฟล์ใน assets/js (ใส่เป็น array ได้หลายไฟล์) */
function renderPageBottom(string|array $scripts = ''): void
{
    $scripts = array_values(array_filter((array) $scripts, static fn ($s) => $s !== ''));
    ?>
    <footer class="site-footer"><span class="footer-brand"><?= svgLotusIcon() ?> ลอยกระทงออนไลน์</span><span>ขอให้ค่ำคืนนี้เต็มไปด้วยความสุข · <a class="footer-link" href="privacy.php">ความเป็นส่วนตัว</a></span></footer>
    <script src="<?= e(asset('assets/js/script.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/audio.js')) ?>"></script>
    <?php foreach ($scripts as $script): ?><script src="<?= e(asset('assets/js/' . $script)) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
<?php
}

// ---------- แม่น้ำในภาพพื้นหลัง ----------
// ทุกค่าเป็นเปอร์เซ็นต์ของภาพ river-lantern-night.png (x = ความกว้าง, y = ความสูง) จึงใช้ได้กับทุกขนาดหน้าจอ
const RIVER_RATIO = 0.5628;        // สูง/กว้าง ของภาพ (941/1672)
const RIVER_Y_FAR = 63.5;          // y ของผืนน้ำส่วนที่ไกลที่สุด (ใกล้ฝั่งตรงข้าม)
const RIVER_Y_NEAR = 97.5;         // y ของผืนน้ำส่วนที่ใกล้ที่สุด (ริมท่าน้ำ)
const RIVER_SCALE_FAR = 0.42;      // ขนาดกระทงเมื่ออยู่ไกลสุด
const RIVER_SCALE_NEAR = 1.35;     // ขนาดกระทงเมื่ออยู่ใกล้สุด

/**
 * ขอบเขตผืนน้ำที่กระทงลอยได้ (พอลิกอนตามที่วาดไว้บนภาพ) กระทงจะไม่ลอยออกนอกพื้นที่นี้
 *
 * @return array<int, array{0:float,1:float}>
 */
function riverPolygon(): array
{
    return [
        [4.19, 64.44],
        [4.31, 64.86],
        [6.58, 66.67],
        [6.7, 67.41],
        [7.48, 67.41],
        [7.95, 67.83],
        [9.27, 70.38],
        [9.39, 71.34],
        [9.75, 71.66],
        [10.77, 74.52],
        [11.84, 76.33],
        [15.55, 79.3],
        [15.67, 80.47],
        [17.05, 80.89],
        [18.72, 83.23],
        [19.68, 83.86],
        [20.1, 86.84],
        [21.23, 88.11],
        [24.28, 89.92],
        [27.03, 90.34],
        [31.64, 91.83],
        [32.0, 91.3],
        [32.0, 90.13],
        [32.6, 87.79],
        [38.1, 87.79],
        [39.59, 88.22],
        [40.13, 88.64],
        [39.83, 93.21],
        [40.31, 96.6],
        [40.97, 97.24],
        [44.02, 97.98],
        [50.0, 97.77],
        [51.02, 97.24],
        [58.37, 97.03],
        [71.29, 97.13],
        [71.35, 96.28],
        [77.81, 95.86],
        [78.23, 95.33],
        [79.49, 94.9],
        [81.28, 92.99],
        [82.36, 90.98],
        [84.03, 89.6],
        [85.05, 88.32],
        [89.77, 85.46],
        [91.69, 82.91],
        [94.32, 82.27],
        [95.39, 81.53],
        [96.17, 81.53],
        [96.35, 81.85],
        [98.15, 81.53],
        [98.33, 81.21],
        [98.33, 78.24],
        [98.74, 75.69],
        [98.8, 66.24],
        [98.68, 65.5],
        [98.03, 64.86],
        [95.69, 64.86],
        [92.28, 65.71],
        [79.49, 65.71],
        [76.26, 64.97],
        [68.06, 64.86],
        [64.23, 64.44],
        [41.99, 64.54],
        [41.81, 64.86],
        [12.86, 64.86],
        [10.53, 64.12],
        [6.94, 64.01],
        [4.49, 63.48],
    ];
}

/** ค่า CSS clip-path: polygon(...) ของผืนน้ำ (เป็น % ของภาพ) ใช้ตัดชั้นอนิเมชั่นน้ำให้ขยับอยู่ในพื้นที่นี้เท่านั้น */
function riverClipPath(): string
{
    return 'polygon(' . implode(',', array_map(static fn (array $p): string => $p[0] . '% ' . $p[1] . '%', riverPolygon())) . ')';
}

/**
 * ขอบเขตท้องฟ้าที่เมฆลอยได้ (พอลิกอนตามเส้นที่วาดไว้บนภาพ; เป็น % ของภาพ เหมือน riverPolygon)
 * เมฆจะลอยและค่อย ๆ จางหายอยู่ในพื้นที่นี้เท่านั้น ไม่ทับต้นไม้ โคมไฟ ภูเขา และวัดริมฝั่ง
 *
 * @return array<int, array{0:float,1:float}>
 */
function riverCloudPolygon(): array
{
    return [
        [20.4, 0],
        [100, 0],
        [100, 37.5],
        [95, 38.5],
        [94.6, 41.4],
        [94.9, 41.7],
        [92.7, 44.6],
        [86.9, 44.9],
        [83.3, 46.5],
        [82.3, 45.6],
        [82.2, 43.1],
        [81.5, 42],
        [78.9, 41],
        [75.4, 40.9],
        [74.7, 41],
        [73.9, 42.7],
        [73.2, 46.6],
        [72.5, 47.6],
        [63.2, 44.9],
        [55.2, 45.6],
        [52.8, 47.8],
        [49.5, 52.7],
        [42.7, 50.5],
        [38.5, 47.8],
        [34.4, 47.8],
        [32.6, 48.7],
        [32.1, 50],
        [27.5, 49.5],
        [18.2, 46.1],
        [15.2, 44.1],
        [11, 44.4],
        [10.5, 43.6],
        [9.9, 27.6],
        [15.2, 26.1],
        [15.3, 20],
        [16.7, 17.8],
        [19.6, 15.9],
        [19.9, 13.7],
    ];
}

/** ค่า CSS clip-path: polygon(...) ของท้องฟ้าที่เมฆลอยได้ */
function riverCloudClipPath(): string
{
    return 'polygon(' . implode(',', array_map(static fn (array $p): string => $p[0] . '% ' . $p[1] . '%', riverCloudPolygon())) . ')';
}

/** จุด (x,y) อยู่ภายในพอลิกอนหรือไม่ (ray casting) */
function riverPointInside(float $x, float $y, array $poly): bool
{
    $inside = false;
    for ($i = 0, $j = count($poly) - 1; $i < count($poly); $j = $i++) {
        [$xi, $yi] = $poly[$i];
        [$xj, $yj] = $poly[$j];
        if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-9) + $xi)) {
            $inside = !$inside;
        }
    }
    return $inside;
}

/** จุดอยู่ในแม่น้ำและมีระยะเผื่อรอบตัว (ตัวกระทงไม่ล้ำขอบ) */
function riverContains(float $x, float $y, float $margin = 2.4): bool
{
    static $poly = null;
    $poly ??= riverPolygon();
    $dy = $margin * 0.5 / RIVER_RATIO;
    foreach ([[0, 0], [$margin, 0], [-$margin, 0], [0, $dy], [0, -$dy]] as [$ox, $oy]) {
        if (!riverPointInside($x + $ox, $y + $oy, $poly)) {
            return false;
        }
    }
    return true;
}

/** ขนาดกระทงตามความลึกของภาพ: ยิ่งอยู่ไกล (y น้อย) ยิ่งเล็ก ยิ่งใกล้ท่าน้ำยิ่งใหญ่ */
function riverScale(float $y): float
{
    $t = max(0.0, min(1.0, ($y - RIVER_Y_FAR) / (RIVER_Y_NEAR - RIVER_Y_FAR)));
    return round(RIVER_SCALE_FAR + (RIVER_SCALE_NEAR - RIVER_SCALE_FAR) * $t, 3);
}

/**
 * กำหนดตำแหน่งเริ่มต้นของกระทงบนผืนน้ำ (เป็นเปอร์เซ็นต์ของภาพ) แบบคงที่ตาม id เพื่อไม่ให้ตำแหน่งสุ่มเปลี่ยนทุกครั้งที่รีเฟรช
 * ตำแหน่งอยู่ในขอบเขต riverPolygon() เสมอ และขนาดเปลี่ยนตามความไกล-ใกล้ (riverScale)
 * การลอยต่อหลังจากนั้นทำใน river.js (ลอยอยู่ในขอบเขตเดียวกัน)
 *
 * @param array<int, array<string,mixed>> $rows
 * @param array<int, array{0:float,1:float}> $taken ตำแหน่งที่ถูกใช้ไปแล้ว (เช่นกระทงของผู้ใช้)
 * @return array<int, array<string,mixed>>
 */
function placeKrathongs(array $rows, array $taken = []): array
{
    $depthSpan = (RIVER_Y_NEAR - 2.5) - (RIVER_Y_FAR + 1.5);
    foreach ($rows as $i => &$row) {
        mt_srand((int) $row['id'] * 7919 + 17);
        // กระจายความลึก: กระทงลำที่ i ได้ระยะไกล-ใกล้ตามลำดับ golden ratio จึงมีทั้งไกลและใกล้ปะปนกัน ไม่กองอยู่แถวเดียว
        $depth = fmod(($i + 1) * 0.618034 + (mt_rand(0, 100) / 100) * 0.12, 1.0);
        $yTarget = RIVER_Y_FAR + 1.5 + $depth * $depthSpan;
        $best = null;
        $bestGap = -1.0;
        for ($try = 0; $try < 160; $try++) {
            $x = mt_rand(40, 990) / 10;
            $y = round($yTarget + (mt_rand(-20, 20) / 10), 1);
            if (!riverContains($x, $y)) {
                continue;
            }
            $gap = 99.0;
            foreach ($taken as $t) {
                $gap = min($gap, hypot($x - $t[0], ($y - $t[1]) * RIVER_RATIO * 2.4) / ($t[2] ?? 1.0));
            }
            if ($gap > $bestGap) {
                $bestGap = $gap;
                $best = [$x, $y];
            }
            if ($gap >= 9) {
                break;
            }
        }
        [$x, $y] = $best ?? [60.0, 72.0];
        $taken[] = [$x, $y];
        $row['x'] = $x;
        $row['y'] = $y;
        $row['scale'] = riverScale($y);
        $row['speed'] = mt_rand(30, 80) / 100;        // ความเร็วลอย (เปอร์เซ็นต์ความกว้างภาพ ต่อวินาที)
        $row['heading'] = mt_rand(0, 628) / 100;      // ทิศเริ่มต้น (เรเดียน)
        $row['bob'] = mt_rand(36, 62) / 10;
    }
    unset($row);
    mt_srand();
    return $rows;
}
