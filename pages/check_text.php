<?php
declare(strict_types=1);

/**
 * ตรวจคำไม่สุภาพแบบเรียลไทม์ให้หน้าสร้างกระทงเรียกใช้ (ใช้ตัวกรองเดียวกับตอนบันทึก)
 * รับ POST: nickname, prayer  →  ตอบ JSON { nickname: bool, prayer: bool }  (true = พบคำไม่เหมาะสม)
 * ไม่ส่งรายการคำหยาบกลับไปยังเบราว์เซอร์
 */
require_once __DIR__ . '/../includes/profanity.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$nickname = mb_substr((string) ($_POST['nickname'] ?? ''), 0, 100);
$prayer = mb_substr((string) ($_POST['prayer'] ?? ''), 0, 1000);

echo json_encode([
    'nickname' => containsProfanity($nickname),
    'prayer' => containsProfanity($prayer),
]);
