
<?php

$dbPath = __DIR__ . '/../database/database.sqlite';

// เชื่อมต่อฐานข้อมูล
$pdo = new PDO('sqlite:' . $dbPath);

// ตั้งค่าการแจ้งเตือน Error
$pdo->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

// สร้างตารางกระทง
$sql = "
CREATE TABLE IF NOT EXISTS krathongs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    wish TEXT NOT NULL,
    design TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

$pdo->exec($sql);

echo 'SQLite Connected Successfully!';

?>
