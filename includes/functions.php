<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * โหลดชุดดอกไม้ทั้งหมดพร้อมรายการดอกไม้ในแต่ละชุด
 *
 * @return array<int, array{id:int, name:string, theme:string, description:string, items:array, keys:string[], flower_names:string[]}>
 */
function loadFlowerSets(PDO $pdo): array
{
    $sets = $pdo->query('SELECT id, name, theme, description FROM flower_sets WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
    $itemStatement = $pdo->prepare(
        'SELECT i.flower_id, i.quantity, f.name, f.svg_key
         FROM flower_set_items i JOIN flowers f ON f.id = i.flower_id
         WHERE i.set_id = :set_id ORDER BY i.quantity DESC, f.sort_order'
    );
    $result = [];
    foreach ($sets as $set) {
        $itemStatement->execute([':set_id' => $set['id']]);
        $items = $itemStatement->fetchAll();
        $keys = [];
        $names = [];
        foreach ($items as $item) {
            for ($i = 0; $i < (int) $item['quantity']; $i++) {
                $keys[] = $item['svg_key'];
            }
            $names[] = preg_replace('/^ดอก/u', '', $item['name']) . ((int) $item['quantity'] > 1 ? ' ×' . (int) $item['quantity'] : '');
        }
        // ชุดที่ถูกต้องต้องมีดอกไม้รวม 5 ดอกพอดี
        if (count($keys) !== 5) {
            continue;
        }
        $set['id'] = (int) $set['id'];
        $set['items'] = $items;
        // เรียงตามช่องบนกระทง [กลาง, ซ้ายใน, ขวาใน, ซ้ายนอก, ขวานอก] ให้ดอกชนิดซ้ำไปอยู่คนละปลาย
        // ไม่วางติดกัน (ซ้ำกันติดกันสีจะกลืนเป็นก้อนเดียว)
        if ($keys[0] === $keys[1]) {
            $keys = [$keys[2], $keys[3], $keys[4], $keys[0], $keys[1]];
        }
        $set['keys'] = $keys;
        $set['flower_names'] = $names;
        $result[] = $set;
    }
    return $result;
}

/**
 * โหลดกระทงที่บันทึกแล้วพร้อมข้อมูลสำหรับวาดภาพ (svg_key ของกระทงและดอกไม้เรียงตามช่อง)
 *
 * @param string $where เงื่อนไข SQL ที่ใช้ alias k (krathongs) เช่น 'k.id = :id' (ว่างได้)
 * @return array<int, array<string,mixed>>
 */
function loadKrathongs(PDO $pdo, string $where = '', array $params = [], int $limit = 24, int $offset = 0): array
{
    $sql = 'SELECT k.id, k.nickname, k.prayer, k.prayer_type, k.created_at, k.flower_set_id, t.svg_key AS krathong_key, t.name AS krathong_name
            FROM krathongs k LEFT JOIN krathong_types t ON t.id = k.krathong_type_id'
        . ($where !== '' ? ' WHERE ' . $where : '')
        . ' ORDER BY k.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();

    $keysBySet = [];
    foreach (loadFlowerSets($pdo) as $set) {
        $keysBySet[$set['id']] = $set['keys'];
    }
    foreach ($rows as &$row) {
        $row['flower_keys'] = $keysBySet[(int) $row['flower_set_id']] ?? [];
    }
    unset($row);
    return $rows;
}

/** วาดภาพกระทงพร้อมเทียนและดอกไม้เป็น <svg> (ตำแหน่งดอกไม้ต้องตรงกับ FLOWER_SLOTS ใน create.js) */
function renderKrathongArt(?string $krathongKey, array $flowerKeys, string $label = 'กระทง', string $class = 'krathong-art'): string
{
    // [ศูนย์กลาง x, y, ขนาด, องศาหมุน] ลำดับ: กลาง, ซ้ายใน, ขวาใน, ซ้ายนอก, ขวานอก
    $slots = [[120, 139, 54, 0], [93, 131, 44, -14], [147, 131, 44, 12], [67, 124, 38, -22], [173, 124, 38, 20]];
    $order = [3, 4, 1, 2, 0];
    $out = '<svg class="' . e($class) . '" viewBox="0 20 240 180" role="img" aria-label="' . e($label) . '">'
        . '<ellipse cx="120" cy="104" rx="112" ry="72" fill="url(#gradFlame)" opacity=".45"/>';
    if ($krathongKey) {
        $out .= '<use href="#kt-' . e($krathongKey) . '" x="20" y="62" width="200" height="130"/>';
    }
    $out .= '<path d="M106 134 L93 66" stroke="#7a4a22" stroke-width="2.2" stroke-linecap="round"/><circle cx="93" cy="66" r="2" fill="#ff5a36"/>'
        . '<path d="M134 134 L148 62" stroke="#7a4a22" stroke-width="2.2" stroke-linecap="round"/><circle cx="148" cy="62" r="2" fill="#ff5a36"/>'
        . '<circle class="flame-glow" cx="120" cy="72" r="20" fill="url(#gradFlame)"/>'
        . '<rect x="113.5" y="84" width="13" height="50" rx="2" fill="url(#gradCandle)" stroke="#d9bd7c" stroke-width=".8"/>'
        . '<path d="M120 84 L120 78" stroke="#4a3320" stroke-width="1.4"/>'
        . '<path class="flame-outer" d="M120 80 C112 72 119 64 120 55 C121 64 128 72 120 80Z" fill="#ffae2b"/>'
        . '<path class="flame-inner" d="M120 78 C116.5 73 119.5 69 120 64 C120.5 69 123.5 73 120 78Z" fill="#fff3b0"/>';
    foreach ($order as $slot) {
        if (!isset($flowerKeys[$slot])) {
            continue;
        }
        [$cx, $cy, $size, $rot] = $slots[$slot];
        $out .= '<use href="#fk-' . e($flowerKeys[$slot]) . '" x="' . ($cx - $size / 2) . '" y="' . ($cy - $size / 2)
            . '" width="' . $size . '" height="' . $size . '" transform="rotate(' . $rot . ' ' . $cx . ' ' . $cy . ')"/>';
    }
    return $out . '</svg>';
}

/**
 * ส่วนหัว (แบรนด์ + เมนู + ปุ่มเมนูมือถือ) ที่ใช้ร่วมกันทุกหน้าย่อยใน /pages
 *
 * @param string $active หน้าปัจจุบัน: 'home' | 'create' | 'river' | ''
 */
function renderSiteHeader(string $active = ''): void
{
    $links = ['home' => ['../index.php', 'หน้าแรก'], 'create' => ['create.php', 'สร้างกระทง'], 'river' => ['river.php', 'แม่น้ำของทุกคน']];
    ?>
    <header class="site-header">
        <a class="brand" href="../index.php" aria-label="ลอยกระทงออนไลน์ หน้าแรก"><span class="brand-mark"><?= svgLotusIcon() ?></span><span>ลอยกระทง<span class="brand-light">ออนไลน์</span></span></a>
        <button class="menu-toggle" id="menuToggle" type="button" aria-label="เปิดเมนู" aria-expanded="false" aria-controls="mainNav"><span></span><span></span><span></span></button>
        <nav class="main-nav" id="mainNav" aria-label="เมนูหลัก">
            <?php foreach ($links as $key => [$href, $label]): ?>
                <a class="nav-link<?= $key === $active ? ' active' : '' ?>" href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>
<?php
}

/** ส่วน <head> และส่วนหัวของหน้าที่ใช้ร่วมกัน (view / gallery / river) */
function renderPageTop(string $title, string $bodyClass = 'create-page', string $active = ''): void
{
    ?><!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#102d25">
    <title><?= e($title) ?> | ลอยกระทงออนไลน์</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
    <?php renderSvgSprite(); ?>
    <div class="scene" aria-hidden="true"></div><div class="scene-shade" aria-hidden="true"></div>
    <?php renderSiteHeader($active); ?>
<?php
}

function renderPageBottom(string $script = ''): void
{
    ?>
    <footer class="site-footer"><span class="footer-brand"><?= svgLotusIcon() ?> ลอยกระทงออนไลน์</span><span>ขอให้ค่ำคืนนี้เต็มไปด้วยความสุข</span></footer>
    <script src="../assets/js/script.js"></script>
    <?php if ($script !== ''): ?><script src="../assets/js/<?= e($script) ?>"></script><?php endif; ?>
</body>
</html>
<?php
}

// ---------- แม่น้ำในภาพพื้นหลัง ----------
// ทุกค่าเป็นเปอร์เซ็นต์ของภาพ river-night.png (x = ความกว้าง, y = ความสูง) จึงใช้ได้กับทุกขนาดหน้าจอ
const RIVER_RATIO = 0.5628;        // สูง/กว้าง ของภาพ (941/1672)
const RIVER_Y_FAR = 55.0;          // y ของผืนน้ำส่วนที่ไกลที่สุด (ใกล้เส้นขอบฟ้า)
const RIVER_Y_NEAR = 85.0;         // y ของผืนน้ำส่วนที่ใกล้ที่สุด (ริมท่าน้ำ)
const RIVER_SCALE_FAR = 0.30;      // ขนาดกระทงเมื่ออยู่ไกลสุด
const RIVER_SCALE_NEAR = 1.25;     // ขนาดกระทงเมื่ออยู่ใกล้สุด

/**
 * ขอบเขตผืนน้ำที่กระทงลอยได้ (พอลิกอนตามที่วาดไว้บนภาพ) กระทงจะไม่ลอยออกนอกพื้นที่นี้
 *
 * @return array<int, array{0:float,1:float}>
 */
function riverPolygon(): array
{
    return [
        [18.47, 56.67],
        [18.94, 58.16],
        [26.81, 57.88],
        [30.88, 59.08],
        [31.14, 63.9],
        [32.39, 64.64],
        [33.54, 68.54],
        [35.73, 70.76],
        [36.46, 74.47],
        [46.43, 74.19],
        [48.36, 73.26],
        [52.63, 73.91],
        [65.83, 73.82],
        [67.14, 77.34],
        [68.02, 84.67],
        [68.6, 85.31],
        [72.82, 85.22],
        [73.87, 79.38],
        [74.54, 78.92],
        [79.45, 79.75],
        [80.8, 80.68],
        [81.12, 83.18],
        [81.64, 83.92],
        [85.71, 84.11],
        [86.18, 82.07],
        [88.26, 81.79],
        [88.84, 79.29],
        [88.63, 77.06],
        [86.12, 75.03],
        [86.18, 70.21],
        [86.65, 68.63],
        [90.92, 68.35],
        [94.84, 66.13],
        [95.98, 63.72],
        [98.07, 64.27],
        [98.44, 63.53],
        [98.38, 60.47],
        [97.34, 59.73],
        [85.55, 60.38],
        [79.19, 59.18],
        [77.99, 57.04],
        [58.32, 57.14],
        [52.32, 55.75],
        [39.49, 56.4],
        [38.5, 56.02],
        [37.72, 54.63],
        [31.19, 54.45],
        [30.83, 55.75],
        [18.73, 55.38],
    ];
}

/** จุด (x,y) อยู่ภายในพอลิกอนหรือไม่ (ray casting) */
function riverPointInside(float $x, float $y, array $poly): bool
{
    $inside = false;
    for ($i = 0, $j = count($poly) - 1; $i < count($poly); $j = $i++) {
        [$xi, $yi] = $poly[$i];
        [$xj, $yj] = $poly[$j];
        if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-9) + $xi)) {
            $inside = !$inside;
        }
    }
    return $inside;
}

