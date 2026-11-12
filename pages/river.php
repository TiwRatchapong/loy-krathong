<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/svg.php';
require_once __DIR__ . '/../includes/reports.php';

const RIVER_MAX = 40;   // จำนวนกระทงของคนอื่นที่แสดงพร้อมกัน (ล่าสุดก่อน)
// หน้านี้แสดงกระทง "ของคุณ" ตาม cookie จึงห้าม cache ร่วมกัน
header('Cache-Control: private, no-store');
$mine = null;
$returning = false;
$others = [];
$total = 0;
$counts = ['total' => 0, 'today' => 0];
$latestId = 0;
$error = '';
try {
    $pdo = getDatabase();
    // กระทงของผู้ใช้ = ใบล่าสุดที่ผูกกับ token ใน cookie ของเบราว์เซอร์นี้ (ใส่ ?mine= เองไม่ทำให้เป็นเจ้าของได้)
    // ?mine=<id> ที่ตรงกับใบของเรา = เพิ่งลอย (เล่นอนิเมชั่นปล่อยลงน้ำ) ไม่มี = กลับมาเยี่ยม กระทงลอยอยู่แล้ว
    $ownerToken = currentOwnerToken();
    if ($ownerToken !== null) {
        setOwnerCookie($ownerToken);   // ต่ออายุ 1 ปีนับจากการเข้าครั้งล่าสุด
        $found = $pdo->prepare('SELECT id FROM krathongs WHERE owner_token_hash = :h AND is_hidden = 0 ORDER BY id DESC LIMIT 1');
        $found->execute([':h' => ownerHash($ownerToken)]);
        $mineId = (int) $found->fetchColumn();
        if ($mineId > 0) {
            $mine = loadKrathongs($pdo, 'k.id = :id', [':id' => $mineId], 1)[0] ?? null;
            $returning = (int) ($_GET['mine'] ?? 0) !== $mineId;
        }
    }
    $counts = krathongCounts($pdo);
    $total = $counts['total'];
    $rows = loadKrathongs($pdo, $mine ? 'k.id <> :id' : '', $mine ? [':id' => $mine['id']] : [], RIVER_MAX);
    $taken = [];
    if ($mine) {
        // กระทงของผู้ใช้ลงน้ำที่กลางแม่น้ำ (อยู่ในขอบเขตเสมอ) ขนาดเพิ่มอีกนิดให้เด่น
        $mine = placeKrathongs([$mine])[0];
        $mine['x'] = 50.0; $mine['y'] = 84.0;
        $mine['scale'] = round(riverScale(84.0), 3);
        $mine['speed'] = .3; $mine['heading'] = 5.9; $mine['bob'] = 4.6;
        $taken[] = [50.0, 84.0, 2.4];   // ตัวหารระยะห่าง: เว้นที่ว่างรอบกระทงของเรากว้างกว่าปกติ
    }
    $others = placeKrathongs($rows, $taken);
    $latestId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM krathongs WHERE is_hidden = 0')->fetchColumn();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'ไม่สามารถโหลดแม่น้ำได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง';
}

// แถบน้ำ [บน, สูง] (% ของภาพ) — ไกล (บาง ขยับน้อย) → ใกล้ (หนา ขยับมาก)
$waterBands = [[63.3, 2.8], [66.1, 2.9], [69.0, 3.2], [72.2, 3.5], [75.7, 3.9], [79.6, 4.3], [83.9, 4.7], [88.6, 5.1], [93.7, 6.3]];

renderPageTop('แม่น้ำของทุกคน', 'river-page', 'river');
?>
    <div class="river-view" id="riverView">
        <div class="river-world" id="riverWorld" data-mine="<?= $mine ? 1 : 0 ?>" data-returning="<?= $returning ? 1 : 0 ?>" data-latest="<?= (int) $latestId ?>" data-live="live.php"
             data-poly="<?= e((string) json_encode(riverPolygon())) ?>" data-ratio="<?= RIVER_RATIO ?>" data-bg="../assets/images/backgrounds/river-lantern-night.webp" data-bands="<?= e((string) json_encode($waterBands)) ?>"
             data-y-far="<?= RIVER_Y_FAR ?>" data-y-near="<?= RIVER_Y_NEAR ?>" data-s-far="<?= RIVER_SCALE_FAR ?>" data-s-near="<?= RIVER_SCALE_NEAR ?>">
            <div class="river-stars" id="riverStars" aria-hidden="true"></div>
            <div class="river-sky" id="riverSky" aria-hidden="true"></div>
            <canvas class="river-wave" id="riverWave" aria-hidden="true"></canvas>
            <div class="river-orbs" id="riverOrbs" aria-hidden="true"></div>
            <?php foreach ($others as $k) { echo renderRiverKrathong($k, false); } ?>
            <?php if ($mine) { echo renderRiverKrathong($mine, true); } ?>
        </div>
    </div>

    <?php if ($error === ''): ?>
        <div class="river-hud" id="riverHud">
            <h1 class="river-hud-title">แม่น้ำของทุกคน</h1>
            <?= renderKrathongStats($counts, 'kt-stats-hud') ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="river-toast river-toast-show" id="riverToast" role="alert"><strong><?= e($error) ?></strong></div>
    <?php elseif ($mine): ?>
        <div class="river-toast" id="riverToast" role="status">
            <p class="river-toast-msg"><strong><?= $returning ? 'กระทงของคุณยังลอยอยู่ ✦' : 'กระทงของคุณลอยแล้ว ✦' ?></strong><span>แตะกระทงของคนอื่นเพื่ออ่านคำอธิษฐาน</span></p>
            <div class="river-toast-actions">
                <a class="button button-primary" href="view.php?id=<?= (int) $mine['id'] ?>">ดูและแชร์</a>
                <a class="button button-ghost" href="create.php">ลอยอีกใบ</a>
            </div>
        </div>
    <?php else: ?>
        <div class="river-toast river-toast-show" id="riverToast">
            <p class="river-toast-msg"><span><?= $others ? 'แตะที่กระทงเพื่ออ่านคำอธิษฐาน' : 'ยังไม่มีกระทงลอยอยู่ มาเป็นคนแรกกันเถอะ' ?></span></p>
            <div class="river-toast-actions">
                <a class="button button-primary" href="create.php">ลอยกระทงของฉัน ✧</a>
                <a class="button button-ghost" href="gallery.php">รายการ</a>
            </div>
        </div>
    <?php endif; ?>

    <dialog class="wish-dialog" id="wishDialog" aria-labelledby="wishName">
        <form method="dialog"><button class="wish-close" aria-label="ปิด">×</button></form>
        <p class="section-kicker">A WISH FROM</p>
        <h2 id="wishName"></h2>
        <blockquote id="wishText"></blockquote>
        <div class="wish-actions">
            <a class="button button-ghost" id="wishLink" href="#">เปิดหน้ากระทง</a>
            <button type="button" class="report-link" id="wishReport" data-report-open>⚑ รายงานว่าไม่เหมาะสม</button>
        </div>
    </dialog>
    <?php renderReportDialog(); ?>
    <script src="<?= e(asset('assets/js/script.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/audio.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/report.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/river.js')) ?>"></script>
</body>
</html>
