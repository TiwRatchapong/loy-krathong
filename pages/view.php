<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/svg.php';

$id = (int) ($_GET['id'] ?? 0);
$isNew = isset($_GET['new']);
$krathong = null;
$error = '';
try {
    if ($id > 0) {
        $rows = loadKrathongs(getDatabase(), 'k.id = :id', [':id' => $id], 1);
        $krathong = $rows[0] ?? null;
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'ไม่สามารถโหลดข้อมูลกระทงได้ในขณะนี้';
}
if ($krathong === null && $error === '') {
    http_response_code(404);
}

renderPageTop($krathong ? 'กระทงของ ' . $krathong['nickname'] : 'ไม่พบกระทง');
?>
    <main class="create-shell">
        <?php if ($krathong): ?>
            <section class="view-panel" data-krathong-view data-animate="<?= $isNew ? 1 : 0 ?>">
                <p class="section-kicker"><?= $isNew ? 'YOUR WISH IS SAVED' : 'A WISH ON THE WATER' ?></p>
                <h1 class="view-title"><?= $isNew ? 'กระทงของคุณลอยแล้ว' : 'กระทงของ ' . e($krathong['nickname']) ?></h1>
                <div class="release" id="releaseStage" aria-label="ภาพกระทงลอยบนสายน้ำ">
                    <div class="rel-sky" id="relSky" aria-hidden="true"></div>
                    <div class="rel-water" aria-hidden="true"><div class="wave wave-1"></div><div class="wave wave-2"></div></div>
                    <div class="rel-ripples" aria-hidden="true"><i></i><i></i><i></i></div>
                    <div class="rel-splash" id="relSplash" aria-hidden="true"></div>
                    <div class="rel-boat"><div class="rel-float">
                        <?= renderKrathongArt($krathong['krathong_key'], $krathong['flower_keys'], 'กระทงของ ' . $krathong['nickname'], 'krathong-art view-art') ?>
                    </div></div>
                </div>
                <p class="view-from reveal">จาก <strong><?= e($krathong['nickname']) ?></strong></p>
                <blockquote class="view-prayer reveal"><?= nl2br(e($krathong['prayer'])) ?></blockquote>
                <div class="success-actions reveal">
                    <button type="button" class="button button-primary" id="replayRelease">ปล่อยอีกครั้ง <span>↻</span></button>
                    <button type="button" class="button button-ghost" id="copyLink" data-url="view.php?id=<?= (int) $krathong['id'] ?>">คัดลอกลิงก์แชร์ <span>⧉</span></button>
                    <a class="button button-ghost" href="river.php">ไปที่แม่น้ำของทุกคน</a>
                    <a class="button button-ghost" href="create.php">สร้างกระทงอีกใบ</a>
                </div>
                <p class="view-copied" id="copiedNote" role="status" hidden>คัดลอกลิงก์แล้ว</p>
            </section>
        <?php else: ?>
            <section class="success-panel">
                <h2><?= $error !== '' ? e($error) : 'ไม่พบกระทงใบนี้' ?></h2>
                <p>ลิงก์อาจไม่ถูกต้อง หรือกระทงถูกนำออกแล้ว</p>
                <div class="success-actions"><a class="button button-primary" href="river.php">ไปที่แม่น้ำของทุกคน</a><a class="button button-ghost" href="create.php">สร้างกระทง</a></div>
            </section>
        <?php endif; ?>
    </main>
<?php renderPageBottom('view.js'); ?>
