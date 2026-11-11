<?php
require_once __DIR__ . '/includes/svg.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'ลอยกระทงออนไลน์ | คืนมหัศจรรย์ริมสายน้ำ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102d25">
    <meta name="description" content="ร่วมลอยกระทงออนไลน์ สร้างกระทง เลือกดอกไม้ และส่งคำอธิษฐานไปกับสายน้ำแห่งความสุข">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?php renderMetaTags('', ['title' => $pageTitle, 'path' => '']); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="scene" aria-hidden="true"></div>
    <div class="scene-shade" aria-hidden="true"></div>
    <div class="fireflies" id="fireflies" aria-hidden="true"></div>

    <header class="site-header">
        <a class="brand" href="index.php" aria-label="ลอยกระทงออนไลน์ หน้าแรก">
            <span class="brand-mark"><?= svgLotusIcon() ?></span>
            <span>ลอยกระทง<span class="brand-light">ออนไลน์</span></span>
        </a>

        <button class="menu-toggle" id="menuToggle" aria-label="เปิดเมนู" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <nav class="main-nav" id="mainNav">
            <a class="nav-link active" href="index.php">หน้าแรก</a>
            <a class="nav-link" href="pages/create.php">สร้างกระทง</a>
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
                <div class="hero-note"><span class="note-icon">☾</span> เทศกาลแห่งความสุข เริ่มต้นได้จากตรงนี้</div>
            </div>
            <a class="scroll-hint" href="#about"><span class="scroll-line"></span> เลื่อนลงเพื่อสำรวจ</a>
        </section>

        <section class="intro" id="about">
            <div class="intro-ornament"><?= svgLotusIcon() ?></div>
            <p class="section-kicker">A LITTLE WISH, A BEAUTIFUL NIGHT</p>
            <h2>หนึ่งกระทง <span>หนึ่งคำอธิษฐาน</span></h2>
            <p class="intro-copy">เลือกกระทงในแบบที่ชอบ เติมดอกไม้ที่ใช่ เขียนคำอธิษฐานจากใจ<br class="desktop-break"> แล้วปล่อยให้กระทงน้อย ๆ ล่องลอยไปบนสายน้ำแห่งความหวัง</p>
            <a class="text-link" href="pages/create.php">เริ่มต้นสร้างความทรงจำ <span>→</span></a>
        </section>
    </main>

    <footer class="site-footer">
        <span class="footer-brand"><?= svgLotusIcon() ?> ลอยกระทงออนไลน์</span>
        <span>สร้างความสุขเล็ก ๆ ไปกับสายน้ำ · <a class="footer-link" href="pages/privacy.php">ความเป็นส่วนตัว</a></span>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>
