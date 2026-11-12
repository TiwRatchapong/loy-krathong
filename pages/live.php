<?php
declare(strict_types=1);

// JSON สำหรับอัปเดตหน้าเว็บแบบสด: ยอดกระทง (ทั้งหมด/วันนี้) และกระทงใหม่ที่เพิ่งลอย (id > after)
// หน้าแม่น้ำเรียกทุก ~25 วินาที จึงออกแบบให้เบา: ไม่เริ่มเซสชัน อ่านอย่างเดียว

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/svg.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const LIVE_MAX_NEW = 8;   // กระทงใหม่ที่ส่งกลับต่อครั้งสูงสุด

try {
    $pdo = getDatabase();
    $out = krathongCounts($pdo);
    $out['boats'] = [];
    $out['latest'] = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM krathongs WHERE is_hidden = 0')->fetchColumn();

    if (isset($_GET['after'])) {
        $after = max(0, (int) $_GET['after']);
        $rows = loadKrathongs($pdo, 'k.id > :after', [':after' => $after], LIVE_MAX_NEW);
        $rows = placeKrathongs(array_reverse($rows));
        foreach ($rows as $k) {
            $out['boats'][] = renderRiverKrathong($k, false);
        }
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('live: ' . $exception->getMessage());
    http_response_code(503);
    echo '{"error":"unavailable"}';
}
