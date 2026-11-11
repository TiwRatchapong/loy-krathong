-- ข้อมูลตั้งต้นที่ต้องรันหลัง migration (รันซ้ำได้ ไม่ทำให้ข้อมูลเสีย)

-- เลิกใช้อิโมจิ: ล้างค่าเดิมของฐานข้อมูลรุ่นก่อน แล้วผูกแต่ละรายการกับภาพ SVG ผ่าน svg_key

UPDATE krathong_types SET emoji = '' WHERE emoji <> '';
UPDATE flowers SET emoji = '' WHERE emoji <> '';

UPDATE krathong_types SET svg_key = 'banana-leaf' WHERE name = 'กระทงใบตอง' AND svg_key <> 'banana-leaf';
UPDATE krathong_types SET svg_key = 'lotus' WHERE name = 'กระทงดอกบัว' AND svg_key <> 'lotus';
UPDATE krathong_types SET svg_key = 'bread' WHERE name = 'กระทงขนมปัง' AND svg_key <> 'bread';
UPDATE krathong_types SET svg_key = 'coconut-shell' WHERE name = 'กระทงกะลา' AND svg_key <> 'coconut-shell';
UPDATE krathong_types SET svg_key = 'banana-stem' WHERE name = 'กระทงหยวกกล้วย' AND svg_key <> 'banana-stem';
UPDATE krathong_types SET svg_key = 'leaf' WHERE name = 'กระทงใบไม้' AND svg_key <> 'leaf';
UPDATE krathong_types SET svg_key = 'ornate' WHERE name = 'กระทงประดิษฐ์' AND svg_key <> 'ornate';
UPDATE krathong_types SET svg_key = 'lotus-petals' WHERE name = 'กระทงกลีบบัว' AND svg_key <> 'lotus-petals';
UPDATE krathong_types SET svg_key = 'banana-cone' WHERE name = 'กระทงกรวยใบตอง' AND svg_key <> 'banana-cone';
UPDATE krathong_types SET svg_key = 'pratheep' WHERE name = 'กระทงประทีป' AND svg_key <> 'pratheep';

UPDATE flowers SET svg_key = 'marigold' WHERE name = 'ดอกดาวเรือง' AND svg_key <> 'marigold';
UPDATE flowers SET svg_key = 'jasmine' WHERE name = 'ดอกมะลิ' AND svg_key <> 'jasmine';
UPDATE flowers SET svg_key = 'rose' WHERE name = 'ดอกกุหลาบ' AND svg_key <> 'rose';
UPDATE flowers SET svg_key = 'lotus' WHERE name = 'ดอกบัว' AND svg_key <> 'lotus';
UPDATE flowers SET svg_key = 'orchid' WHERE name = 'ดอกกล้วยไม้' AND svg_key <> 'orchid';
UPDATE flowers SET svg_key = 'globe-amaranth' WHERE name = 'ดอกบานไม่รู้โรย' AND svg_key <> 'globe-amaranth';
UPDATE flowers SET svg_key = 'gardenia' WHERE name = 'ดอกพุด' AND svg_key <> 'gardenia';
UPDATE flowers SET svg_key = 'ixora' WHERE name = 'ดอกเข็ม' AND svg_key <> 'ixora';
UPDATE flowers SET svg_key = 'bullet-wood' WHERE name = 'ดอกพิกุล' AND svg_key <> 'bullet-wood';
UPDATE flowers SET svg_key = 'champi' WHERE name = 'ดอกจำปี' AND svg_key <> 'champi';
UPDATE flowers SET svg_key = 'champa' WHERE name = 'ดอกจำปา' AND svg_key <> 'champa';
UPDATE flowers SET svg_key = 'bougainvillea' WHERE name = 'ดอกเฟื่องฟ้า' AND svg_key <> 'bougainvillea';
UPDATE flowers SET svg_key = 'chrysanthemum' WHERE name = 'ดอกเบญจมาศ' AND svg_key <> 'chrysanthemum';
UPDATE flowers SET svg_key = 'plumeria' WHERE name = 'ดอกลีลาวดี' AND svg_key <> 'plumeria';
UPDATE flowers SET svg_key = 'orange-jasmine' WHERE name = 'ดอกแก้ว' AND svg_key <> 'orange-jasmine';

