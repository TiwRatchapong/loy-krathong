<?php
declare(strict_types=1);

// เพิ่มเลขนี้ทุกครั้งที่แก้ schema.sql / seed.sql หรือโครงสร้างตาราง เพื่อให้ฐานข้อมูลเดิมถูกอัปเกรดอีกหนึ่งครั้ง
const DB_SCHEMA_VERSION = 5;

function getDatabase(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('PHP ยังไม่ได้เปิดใช้งานส่วนขยาย PDO SQLite กรุณาเปิดใช้งาน pdo_sqlite ใน php.ini');
    }

    // โฟลเดอร์ database/ เก็บ schema.sql และ seed.sql (ไฟล์ตั้งต้น)
    $databaseDirectory = dirname(__DIR__) . '/database';
    if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0775, true) && !is_dir($databaseDirectory)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์ฐานข้อมูลได้');
    }

    // แนะนำให้ตั้ง KRATHONG_DB_PATH ไปยังตำแหน่ง "นอก web root" เช่น /var/lib/krathong/database.sqlite
    // (ถ้าไม่ตั้ง จะใช้ database/database.sqlite เหมือนเดิม โดยต้องมี .htaccess/nginx rule กันไม่ให้ดาวน์โหลด)
    $envPath = getenv('KRATHONG_DB_PATH');
    $databasePath = ($envPath !== false && $envPath !== '') ? $envPath : $databaseDirectory . '/database.sqlite';
    $databaseFileDirectory = dirname($databasePath);
    if (!is_dir($databaseFileDirectory) && !mkdir($databaseFileDirectory, 0775, true) && !is_dir($databaseFileDirectory)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บไฟล์ฐานข้อมูลได้');
    }
    $pdo = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');   // รอคิวเขียนสูงสุด 5 วินาที แทนที่จะ error "database is locked"
    $pdo->exec('PRAGMA journal_mode = WAL');    // อ่านและเขียนพร้อมกันได้ ช่วยตอนคนเข้าเยอะ

    // รันสร้างตาราง/ย้ายข้อมูล/seed เฉพาะตอนที่ฐานข้อมูลยังไม่ถึงเวอร์ชันปัจจุบัน (ไม่ทำทุก request)
    $currentVersion = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($currentVersion < DB_SCHEMA_VERSION) {
        migrateDatabase($pdo, $databaseDirectory);
        $pdo->exec('PRAGMA user_version = ' . DB_SCHEMA_VERSION);
    }

    return $pdo;
}

/**
 * สร้างตาราง ย้ายฐานข้อมูลรุ่นเก่า และ seed ข้อมูล (ปลอดภัยที่จะรันซ้ำ)
 * ถ้าแก้ schema.sql / seed.sql ภายหลัง ให้เพิ่มค่า DB_SCHEMA_VERSION เพื่อให้รันอีกครั้งหนึ่ง
 */
function migrateDatabase(PDO $pdo, string $databaseDirectory): void
{
    $schemaPath = $databaseDirectory . '/schema.sql';
    if (!is_file($schemaPath)) {
        throw new RuntimeException('ไม่พบไฟล์ database/schema.sql');
    }
    $pdo->exec((string) file_get_contents($schemaPath));

    // Very early databases had krathongs(name, wish, design). Rebuild it with the current structure,
    // keeping existing rows (name → nickname, wish → prayer).
    $krathongColumns = array_column($pdo->query('PRAGMA table_info(krathongs)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('prayer', $krathongColumns, true)) {
        $hasType = in_array('krathong_type_id', $krathongColumns, true);
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->beginTransaction();
        try {
            $pdo->exec("CREATE TABLE krathongs_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nickname TEXT NOT NULL,
                krathong_type_id INTEGER,
                flower_set_id INTEGER REFERENCES flower_sets(id),
                prayer_type TEXT NOT NULL CHECK (prayer_type IN ('preset', 'custom')),
                prayer TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (krathong_type_id) REFERENCES krathong_types(id)
            )");
            $pdo->exec(
                'INSERT INTO krathongs_new (id, nickname, krathong_type_id, prayer_type, prayer, created_at) '
                . "SELECT id, name, " . ($hasType ? 'krathong_type_id' : 'NULL') . ", 'custom', wish, COALESCE(created_at, CURRENT_TIMESTAMP) FROM krathongs"
            );
            $pdo->exec('DROP TABLE krathongs');
            $pdo->exec('ALTER TABLE krathongs_new RENAME TO krathongs');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_krathongs_created_at ON krathongs(created_at DESC)');
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    // Migrate databases created by earlier versions: add any missing columns.
    $ensureColumn = static function (string $table, string $column, string $definition) use ($pdo): void {
        $names = array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array($column, $names, true)) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        }
    };
    $ensureColumn('krathongs', 'krathong_type_id', 'INTEGER REFERENCES krathong_types(id)');
    $ensureColumn('krathongs', 'flower_set_id', 'INTEGER REFERENCES flower_sets(id)');
    $ensureColumn('krathongs', 'is_hidden', 'INTEGER NOT NULL DEFAULT 0');   // 1 = ผู้ดูแลซ่อนกระทงนี้
    $ensureColumn('krathongs', 'owner_token_hash', 'TEXT');                  // SHA-256 ของ token เจ้าของ (ดู ownerHash() ใน includes/functions.php)
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_krathongs_owner ON krathongs(owner_token_hash, id DESC)');
    $ensureColumn('flowers', 'image_path', "TEXT NOT NULL DEFAULT ''");
    $ensureColumn('flowers', 'svg_key', "TEXT NOT NULL DEFAULT ''");
    $ensureColumn('krathong_types', 'svg_key', "TEXT NOT NULL DEFAULT ''");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_krathongs_visible ON krathongs(is_hidden, id DESC)');

    // บันทึกเหตุการณ์สำหรับจำกัดอัตราการใช้งาน (rate limit) เก็บเฉพาะค่า hash ของ IP
    $pdo->exec('CREATE TABLE IF NOT EXISTS rate_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bucket TEXT NOT NULL,
        ip_hash TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_events_lookup ON rate_events(bucket, ip_hash, created_at)');

    // บันทึกกิจกรรมของผู้ดูแล (เข้าสู่ระบบ, ซ่อน/แสดง/ลบกระทง ฯลฯ)
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action TEXT NOT NULL,
        target TEXT NOT NULL DEFAULT '',
        detail TEXT NOT NULL DEFAULT '',
        ip_hash TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    // รายงานกระทงที่ไม่เหมาะสมจากผู้เข้าชม (ดู includes/reports.php) เก็บเฉพาะ hash ของ IP
    // status: open = รอตรวจสอบ, actioned = ผู้ดูแลซ่อนกระทงแล้ว, dismissed = ผู้ดูแลปัดตก
    $pdo->exec("CREATE TABLE IF NOT EXISTS krathong_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        krathong_id INTEGER NOT NULL,
        reason TEXT NOT NULL,
        detail TEXT NOT NULL DEFAULT '',
        ip_hash TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'actioned', 'dismissed')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        handled_at TEXT,
        FOREIGN KEY (krathong_id) REFERENCES krathongs(id) ON DELETE CASCADE
    )");
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_reports_once ON krathong_reports(krathong_id, ip_hash)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_status ON krathong_reports(status, krathong_id)');

    // Seed data that depends on the columns above (SVG keys, flower sets). Safe to run repeatedly.
    $seedPath = $databaseDirectory . '/seed.sql';
    if (is_file($seedPath)) {
        $pdo->exec((string) file_get_contents($seedPath));
    }
}