/** จุดอยู่ในแม่น้ำและมีระยะเผื่อรอบตัว (ตัวกระทงไม่ล้ำขอบ) */
function riverContains(float $x, float $y, float $margin = 2.4): bool
{
    static $poly = null;
    $poly ??= riverPolygon();
    $dy = $margin * 0.5 / RIVER_RATIO;
    foreach ([[0, 0], [$margin, 0], [-$margin, 0], [0, $dy], [0, -$dy]] as [$ox, $oy]) {
        if (!riverPointInside($x + $ox, $y + $oy, $poly)) {
            return false;
        }
    }
    return true;
}

/** ขนาดกระทงตามความลึกของภาพ: ยิ่งอยู่ไกล (y น้อย) ยิ่งเล็ก ยิ่งใกล้ท่าน้ำยิ่งใหญ่ */
function riverScale(float $y): float
{
    $t = max(0.0, min(1.0, ($y - RIVER_Y_FAR) / (RIVER_Y_NEAR - RIVER_Y_FAR)));
    return round(RIVER_SCALE_FAR + (RIVER_SCALE_NEAR - RIVER_SCALE_FAR) * $t, 3);
}

/**
 * กำหนดตำแหน่งเริ่มต้นของกระทงบนผืนน้ำ (เป็นเปอร์เซ็นต์ของภาพ) แบบคงที่ตาม id เพื่อไม่ให้ตำแหน่งสุ่มเปลี่ยนทุกครั้งที่รีเฟรช
 * ตำแหน่งอยู่ในขอบเขต riverPolygon() เสมอ และขนาดเปลี่ยนตามความไกล-ใกล้ (riverScale)
 * การลอยต่อหลังจากนั้นทำใน river.js (ลอยอยู่ในขอบเขตเดียวกัน)
 *
 * @param array<int, array<string,mixed>> $rows
 * @param array<int, array{0:float,1:float}> $taken ตำแหน่งที่ถูกใช้ไปแล้ว (เช่นกระทงของผู้ใช้)
 * @return array<int, array<string,mixed>>
 */
