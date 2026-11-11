<?php
declare(strict_types=1);

function getDatabase(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('PHP ยังไม่ได้เปิดใช้งานส่วนขยาย PDO SQLite กรุณาเปิดใช้งาน pdo_sqlite ใน php.ini');
    }

    $databaseDirectory = dirname(__DIR__) . '/database';
    if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0775, true) && !is_dir($databaseDirectory)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์ฐานข้อมูลได้');
    }

    $databasePath = $databaseDirectory . '/database.sqlite';
    $pdo = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

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
    $ensureColumn('flowers', 'image_path', "TEXT NOT NULL DEFAULT ''");
    $ensureColumn('flowers', 'svg_key', "TEXT NOT NULL DEFAULT ''");
    $ensureColumn('krathong_types', 'svg_key', "TEXT NOT NULL DEFAULT ''");

    // Seed data that depends on the columns above (SVG keys, flower sets). Safe to run repeatedly.
    $seedPath = $databaseDirectory . '/seed.sql';
    if (is_file($seedPath)) {
        $pdo->exec((string) file_get_contents($seedPath));
    }

    return $pdo;
}