-- ชุดดอกไม้ (ชุดละ 5 ดอก)
INSERT OR IGNORE INTO flower_sets (name, theme, description, sort_order) VALUES
('ชุดที่ 1', 'ความรุ่งเรือง', 'ขอให้เจริญก้าวหน้า การงานรุ่งโรจน์', 1),
('ชุดที่ 2', 'ความรัก', 'ขอให้รักสมหวัง อบอุ่นหัวใจ', 2),
('ชุดที่ 3', 'ความสงบ', 'ขอให้ใจสงบ ร่มเย็นเป็นสุข', 3),
('ชุดที่ 4', 'ความบริสุทธิ์', 'ขอให้จิตใจผ่องใส เริ่มต้นใหม่', 4),
('ชุดที่ 5', 'สติปัญญา', 'ขอให้เรียนรู้ รู้เท่าทัน ตัดสินใจดี', 5),
('ชุดที่ 6', 'ความสดใส', 'ขอให้ชีวิตสดใส เต็มไปด้วยพลัง', 6),
('ชุดที่ 7', 'ความอ่อนโยน', 'ขอให้พบแต่ความเมตตาและมิตรภาพ', 7),
('ชุดที่ 8', 'ความยั่งยืน', 'ขอให้รักและสุขภาพยืนยาว', 8);

INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 2 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 1' AND f.name = 'ดอกดาวเรือง';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 1' AND f.name = 'ดอกเบญจมาศ';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 1' AND f.name = 'ดอกจำปา';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 1' AND f.name = 'ดอกพิกุล';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 2 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 2' AND f.name = 'ดอกกุหลาบ';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 2' AND f.name = 'ดอกมะลิ';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 2' AND f.name = 'ดอกบานไม่รู้โรย';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 2' AND f.name = 'ดอกเฟื่องฟ้า';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 2 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 3' AND f.name = 'ดอกบัว';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 3' AND f.name = 'ดอกลีลาวดี';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 3' AND f.name = 'ดอกจำปี';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 3' AND f.name = 'ดอกมะลิ';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 2 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 4' AND f.name = 'ดอกมะลิ';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 4' AND f.name = 'ดอกพุด';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 4' AND f.name = 'ดอกแก้ว';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 4' AND f.name = 'ดอกลีลาวดี';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 2 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 5' AND f.name = 'ดอกเข็ม';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 5' AND f.name = 'ดอกบัว';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 5' AND f.name = 'ดอกกล้วยไม้';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 5' AND f.name = 'ดอกเบญจมาศ';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 2 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 6' AND f.name = 'ดอกเฟื่องฟ้า';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 6' AND f.name = 'ดอกกล้วยไม้';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 6' AND f.name = 'ดอกดาวเรือง';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 6' AND f.name = 'ดอกจำปา';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 2 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 7' AND f.name = 'ดอกลีลาวดี';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 7' AND f.name = 'ดอกพิกุล';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 7' AND f.name = 'ดอกจำปี';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 7' AND f.name = 'ดอกแก้ว';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 2 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 8' AND f.name = 'ดอกบานไม่รู้โรย';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 8' AND f.name = 'ดอกเบญจมาศ';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 8' AND f.name = 'ดอกกุหลาบ';
INSERT OR IGNORE INTO flower_set_items (set_id, flower_id, quantity) SELECT s.id, f.id, 1 FROM flower_sets s, flowers f WHERE s.name = 'ชุดที่ 8' AND f.name = 'ดอกเข็ม';
