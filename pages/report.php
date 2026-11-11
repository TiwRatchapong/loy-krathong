<?php
declare(strict_types=1);

/**
 * รับรายงานกระทงที่ไม่เหมาะสมจากหน้าเว็บ (เรียกด้วย fetch จาก assets/js/report.js)
 * รับ POST: id, reason, detail  →  ตอบ JSON { ok: bool, message|error: string }
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/reports.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function reportJson(int $status, bool $ok, string $text): never
{
    http_response_code($status);
    echo json_encode($ok ? ['ok' => true, 'message' => $text] : ['ok' => false, 'error' => $text], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    reportJson(405, false, 'method_not_allowed');
}

// กันการส่งข้ามเว็บไซต์: ต้องมี header เฉพาะ (เว็บอื่นตั้งโดยไม่ผ่าน CORS ไม่ได้) และ Origin ต้องเป็นโดเมนเดียวกัน
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'krathong') {
    reportJson(403, false, 'คำขอไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่');
}
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $ownHost = strtolower((string) parse_url('//' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    if ($originHost === '' || $originHost !== $ownHost) {
        reportJson(403, false, 'คำขอไม่ถูกต้อง');
    }
}

// ช่องล่อบอท: ผู้ใช้จริงมองไม่เห็น ถ้ามีค่าให้ทำเป็นสำเร็จแต่ไม่บันทึก
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    reportJson(200, true, 'ขอบคุณที่แจ้ง ผู้ดูแลจะตรวจสอบโดยเร็ว');
}

try {
    $pdo = getDatabase();
    // จำกัดต่อ IP: 5 ครั้ง / 10 นาที และ 20 ครั้ง / วัน (นับทุกครั้งที่กดส่ง)
    if (!rateLimitAllow($pdo, 'report_burst', 5, 600) || !rateLimitAllow($pdo, 'report_daily', 20, 86400)) {
        reportJson(429, false, 'ส่งรายงานถี่เกินไป กรุณารอสักครู่แล้วลองใหม่');
    }
    $result = reportSubmit(
        $pdo,
        (int) ($_POST['id'] ?? 0),
        (string) ($_POST['reason'] ?? ''),
        (string) ($_POST['detail'] ?? '')
    );
} catch (Throwable $exception) {
    error_log('report: ' . $exception->getMessage());
    reportJson(500, false, 'ไม่สามารถส่งรายงานได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง');
}

switch ($result) {
    case 'ok':
        reportJson(200, true, 'ขอบคุณที่แจ้ง ผู้ดูแลจะตรวจสอบกระทงใบนี้โดยเร็ว');
    case 'duplicate':
        reportJson(200, true, 'เราได้รับรายงานกระทงใบนี้จากคุณแล้ว ขอบคุณที่ช่วยดูแล');
    case 'not_found':
        reportJson(404, false, 'ไม่พบกระทงนี้ หรือถูกนำออกแล้ว');
    case 'need_detail':
        reportJson(422, false, 'กรุณาอธิบายเหตุผลสั้น ๆ เมื่อเลือก "อื่น ๆ"');
    default:
        reportJson(422, false, 'กรุณาเลือกเหตุผลที่รายงาน');
}
