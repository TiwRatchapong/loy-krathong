<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/svg.php';
require_once __DIR__ . '/../includes/profanity.php';

startSecureSession();

$prayerOptions = [
    'ขอให้ตัวฉันและครอบครัวมีความสุข สุขภาพแข็งแรง และพบเจอแต่สิ่งดี ๆ',
    'ขอให้สิ่งที่ตั้งใจไว้สำเร็จลุล่วง และมีพลังในการก้าวผ่านทุกอุปสรรค',
    'ขอให้การเรียนและการทำงานราบรื่น เติบโตในเส้นทางที่เลือก',
    'ขอให้ปล่อยวางความทุกข์ ความกังวล และเริ่มต้นสิ่งดี ๆ อีกครั้ง',
    'ขอให้พบเจอความรักที่ดี มิตรภาพที่อบอุ่น และผู้คนที่จริงใจ',
    'ขอให้มีโอกาสใหม่ ๆ และความสุขเข้ามาในชีวิต',
];
$nickname = '';
$prayerType = 'preset';
$prayer = $prayerOptions[0];
$selectedFlowerSetId = 0;
$selectedKrathongId = 0;
$errors = [];
$success = false;
$createdId = null;
$flowerSets = [];
$krathongTypes = [];

try {
    $pdo = getDatabase();
    $krathongTypes = $pdo->query("SELECT id, name, description, svg_key FROM krathong_types WHERE is_active = 1 AND svg_key <> '' ORDER BY sort_order, id")->fetchAll();
    $flowerSets = loadFlowerSets($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nickname = trim((string) ($_POST['nickname'] ?? ''));
        $selectedKrathongId = (int) ($_POST['krathong_type_id'] ?? 0);
        $prayerType = (string) ($_POST['prayer_type'] ?? 'preset');
        $prayer = trim((string) ($_POST['prayer'] ?? ''));
        $selectedFlowerSetId = (int) ($_POST['flower_set_id'] ?? 0);

        // ช่องล่อบอท (honeypot): ผู้ใช้จริงมองไม่เห็นและไม่กรอก ถ้ามีค่าแสดงว่าเป็นสคริปต์
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect('river.php');
        }
        // จำกัดจำนวนกระทงต่อ IP: 8 ใบ / 10 นาที และ 30 ใบ / วัน (นับทุกครั้งที่กดส่ง)
        if (!rateLimitAllow($pdo, 'create_burst', 8, 600) || !rateLimitAllow($pdo, 'create_daily', 30, 86400)) {
            $errors[] = 'ส่งกระทงถี่เกินไป กรุณารอสักครู่แล้วลองใหม่อีกครั้ง';
        }
        if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
            $errors[] = 'เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองอีกครั้ง';
        }
        if ($nickname === '' || mb_strlen($nickname) > 30) {
            $errors[] = 'กรุณากรอกชื่อเล่นไม่เกิน 30 ตัวอักษร';
        } elseif (containsProfanity($nickname)) {
            $errors[] = 'ชื่อเล่นมีคำที่ไม่เหมาะสม กรุณาเปลี่ยนชื่อ';
        }
        $validKrathongIds = array_map('intval', array_column($krathongTypes, 'id'));
        if (!in_array($selectedKrathongId, $validKrathongIds, true)) {
            $errors[] = 'กรุณาเลือกกระทงที่ต้องการ';
        }
        if (!in_array($prayerType, ['preset', 'custom'], true)) {
            $errors[] = 'กรุณาเลือกประเภทคำอธิษฐานให้ถูกต้อง';
        }
        if ($prayerType === 'preset' && !in_array($prayer, $prayerOptions, true)) {
            $errors[] = 'กรุณาเลือกคำอธิษฐานจากรายการ';
        }
        if ($prayerType === 'custom' && ($prayer === '' || mb_strlen($prayer) > 300)) {
            $errors[] = 'กรุณาเขียนคำอธิษฐาน 1–300 ตัวอักษร';
        } elseif ($prayerType === 'custom' && containsProfanity($prayer)) {
            $errors[] = 'คำอธิษฐานมีคำที่ไม่เหมาะสม กรุณาแก้ไขข้อความ';
        }

        $selectedSet = null;
        foreach ($flowerSets as $set) {
            if ((int) $set['id'] === $selectedFlowerSetId) {
                $selectedSet = $set;
                break;
            }
        }
        if ($selectedSet === null) {
            $errors[] = 'กรุณาเลือกชุดดอกไม้';
        }

        if (!$errors) {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('INSERT INTO krathongs (nickname, krathong_type_id, flower_set_id, prayer_type, prayer, owner_token_hash) VALUES (:nickname, :type_id, :set_id, :prayer_type, :prayer, :owner)');
            $statement->execute([':nickname' => $nickname, ':type_id' => $selectedKrathongId, ':set_id' => $selectedFlowerSetId, ':prayer_type' => $prayerType, ':prayer' => $prayer, ':owner' => ownerHash(ensureOwnerToken())]);   // ผูกกระทงกับเบราว์เซอร์นี้
            $createdId = (int) $pdo->lastInsertId();
            $flowerStatement = $pdo->prepare('INSERT INTO krathong_flowers (krathong_id, flower_id, quantity) VALUES (:krathong_id, :flower_id, :quantity)');
            foreach ($selectedSet['items'] as $item) {
                $flowerStatement->execute([':krathong_id' => $createdId, ':flower_id' => (int) $item['flower_id'], ':quantity' => (int) $item['quantity']]);
            }
            $pdo->commit();
            $success = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            // พาไปดูกระทงที่เพิ่งสร้าง (กันการกดรีเฟรชแล้วส่งฟอร์มซ้ำ)
            redirect('river.php?mine=' . $createdId);
        }
    }
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#102d25">
    <title>สร้างกระทง | ลอยกระทงออนไลน์</title>
    <?php renderMetaTags('../', ['title' => 'สร้างกระทง | ลอยกระทงออนไลน์', 'path' => 'pages/create.php']); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="../assets/images/backgrounds/river-lantern-night.webp" type="image/webp">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body class="create-page">
    <?php renderSvgSprite(); ?>
    <div class="scene" aria-hidden="true"></div><div class="scene-shade" aria-hidden="true"></div>
    <?php renderSiteHeader('create'); ?>

    <main class="create-shell">
        <div class="create-heading">
            <p class="section-kicker">CREATE YOUR OWN KRATHONG</p>
            <h1>สร้างกระทง<span>ของคุณ</span></h1>
            <p>ค่อย ๆ เลือกสิ่งที่ชอบ แล้วส่งคำอธิษฐานไปกับสายน้ำ</p>
        </div>

        <?php if ($success): ?>
            <section class="success-panel" role="status">
                <div class="success-symbol">✧</div><p class="section-kicker">YOUR WISH IS SAVED</p>
                <h2>บันทึกกระทงเรียบร้อยแล้ว</h2>
                <p>คำอธิษฐานของคุณถูกเก็บไว้แล้ว ขอบคุณที่ร่วมเป็นส่วนหนึ่งของคืนแห่งความสุข</p>
                <div class="success-actions"><a class="button button-primary" href="../index.php">กลับสู่หน้าแรก <span>↗</span></a><a class="button button-ghost" href="create.php">สร้างกระทงอีกใบ</a></div>
            </section>
        <?php else: ?>
            <?php if ($errors): ?><div class="form-alert" role="alert"><strong>ยังไม่สามารถบันทึกได้</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form class="create-layout wizard-layout" method="post" action="create.php" id="krathongWizard" data-check-url="check_text.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="prayer" id="prayerValue" value="<?= e($prayer) ?>">
                <div class="hp-field" aria-hidden="true"><label>เว็บไซต์ (ไม่ต้องกรอก)<input type="text" name="website" tabindex="-1" autocomplete="off" value=""></label></div>
                <div class="form-panel wizard-panel">
                    <div class="wizard-progress" aria-label="ความคืบหน้าการสร้างกระทง">
                        <div class="wizard-progress-top"><span id="stepLabel">ขั้นตอนที่ 1 จาก 5</span><span id="stepPercent">20%</span></div>
                        <div class="wizard-track"><span id="stepProgress" style="width:20%"></span></div>
                        <div class="wizard-steps"><span class="active">ชื่อ</span><span>กระทง</span><span>ดอกไม้</span><span>คำอธิษฐาน</span><span>ตรวจสอบ</span></div>
                    </div>

                    <section class="wizard-step active" data-step="0">
                        <div class="step-heading"><span>01</span><div><h2>ก่อนอื่น ขอทราบชื่อคุณ</h2><p>ชื่อเล่นที่จะแสดงในกระทงของคุณ</p></div></div>
                        <label class="field-label" for="nickname">ชื่อเล่น <span>ไม่เกิน 30 ตัวอักษร</span></label>
                        <input class="text-field" id="nickname" name="nickname" type="text" maxlength="30" placeholder="เขียนชื่อของคุณ.." value="<?= e($nickname) ?>" autocomplete="nickname" required>
                        <p class="field-error" id="nicknameError" role="alert" hidden></p>
                        <p class="wizard-hint">ไม่จำเป็นต้องใช้ชื่อจริงก็ได้นะ</p>
                    </section>

                    <section class="wizard-step" data-step="1" hidden>
                        <div class="step-heading"><span>02</span><div><h2>เลือกกระทงที่คุณชอบ</h2><p>มีให้เลือก <?= count($krathongTypes) ?> แบบ เลือกได้ 1 แบบ</p></div></div>
                        <div class="krathong-grid">
                            <?php foreach ($krathongTypes as $type): $checked = $selectedKrathongId === (int) $type['id']; ?>
                                <label class="krathong-option <?= $checked ? 'selected' : '' ?>" data-svg="<?= e($type['svg_key']) ?>" data-name="<?= e($type['name']) ?>">
                                    <input type="radio" name="krathong_type_id" value="<?= (int) $type['id'] ?>" <?= $checked ? 'checked' : '' ?> required>
                                    <span class="catalog-visual krathong-visual"><svg viewBox="0 0 200 130" aria-hidden="true"><use href="#kt-<?= e($type['svg_key']) ?>"/></svg></span>
                                    <span class="catalog-name"><?= e($type['name']) ?></span>
                                    <span class="catalog-description"><?= e($type['description']) ?></span>
                                    <span class="catalog-selected">✓</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="wizard-step" data-step="2" hidden>
                        <div class="step-heading"><span>03</span><div><h2>เลือกชุดดอกไม้ประดับกระทง</h2><p>มีให้เลือก <?= count($flowerSets) ?> ชุด แต่ละชุดจัดดอกไม้ครบ 5 ดอกให้แล้ว</p></div></div>
                        <div class="krathong-grid flower-set-grid">
                            <?php foreach ($flowerSets as $set): $checked = $selectedFlowerSetId === $set['id']; ?>
                                <label class="krathong-option flower-set-option <?= $checked ? 'selected' : '' ?>" data-name="<?= e($set['name']) ?> · <?= e($set['theme']) ?>" data-flowers="<?= e(implode(',', $set['keys'])) ?>" data-summary="<?= e(implode(' · ', $set['flower_names'])) ?>">
                                    <input type="radio" name="flower_set_id" value="<?= $set['id'] ?>" <?= $checked ? 'checked' : '' ?> required>
                                    <span class="catalog-visual set-visual"><svg viewBox="0 0 250 64" aria-hidden="true"><?php foreach ([3, 1, 0, 2, 4] as $pos => $slot): ?><use href="#fl-<?= e($set['keys'][$slot]) ?>" x="<?= $pos * 50 ?>" y="0" width="50" height="64"/><?php endforeach; ?></svg></span>
                                    <span class="catalog-name"><?= e($set['name']) ?> <span class="set-theme">· <?= e($set['theme']) ?></span></span>
                                    <span class="set-flowers"><?= e(implode(' · ', $set['flower_names'])) ?></span>
                                    <span class="catalog-description"><?= e($set['description']) ?></span>
                                    <span class="catalog-selected">✓</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="wizard-step" data-step="3" hidden>
                        <div class="step-heading"><span>04</span><div><h2>ส่งคำอธิษฐานของคุณ</h2><p>เลือกข้อความที่ชอบ หรือเขียนคำอธิษฐานด้วยตัวเอง</p></div></div>
                        <div class="prayer-tabs"><label><input type="radio" name="prayer_type" value="preset" <?= $prayerType === 'preset' ? 'checked' : '' ?>><span>เลือกคำอธิษฐาน</span></label><label><input type="radio" name="prayer_type" value="custom" <?= $prayerType === 'custom' ? 'checked' : '' ?>><span>เขียนเอง</span></label></div>
                        <div class="prayer-presets" id="prayerPresets">
                            <?php foreach ($prayerOptions as $option): ?><label class="prayer-option"><input type="radio" name="preset_prayer" value="<?= e($option) ?>" <?= $prayer === $option ? 'checked' : '' ?>><span><?= e($option) ?></span></label><?php endforeach; ?>
                        </div>
                        <textarea class="text-field prayer-custom" id="customPrayer" maxlength="300" rows="4" placeholder="เขียนคำอธิษฐานของคุณที่นี่..." hidden><?= e($prayerType === 'custom' ? $prayer : '') ?></textarea>
                        <p class="field-error" id="prayerError" role="alert" hidden></p>
                        <p class="char-count" id="charCount">0 / 300</p>
                    </section>

                    <section class="wizard-step" data-step="4" hidden>
                        <div class="step-heading"><span>05</span><div><h2>ตรวจสอบกระทงของคุณ</h2><p>ทุกอย่างเรียบร้อยแล้ว กดลอยกระทงได้เลย</p></div></div>
                        <div class="review-card">
                            <div class="review-row"><span>ชื่อผู้ลอย</span><strong id="reviewNickname">—</strong></div>
                            <div class="review-row"><span>รูปแบบกระทง</span><strong id="reviewKrathong">—</strong></div>
                            <div class="review-row review-flower-row"><span>ชุดดอกไม้ (5 ดอก)</span><strong id="reviewFlowers">—</strong></div>
                            <div class="review-prayer"><span>คำอธิษฐาน</span><p id="reviewPrayer">—</p></div>
                            <p class="review-notice">ชื่อเล่นและคำอธิษฐานจะแสดงให้ผู้เข้าชมทุกคนเห็นบนแม่น้ำและหน้าแกลเลอรี ไม่ควรใส่ข้อมูลส่วนตัว เช่น เบอร์โทรหรือที่อยู่ · <a class="footer-link" href="privacy.php" target="_blank" rel="noopener">ความเป็นส่วนตัว</a></p>
                        </div>
                        <p class="wizard-hint">เมื่อกดยืนยัน ระบบจะบันทึกกระทงและคำอธิษฐานของคุณ</p>
                    </section>

                    <div class="wizard-actions">
                        <button class="button button-ghost" type="button" id="prevStep" hidden>ย้อนกลับ</button>
                        <span class="wizard-spacer"></span>
                        <button class="button button-primary" type="button" id="nextStep">ถัดไป <span>→</span></button>
                        <button class="button button-primary" type="submit" id="submitKrathong" hidden>ลอยกระทง <span>✧</span></button>
                    </div>
                </div>

                <aside class="preview-panel wizard-preview">
                    <p class="section-kicker">YOUR KRATHONG</p><h2>ตัวอย่างกระทงของคุณ</h2>
                    <div class="krathong-preview" aria-live="polite">
                        <svg class="preview-svg" id="previewSvg" viewBox="0 0 240 200" role="img" aria-label="ตัวอย่างกระทงของคุณ">
                            <ellipse cx="120" cy="104" rx="112" ry="72" fill="url(#gradFlame)" opacity=".45"/>
                            <g id="pvBase"></g>
                            <g class="preview-candle" aria-hidden="true">
                                <path d="M106 134 L93 66" stroke="#7a4a22" stroke-width="2.2" stroke-linecap="round"/><circle cx="93" cy="66" r="2" fill="#ff5a36"/>
                                <path d="M134 134 L148 62" stroke="#7a4a22" stroke-width="2.2" stroke-linecap="round"/><circle cx="148" cy="62" r="2" fill="#ff5a36"/>
                                <circle cx="120" cy="72" r="20" fill="url(#gradFlame)"/>
                                <rect x="113.5" y="84" width="13" height="50" rx="2" fill="url(#gradCandle)" stroke="#d9bd7c" stroke-width=".8"/>
                                <path d="M120 84 L120 78" stroke="#4a3320" stroke-width="1.4"/>
                                <path d="M120 80 C112 72 119 64 120 55 C121 64 128 72 120 80Z" fill="#ffae2b"/>
                                <path d="M120 78 C116.5 73 119.5 69 120 64 C120.5 69 123.5 73 120 78Z" fill="#fff3b0"/>
                            </g>
                            <g id="pvFlowers"></g>
                        </svg>
                    </div>
                    <p class="preview-krathong-name" id="previewKrathong">ยังไม่ได้เลือกกระทง</p>
                    <p class="preview-nickname" id="previewNickname">ชื่อของคุณ</p>
                    <p class="preview-prayer" id="previewPrayer">คำอธิษฐานของคุณจะปรากฏที่นี่</p>
                    <div class="preview-note"><span>☾</span> ทุกคำอธิษฐานมีความหมาย</div>
                </aside>
            </form>
        <?php endif; ?>
    </main>
    <footer class="site-footer"><span class="footer-brand"><?= svgLotusIcon() ?> ลอยกระทงออนไลน์</span><span>ขอให้ค่ำคืนนี้เต็มไปด้วยความสุข</span></footer>
    <script src="<?= e(asset('assets/js/script.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/audio.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/create.js')) ?>"></script>
</body>
</html>
