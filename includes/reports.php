<?php
declare(strict_types=1);

/**
 * ระบบรายงานกระทงที่ไม่เหมาะสม (ใช้ร่วมกันระหว่างหน้าเว็บ pages/report.php และหน้าผู้ดูแล)
 * ตาราง krathong_reports ถูกสร้างใน config/database.php (migrateDatabase)
 */

const REPORT_REASONS = [
    'offensive' => 'ภาษาหยาบคายหรือไม่สุภาพ',
    'hate' => 'เหยียดหยาม กลั่นแกล้ง หรือคุกคามผู้อื่น',
    'sexual' => 'เนื้อหาทางเพศ',
    'personal' => 'เปิดเผยข้อมูลส่วนตัว (เบอร์โทร ที่อยู่ ลิงก์)',
    'spam' => 'สแปมหรือโฆษณา',
    'other' => 'อื่น ๆ',
];
const REPORT_DETAIL_MAX = 200;

/**
 * บันทึกรายงาน 1 รายการ
 *
 * @return string ok | duplicate | not_found | invalid | need_detail
 *   duplicate = IP เดิม (hash) เคยรายงานกระทงใบนี้แล้ว ไม่บันทึกซ้ำ
 */
function reportSubmit(PDO $pdo, int $krathongId, string $reason, string $detail): string
{
    if ($krathongId < 1 || !isset(REPORT_REASONS[$reason])) {
        return 'invalid';
    }
    $detail = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $detail)), 0, REPORT_DETAIL_MAX);
    if ($reason === 'other' && mb_strlen($detail) < 3) {
        return 'need_detail';
    }

    // รายงานได้เฉพาะกระทงที่ยังแสดงอยู่ (ที่ซ่อนแล้วผู้ใช้ทั่วไปเข้าไม่ถึงอยู่แล้ว)
    $exists = $pdo->prepare('SELECT 1 FROM krathongs WHERE id = :id AND is_hidden = 0');
    $exists->execute([':id' => $krathongId]);
    if (!$exists->fetchColumn()) {
        return 'not_found';
    }

    // เก็บเฉพาะ hash ของ IP เพื่อกันรายงานซ้ำจากคนเดิม (unique: krathong_id + ip_hash)
    $ipHash = substr(hash('sha256', 'krathong-report|' . clientIp()), 0, 16);
    $insert = $pdo->prepare('INSERT OR IGNORE INTO krathong_reports (krathong_id, reason, detail, ip_hash) VALUES (:k, :r, :d, :ip)');
    $insert->execute([':k' => $krathongId, ':r' => $reason, ':d' => $detail, ':ip' => $ipHash]);
    return $insert->rowCount() > 0 ? 'ok' : 'duplicate';
}

/** หน้าต่างรายงาน (dialog) ที่ใช้ร่วมกันในหน้าแม่น้ำ / แกลเลอรี / หน้าแชร์ — เปิดด้วย assets/js/report.js */
function renderReportDialog(string $endpoint = 'report.php'): void
{
    ?>
    <dialog class="wish-dialog report-dialog" id="reportDialog" aria-labelledby="reportTitle" data-endpoint="<?= e($endpoint) ?>">
        <form method="dialog"><button class="wish-close" aria-label="ปิด">×</button></form>
        <p class="section-kicker">REPORT</p>
        <h2 id="reportTitle">รายงานกระทงที่ไม่เหมาะสม</h2>

        <form id="reportForm" class="report-form" novalidate>
            <input type="hidden" name="id" value="">
            <fieldset class="report-reasons">
                <legend>เหตุผลที่รายงาน</legend>
                <?php foreach (REPORT_REASONS as $key => $label): ?>
                    <label class="report-reason"><input type="radio" name="reason" value="<?= e($key) ?>"><span><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </fieldset>
            <label class="report-label" for="reportDetail">รายละเอียดเพิ่มเติม <span id="reportDetailHint">(ไม่บังคับ)</span></label>
            <textarea id="reportDetail" name="detail" rows="3" maxlength="<?= REPORT_DETAIL_MAX ?>" placeholder="บอกเราเพิ่มเติมสั้น ๆ ว่าไม่เหมาะสมอย่างไร"></textarea>
            <small class="report-count"><span id="reportCount">0</span> / <?= REPORT_DETAIL_MAX ?></small>
            <div class="hp-field" aria-hidden="true"><label>เว็บไซต์ <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <p class="report-msg" id="reportMsg" role="alert" aria-live="polite" hidden></p>
            <div class="report-actions">
                <button type="button" class="button button-ghost" data-report-cancel>ยกเลิก</button>
                <button type="submit" class="button button-primary" id="reportSubmit">ส่งรายงาน</button>
            </div>
        </form>

        <div class="report-done" id="reportDone" hidden>
            <div class="success-symbol" aria-hidden="true">✓</div>
            <p id="reportDoneText">ขอบคุณที่ช่วยดูแลชุมชนของเรา</p>
            <button type="button" class="button button-ghost" data-report-cancel>ปิด</button>
        </div>
    </dialog>
<?php
}
