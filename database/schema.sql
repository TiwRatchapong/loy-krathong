PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS krathong_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL,
    image_path TEXT NOT NULL DEFAULT '',
    emoji TEXT NOT NULL DEFAULT '', -- เลิกใช้แล้ว (ภาพกระทงวาดเป็น SVG ดู svg_key)
    svg_key TEXT NOT NULL DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS flowers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    emoji TEXT NOT NULL DEFAULT '', -- เลิกใช้แล้ว (ภาพดอกไม้วาดเป็น SVG ดู svg_key)
    description TEXT NOT NULL,
    image_path TEXT NOT NULL DEFAULT '',
    svg_key TEXT NOT NULL DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0
);

-- ชุดดอกไม้สำเร็จรูป (ชุดที่ 1, ชุดที่ 2, ...) ชุดละ 5 ดอก
CREATE TABLE IF NOT EXISTS flower_sets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    theme TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS flower_set_items (
    set_id INTEGER NOT NULL,
    flower_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity BETWEEN 1 AND 5),
    PRIMARY KEY (set_id, flower_id),
    FOREIGN KEY (set_id) REFERENCES flower_sets(id) ON DELETE CASCADE,
    FOREIGN KEY (flower_id) REFERENCES flowers(id)
);

CREATE TABLE IF NOT EXISTS krathongs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nickname TEXT NOT NULL,
    krathong_type_id INTEGER,
    flower_set_id INTEGER REFERENCES flower_sets(id),
    prayer_type TEXT NOT NULL CHECK (prayer_type IN ('preset', 'custom')),
    prayer TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (krathong_type_id) REFERENCES krathong_types(id)
);

CREATE TABLE IF NOT EXISTS krathong_flowers (
    krathong_id INTEGER NOT NULL,
    flower_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity BETWEEN 1 AND 5),
    PRIMARY KEY (krathong_id, flower_id),
    FOREIGN KEY (krathong_id) REFERENCES krathongs(id) ON DELETE CASCADE,
    FOREIGN KEY (flower_id) REFERENCES flowers(id)
);

CREATE INDEX IF NOT EXISTS idx_krathongs_created_at ON krathongs(created_at DESC);

INSERT OR IGNORE INTO krathong_types (name, description, sort_order) VALUES
('กระทงใบตอง', 'กระทงแบบไทยดั้งเดิม ทำจากใบตองธรรมชาติ', 1),
('กระทงดอกบัว', 'รูปทรงดอกบัว สื่อถึงความสงบและความบริสุทธิ์', 2),
('กระทงขนมปัง', 'กระทงขนมปัง ย่อยสลายได้ตามธรรมชาติ', 3),
('กระทงกะลา', 'กระทงจากกะลามะพร้าว เรียบง่ายและเป็นธรรมชาติ', 4),
('กระทงหยวกกล้วย', 'กระทงจากหยวกกล้วย ตกแต่งแบบพื้นบ้าน', 5),
('กระทงใบไม้', 'กระทงใบไม้โทนธรรมชาติ เรียบง่าย', 6),
('กระทงประดิษฐ์', 'กระทงตกแต่งประณีตในสไตล์ร่วมสมัย', 7),
('กระทงกลีบบัว', 'กระทงประดับกลีบบัวอย่างอ่อนช้อย', 8),
('กระทงกรวยใบตอง', 'กระทงทรงกรวยจากใบตอง พับเป็นชั้นอย่างประณีต', 9),
('กระทงประทีป', 'กระทงประดับแสงเทียน ให้บรรยากาศอบอุ่นยามค่ำคืน', 10);

INSERT OR IGNORE INTO flowers (name, description, sort_order) VALUES
('ดอกดาวเรือง', 'ความรุ่งเรืองและความสำเร็จ', 1),
('ดอกมะลิ', 'ความบริสุทธิ์และความรัก', 2),
('ดอกกุหลาบ', 'ความรักและความปรารถนาดี', 3),
('ดอกบัว', 'ความสงบและสติปัญญา', 4),
('ดอกกล้วยไม้', 'ความงดงามและความเจริญงอกงาม', 5),
('ดอกบานไม่รู้โรย', 'ความรักมั่นคงและยั่งยืน', 6),
('ดอกพุด', 'ความบริสุทธิ์และความตั้งใจดี', 7),
('ดอกเข็ม', 'สติปัญญาและความเฉียบแหลม', 8),
('ดอกพิกุล', 'ความดีงามและความอ่อนโยน', 9),
('ดอกจำปี', 'ความสดชื่นและความก้าวหน้า', 10),
('ดอกจำปา', 'เสน่ห์และความเมตตา', 11),
('ดอกเฟื่องฟ้า', 'ความสดใสและความแข็งแกร่ง', 12),
('ดอกเบญจมาศ', 'ความจริงใจและความยืนยาว', 13),
('ดอกลีลาวดี', 'ความอ่อนโยนและความสง่างาม', 14),
('ดอกแก้ว', 'ความหอมหวานและความบริสุทธิ์', 15);
