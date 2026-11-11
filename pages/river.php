<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/svg.php';

const RIVER_MAX = 40;   // จำนวนกระทงของคนอื่นที่แสดงพร้อมกัน (ล่าสุดก่อน)
$mine = null;
$others = [];
$total = 0;
$error = '';
try {
    $pdo = getDatabase();
    $mineId = (int) ($_GET['mine'] ?? 0);
    if ($mineId > 0) {
        $mine = loadKrathongs($pdo, 'k.id = :id', [':id' => $mineId], 1)[0] ?? null;
    }
    $total = (int) $pdo->query('SELECT COUNT(*) FROM krathongs')->fetchColumn();
    $rows = loadKrathongs($pdo, $mine ? 'k.id <> :id' : '', $mine ? [':id' => $mine['id']] : [], RIVER_MAX);
    $taken = [];
    if ($mine) {
        // กระทงของผู้ใช้ลงน้ำที่กลางแม่น้ำ (อยู่ในขอบเขตเสมอ) ขนาดเพิ่มอีกนิดให้เด่น
        $mine = placeKrathongs([$mine])[0];
        $mine['x'] = 52.0; $mine['y'] = 68.0;
        $mine['scale'] = round(riverScale(68.0) * 1.2, 3);
        $mine['speed'] = .45; $mine['heading'] = 3.4; $mine['bob'] = 4.6;
        $taken[] = [52.0, 68.0];
    }
    $others = placeKrathongs($rows, $taken);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'ไม่สามารถโหลดแม่น้ำได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง';
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
        . '<span class="rk-tag">' . ($isMine ? '✦ กระทงของคุณ' : e($k['nickname'])) . '</span></span></button>';
}

renderPageTop('แม่น้ำของทุกคน', 'river-page', 'river');
?>
    <div class="river-view" id="riverView">
        <div class="river-world" id="riverWorld" data-mine="<?= $mine ? 1 : 0 ?>"
             data-poly="<?= e((string) json_encode(riverPolygon())) ?>" data-ratio="<?= RIVER_RATIO ?>"
             data-y-far="<?= RIVER_Y_FAR ?>" data-y-near="<?= RIVER_Y_NEAR ?>" data-s-far="<?= RIVER_SCALE_FAR ?>" data-s-near="<?= RIVER_SCALE_NEAR ?>">
            <div class="river-glint" aria-hidden="true"></div>
            <div class="river-sky" id="riverSky" aria-hidden="true"></div>
            <?php foreach ($others as $k) { echo renderRiverKrathong($k, false); } ?>
            <?php if ($mine) { echo renderRiverKrathong($mine, true); } ?>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="river-toast river-toast-show"><strong><?= e($error) ?></strong></div>
    <?php elseif ($mine): ?>
        <div class="river-toast" id="riverToast" role="status">
            <strong>กระทงของคุณลอยลงแม่น้ำแล้ว</strong>
            <span>กดที่กระทงของคนอื่นเพื่ออ่านคำอธิษฐาน · มีกระทงลอยอยู่ <?= number_format($total) ?> ใบ</span>
            <div class="river-toast-actions">
                <a class="button button-primary" href="view.php?id=<?= (int) $mine['id'] ?>">ดูและแชร์กระทงของฉัน</a>
                <a class="button button-ghost" href="create.php">สร้างอีกใบ</a>
            </div>
        </div>
    <?php else: ?>
        <div class="river-toast river-toast-show" id="riverToast">
            <strong>แม่น้ำของทุกคน · <?= number_format($total) ?> กระทง</strong>
            <span><?= $others ? 'กดที่กระทงเพื่ออ่านคำอธิษฐาน' : 'ยังไม่มีกระทงลอยอยู่ มาเป็นคนแรกกันเถอะ' ?></span>
            <div class="river-toast-actions"><a class="button button-primary" href="create.php">ลอยกระทงของฉัน ✧</a><a class="button button-ghost" href="gallery.php">มุมมองรายการ</a></div>
        </div>
    <?php endif; ?>

    <dialog class="wish-dialog" id="wishDialog" aria-labelledby="wishName">
        <form method="dialog"><button class="wish-close" aria-label="ปิด">×</button></form>
        <p class="section-kicker">A WISH FROM</p>
        <h2 id="wishName"></h2>
        <blockquote id="wishText"></blockquote>
        <a class="button button-ghost" id="wishLink" href="#">เปิดหน้ากระทง</a>
    </dialog>
    <script src="../assets/js/script.js"></script>
    <script src="../assets/js/river.js"></script>
</body>
</html>
