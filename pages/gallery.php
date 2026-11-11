<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/svg.php';

const GALLERY_PER_PAGE = 24;
$page = max(1, (int) ($_GET['page'] ?? 1));
$krathongs = [];
$total = 0;
$error = '';
try {
    $pdo = getDatabase();
    $total = (int) $pdo->query('SELECT COUNT(*) FROM krathongs WHERE is_hidden = 0')->fetchColumn();
    $pages = max(1, (int) ceil($total / GALLERY_PER_PAGE));
    $page = min($page, $pages);
    $krathongs = loadKrathongs($pdo, '', [], GALLERY_PER_PAGE, ($page - 1) * GALLERY_PER_PAGE);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'ไม่สามารถโหลดกระทงได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง';
    $pages = 1;
}

renderPageTop('กระทงของทุกคน', 'create-page gallery-page');
?>
    <main class="create-shell gallery-shell">
        <div class="create-heading">
            <p class="section-kicker">COMMUNITY GALLERY</p>
            <h1>กระทงของ<span>ทุกคน</span></h1>
            <p>กดที่กระทงเพื่ออ่านคำอธิษฐาน · ทั้งหมด <?= number_format($total) ?> ใบ</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="form-alert" role="alert"><strong><?= e($error) ?></strong></div>
        <?php elseif (!$krathongs): ?>
            <section class="success-panel">
                <h2>ยังไม่มีกระทงลอยอยู่</h2>
                <p>มาเป็นคนแรกที่ปล่อยกระทงลงสายน้ำกันเถอะ</p>
                <div class="success-actions"><a class="button button-primary" href="create.php">สร้างกระทง <span>↗</span></a></div>
            </section>
        <?php else: ?>
            <ul class="gallery-grid">
                <?php foreach ($krathongs as $i => $k): ?>
                    <li>
                        <button type="button" class="gallery-item" style="--bob-delay:<?= -($i % 7) * 0.7 ?>s;--bob-time:<?= 5 + ($i % 4) ?>s"
                                data-id="<?= (int) $k['id'] ?>" data-name="<?= e($k['nickname']) ?>" data-prayer="<?= e($k['prayer']) ?>"
                                aria-label="กระทงของ <?= e($k['nickname']) ?>">
                            <?= renderKrathongArt($k['krathong_key'], $k['flower_keys'], 'กระทงของ ' . $k['nickname'], 'krathong-art gallery-art') ?>
                            <span class="gallery-name"><?= e($k['nickname']) ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($pages > 1): ?>
                <nav class="gallery-pager" aria-label="เปลี่ยนหน้า">
                    <?php if ($page > 1): ?><a class="button button-ghost" href="gallery.php?page=<?= $page - 1 ?>">← ก่อนหน้า</a><?php endif; ?>
                    <span>หน้า <?= $page ?> / <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a class="button button-ghost" href="gallery.php?page=<?= $page + 1 ?>">ถัดไป →</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <dialog class="wish-dialog" id="wishDialog" aria-labelledby="wishName">
        <form method="dialog"><button class="wish-close" aria-label="ปิด">×</button></form>
        <p class="section-kicker">A WISH FROM</p>
        <h2 id="wishName"></h2>
        <blockquote id="wishText"></blockquote>
        <a class="button button-ghost" id="wishLink" href="#">เปิดหน้ากระทง</a>
    </dialog>
<?php renderPageBottom('gallery.js'); ?>