function placeKrathongs(array $rows, array $taken = []): array
{
    foreach ($rows as &$row) {
        mt_srand((int) $row['id'] * 7919 + 17);
        $best = null;
        $bestGap = -1.0;
        for ($try = 0; $try < 80; $try++) {
            $x = mt_rand(180, 985) / 10;
            $y = mt_rand(545, 855) / 10;
            if (!riverContains($x, $y)) {
                continue;
            }
            $gap = 99.0;
            foreach ($taken as [$tx, $ty]) {
                $gap = min($gap, hypot($x - $tx, ($y - $ty) * RIVER_RATIO));
            }
            if ($gap > $bestGap) {
                $bestGap = $gap;
                $best = [$x, $y];
            }
            if ($gap >= 7) {
                break;
            }
        }
        [$x, $y] = $best ?? [52.0, 66.0];
        $taken[] = [$x, $y];
        $row['x'] = $x;
        $row['y'] = $y;
        $row['scale'] = riverScale($y);
        $row['speed'] = mt_rand(30, 80) / 100;        // ความเร็วลอย (เปอร์เซ็นต์ความกว้างภาพ ต่อวินาที)
        $row['heading'] = mt_rand(0, 628) / 100;      // ทิศเริ่มต้น (เรเดียน)
        $row['bob'] = mt_rand(36, 62) / 10;
    }
    unset($row);
    mt_srand();
    return $rows;
}
