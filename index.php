<?php
require_once __DIR__ . '/includes/svg.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
$counts = ['total' => 0, 'today' => 0];
try {
    $counts = krathongCounts(getDatabase());
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}

// กระทงลอยประดับหน้าแรก (ไม่มีข้อมูล ไม่กดได้) ใช้แบบกระทง/ชุดดอกไม้จริงของระบบ
$heroBoats = [];
try {
    $flowerSets = loadFlowerSets(getDatabase());
} catch (Throwable $exception) {
    $flowerSets = [];
}
$ktKeys = svgKrathongKeys();
$flKeys = svgFlowerKeys();
for ($i = 0; $i < 18; $i++) {
    $set = $flowerSets ? $flowerSets[array_rand($flowerSets)]['keys'] : [];
    if (count($set) !== 5) {
        $set = [];
        for ($f = 0; $f < 5; $f++) {
            $set[] = $flKeys[array_rand($flKeys)];
        }
    }
    $heroBoats[] = [
        'key' => $ktKeys[array_rand($ktKeys)],
        'flowers' => $set,
        'bob' => round(4 + mt_rand(0, 25) / 10, 1),
    ];
}
$pageTitle = 'ลอยกระทงออนไลน์ | คืนมหัศจรรย์ริมสายน้ำ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#102d25">
    <meta name="description" content="ร่วมลอยกระทงออนไลน์ สร้างกระทง เลือกดอกไม้ และส่งคำอธิษฐานไปกับสายน้ำแห่งความสุข">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?php renderMetaTags('', ['title' => $pageTitle, 'path' => '']); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="assets/images/backgrounds/river-lantern-night.webp" type="image/webp">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/css/style.css', ''), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php renderSvgSprite(); ?>
    <div class="scene" aria-hidden="true"></div>
    <div class="scene-shade" aria-hidden="true"></div>
    <div class="fireflies" id="fireflies" aria-hidden="true"></div>
    <div class="hero-river" id="heroRiver" aria-hidden="true"
         data-poly="<?= htmlspecialchars((string) json_encode(riverPolygon()), ENT_QUOTES, 'UTF-8') ?>" data-ratio="<?= RIVER_RATIO ?>"
         data-y-far="<?= RIVER_Y_FAR ?>" data-y-near="<?= RIVER_Y_NEAR ?>" data-s-far="<?= RIVER_SCALE_FAR ?>" data-s-near="<?= RIVER_SCALE_NEAR ?>">
        <?php foreach ($heroBoats as $b): ?>
        <span class="hf" style="--bob:<?= $b['bob'] ?>s"><span class="hf-bob"><?= renderKrathongArt($b['key'], $b['flowers'], '', 'krathong-art river-art') ?></span></span>
        <?php endforeach; ?>
    </div>

    <header class="site-header">
        <a class="brand" href="index.php" aria-label="ลอยกระทงออนไลน์ หน้าแรก">
            <span class="brand-mark"><?= svgLotusIcon() ?></span>
            <span>ลอยกระทง<span class="brand-light">ออนไลน์</span></span>
            <span class="brand-sep" aria-hidden="true"></span>
            <img class="brand-partner" src="assets/images/ch5-emblem.png" alt="TV5HD Online" width="38" height="36">
        </a>

        <button class="menu-toggle" id="menuToggle" aria-label="เปิดเมนู" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <nav class="main-nav" id="mainNav">
            <a class="nav-link active" href="index.php">หน้าแรก</a>
            <a class="nav-link" href="pages/river.php">แม่น้ำของทุกคน</a>
            <a class="nav-cta" href="pages/create.php">เริ่มลอยกระทง <span>↗</span></a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-content">
                <div class="eyebrow"><span class="eyebrow-line"></span> A MAGICAL THAI FESTIVAL <span class="eyebrow-line"></span></div>
                <h1>คืนมหัศจรรย์<br><span>แห่งสายน้ำ</span></h1>
                <p class="hero-description">ปล่อยความทุกข์ไปกับสายน้ำ<br class="mobile-break"> แล้วฝากคำอธิษฐานไว้กับแสงจันทร์</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="pages/create.php">สร้างกระทงของฉัน <span class="button-icon">✧</span></a>
                    <a class="button button-ghost" href="pages/river.php">ชมกระทงทั้งหมด <span class="arrow">→</span></a>
                </div>
                <?= renderKrathongStats($counts, 'kt-stats-hero', 'pages/live.php') ?>
                <div class="hero-note"><span class="note-icon">☾</span> เทศกาลแห่งความสุข เริ่มต้นได้จากตรงนี้</div>
            </div>
        </section>

    </main>

    <footer class="site-footer" style="background: transparent;">
        <span class="footer-brand"><?= svgLotusIcon() ?> ลอยกระทงออนไลน์</span>
        <span>สร้างความสุขเล็ก ๆ ไปกับสายน้ำ · <a class="footer-link" href="pages/privacy.php">ความเป็นส่วนตัว</a></span>
    </footer>

    <script src="<?= htmlspecialchars(asset('assets/js/script.js', ''), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(asset('assets/js/audio.js', ''), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(asset('assets/js/home-river.js', ''), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
