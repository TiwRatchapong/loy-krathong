<?php
declare(strict_types=1);

/**
 * ภาพกระทงและดอกไม้ทั้งหมดวาดเป็น SVG (ไม่ใช้อิโมจิ)
 *
 * renderSvgSprite() พิมพ์ <svg> ซ่อนไว้ในหน้า แล้วเรียกใช้ซ้ำด้วย <use href="#kt-..."> / <use href="#fl-...">
 *  - กระทง  : id = "kt-{svg_key}"  viewBox 0 0 200 130
 *  - ดอกไม้ : id = "fl-{svg_key}"  viewBox 0 0 64 64
 * svg_key เก็บอยู่ในตาราง krathong_types / flowers
 */

function svgFmt(float $n): string
{
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

/** วางรูปร่างซ้ำเป็นวงกลมรอบจุดศูนย์กลาง ($shape วาดให้ชี้ขึ้นบน) */
function svgRing(int $count, string $shape, float $offset = 0.0, float $cx = 32, float $cy = 32): string
{
    $out = '';
    for ($i = 0; $i < $count; $i++) {
        $angle = $offset + $i * 360 / $count;
        $out .= '<g transform="rotate(' . svgFmt($angle) . ' ' . svgFmt($cx) . ' ' . svgFmt($cy) . ')">' . $shape . '</g>';
    }
    return $out;
}

/** ตำแหน่งแบบเมล็ดทานตะวัน ใช้จัดดอกเล็ก ๆ เป็นกลุ่มทรงโดม */
function svgSunflowerPoints(int $count, float $radius, float $cx = 32, float $cy = 32): array
{
    $points = [];
    for ($i = 0; $i < $count; $i++) {
        $r = $radius * sqrt(($i + 0.5) / $count);
        $a = deg2rad($i * 137.508);
        $points[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
    }
    return $points;
}

/* ------------------------------------------------------------------ */
/* ดอกไม้ 15 ชนิด (viewBox 64x64)                                       */
/* ------------------------------------------------------------------ */

function svgFlowerSymbols(): array
{
    $f = [];

    // ดอกดาวเรือง — กลีบซ้อนหลายชั้นสีส้มทอง
    $f['marigold'] =
        svgRing(18, '<ellipse cx="32" cy="12.5" rx="4.3" ry="10" fill="#ef8f12"/>')
        . svgRing(14, '<ellipse cx="32" cy="16" rx="4.2" ry="8.5" fill="#f6a81f" stroke="#d9770b" stroke-width=".4"/>', 6)
        . svgRing(11, '<ellipse cx="32" cy="20" rx="3.6" ry="6.5" fill="#fbc03a" stroke="#e4900f" stroke-width=".35"/>', 3)
        . svgRing(8, '<ellipse cx="32" cy="24" rx="3" ry="4.4" fill="#fdd25e"/>', 10)
        . '<circle cx="32" cy="32" r="3.2" fill="#c96a05"/>';

    // ดอกมะลิ — กลีบขาวกลมสองชั้น
    $f['jasmine'] =
        svgRing(5, '<ellipse cx="32" cy="17" rx="8" ry="11.5" fill="#e4e1d3"/>', 36)
        . svgRing(5, '<ellipse cx="32" cy="17.5" rx="7.6" ry="11.5" fill="#fffef8" stroke="#d6d0ba" stroke-width=".7"/>')
        . '<circle cx="32" cy="32" r="4.2" fill="#f4dc7a"/><circle cx="32" cy="32" r="2" fill="#d8b23a"/>';

    // ดอกกุหลาบ — กลีบม้วนเป็นวงเกลียว
    $f['rose'] =
        '<circle cx="32" cy="32" r="25" fill="#a31229"/>'
        . svgRing(6, '<circle cx="32" cy="16" r="10.5" fill="#d02a46" stroke="#8c0f24" stroke-width="1"/>', 15)
        . '<circle cx="32" cy="32" r="16" fill="#dc3552" stroke="#8c0f24" stroke-width="1"/>'
        . '<circle cx="32" cy="32" r="11.5" fill="#c4233e" stroke="#8c0f24" stroke-width="1"/>'
        . '<path d="M32 32m-7 0a7 7 0 1 1 7 7" fill="none" stroke="#7d0c20" stroke-width="1.6" stroke-linecap="round"/>'
        . '<path d="M32 32m-3.4 0a3.4 3.4 0 1 1 3.4 3.4" fill="none" stroke="#7d0c20" stroke-width="1.5" stroke-linecap="round"/>'
        . '<path d="M20 22c4-6 11-8 17-6" fill="none" stroke="#ff8aa0" stroke-width="1.6" stroke-linecap="round" opacity=".7"/>';

    // ดอกบัว — มองด้านข้าง กลีบแหลมชมพู
    $f['lotus'] =
        '<path d="M32 56 C13 54 5 40 7 25 C19 31 28 42 32 56Z" fill="#f2a3c0" stroke="#d96d92" stroke-width="1"/>'
        . '<path d="M32 56 C51 54 59 40 57 25 C45 31 36 42 32 56Z" fill="#f2a3c0" stroke="#d96d92" stroke-width="1"/>'
        . '<path d="M32 56 C19 49 13 34 17 14 C27 24 32 39 32 56Z" fill="#f58cb4" stroke="#d96d92" stroke-width="1"/>'
        . '<path d="M32 56 C45 49 51 34 47 14 C37 24 32 39 32 56Z" fill="#f58cb4" stroke="#d96d92" stroke-width="1"/>'
        . '<path d="M32 58 C21 47 23 24 32 6 C41 24 43 47 32 58Z" fill="#ffd6e5" stroke="#e07a9d" stroke-width="1"/>'
        . '<path d="M32 52 C28 40 29 24 32 14" fill="none" stroke="#ffffff" stroke-width="1.6" stroke-linecap="round" opacity=".7"/>'
        . '<path d="M14 58 Q32 50 50 58 Q32 64 14 58Z" fill="#3f8d4b"/>';

    // ดอกกล้วยไม้ — ม่วงชมพู กลีบข้างกว้าง
    $f['orchid'] =
        '<ellipse cx="32" cy="14" rx="8" ry="11.5" fill="#e98ccb" stroke="#b6469a" stroke-width=".9"/>'
        . '<ellipse cx="20.5" cy="46" rx="6.5" ry="12" transform="rotate(32 20.5 46)" fill="#e98ccb" stroke="#b6469a" stroke-width=".9"/>'
        . '<ellipse cx="43.5" cy="46" rx="6.5" ry="12" transform="rotate(-32 43.5 46)" fill="#e98ccb" stroke="#b6469a" stroke-width=".9"/>'
        . '<ellipse cx="15.5" cy="29" rx="13" ry="15" transform="rotate(-18 15.5 29)" fill="#f4b0dc" stroke="#b6469a" stroke-width=".9"/>'
        . '<ellipse cx="48.5" cy="29" rx="13" ry="15" transform="rotate(18 48.5 29)" fill="#f4b0dc" stroke="#b6469a" stroke-width=".9"/>'
        . '<path d="M32 30 C25 32 24 42 30 50 Q32 53 34 50 C40 42 39 32 32 30Z" fill="#a22a85" stroke="#7a1a63" stroke-width=".8"/>'
        . '<ellipse cx="32" cy="33" rx="4" ry="3.2" fill="#fff0a6"/><circle cx="29" cy="43" r="1" fill="#ffd3ee"/><circle cx="35" cy="43" r="1" fill="#ffd3ee"/>';

    // ดอกบานไม่รู้โรย — ช่อกลมสีม่วง
    $bumps = '';
    foreach (array_reverse(svgSunflowerPoints(34, 19.5)) as $i => [$x, $y]) {
        $dark = $i % 2 === 0;
        $bumps .= '<circle cx="' . svgFmt($x) . '" cy="' . svgFmt($y) . '" r="5" fill="' . ($dark ? '#9a3fc0' : '#ad57d3') . '" stroke="#7a2a9c" stroke-width=".5"/>'
            . '<circle cx="' . svgFmt($x - 1.4) . '" cy="' . svgFmt($y - 1.6) . '" r="1.5" fill="#e2b3f2" opacity=".8"/>';
    }
    $f['globe-amaranth'] = '<circle cx="32" cy="32" r="25" fill="#8a33ae"/>' . $bumps;

    // ดอกพุด — กลีบขาวครีมซ้อนกันแบบกังหัน มีใบเขียว
    $f['gardenia'] =
        '<ellipse cx="12" cy="50" rx="11" ry="5.5" transform="rotate(-30 12 50)" fill="#3c8a47"/>'
        . '<ellipse cx="52" cy="50" rx="11" ry="5.5" transform="rotate(30 52 50)" fill="#3c8a47"/>'
        . svgRing(7, '<ellipse cx="32" cy="15" rx="9.5" ry="13" fill="#efe9d6" stroke="#cfc6a8" stroke-width=".7"/>')
        . svgRing(7, '<ellipse cx="32" cy="18" rx="8" ry="11" fill="#fffdf6" stroke="#d6cdb0" stroke-width=".7"/>', 25)
        . '<circle cx="32" cy="32" r="6" fill="#f0e7c8" stroke="#cdbf94" stroke-width=".7"/>'
        . '<path d="M32 32m-3.2 0a3.2 3.2 0 1 1 3.2 3.2" fill="none" stroke="#b8a66f" stroke-width="1" stroke-linecap="round"/>';

    // ดอกเข็ม — ช่อดอกเล็กสีแดงส้มเป็นโดม
    $ixoraFlowers = '';
    foreach (array_reverse(svgSunflowerPoints(15, 19)) as [$x, $y]) {
        $ixoraFlowers .= '<g transform="translate(' . svgFmt($x) . ' ' . svgFmt($y) . ')">'
            . '<circle cx="0" cy="-3" r="2.9" fill="#f04a22"/><circle cx="3" cy="0" r="2.9" fill="#f04a22"/>'
            . '<circle cx="0" cy="3" r="2.9" fill="#f04a22"/><circle cx="-3" cy="0" r="2.9" fill="#f04a22"/>'
            . '<circle cx="0" cy="0" r="1.3" fill="#ffc7a8"/></g>';
    }
    $f['ixora'] =
        '<ellipse cx="11" cy="48" rx="10.5" ry="5" transform="rotate(-35 11 48)" fill="#37803f"/>'
        . '<ellipse cx="53" cy="48" rx="10.5" ry="5" transform="rotate(35 53 48)" fill="#37803f"/>'
        . '<circle cx="32" cy="32" r="25" fill="#c92d14"/>' . $ixoraFlowers;

    // ดอกพิกุล — ดอกเล็กกลีบเรียวสีครีมเหลือง
    $f['bullet-wood'] =
        svgRing(10, '<ellipse cx="32" cy="14" rx="3.4" ry="11.5" fill="#efdc8e" stroke="#c9ad4f" stroke-width=".5"/>')
        . svgRing(10, '<ellipse cx="32" cy="19" rx="2.8" ry="8" fill="#fbefb8" stroke="#d4ba62" stroke-width=".4"/>', 18)
        . '<circle cx="32" cy="32" r="5.5" fill="#a97c2e"/><circle cx="32" cy="32" r="2.6" fill="#6f4d16"/>'
        . svgRing(8, '<circle cx="32" cy="27" r="1" fill="#e4c26a"/>');

    // ดอกจำปี — กลีบเรียวยาวสีขาวครีม
    $champiPetal = '<path d="M32 33 C25 24 26 10 33 3 C41 10 40 25 32 33Z" fill="#fff6dc" stroke="#d8c07c" stroke-width=".8"/>'
        . '<path d="M32 30 C29 22 30 12 33 7" fill="none" stroke="#ead9a0" stroke-width=".9" stroke-linecap="round"/>';
    $f['champi'] = svgRing(8, $champiPetal, 8) . svgRing(8, $champiPetal) . '<circle cx="32" cy="32" r="4" fill="#9db752"/><circle cx="32" cy="32" r="1.8" fill="#6b8a2f"/>';

    // ดอกจำปา — กลีบเรียวสีส้มเข้ม
    $champaPetal = '<path d="M32 33 C27 24 28 9 34 2 C40 10 38 25 32 33Z" fill="#f59a2c" stroke="#c8641a" stroke-width=".8"/>'
        . '<path d="M32 30 C30 22 31 12 34 6" fill="none" stroke="#fcc36d" stroke-width="1" stroke-linecap="round"/>';
    $f['champa'] = svgRing(7, $champaPetal, 25) . svgRing(7, $champaPetal) . '<circle cx="32" cy="32" r="4.2" fill="#d97a1c"/><circle cx="32" cy="32" r="1.9" fill="#8d4a0b"/>';

    // ดอกเฟื่องฟ้า — ใบประดับสีบานเย็นรูปหัวใจ
    $bract = '<path d="M32 33 C12 31 8 9 24 6 C29 6 32 10 32 15 C32 10 35 6 40 6 C56 9 52 31 32 33Z" fill="__C__" stroke="#8f1f5f" stroke-width=".8"/>';
    $f['bougainvillea'] =
        svgRing(3, str_replace('__C__', '#f27ac0', $bract), 60)
        . svgRing(3, str_replace('__C__', '#d52f93', $bract))
        . svgRing(3, '<ellipse cx="32" cy="27" rx="1.6" ry="4.2" fill="#fff6e0"/>', 0)
        . '<circle cx="32" cy="32" r="2" fill="#f4d35e"/>';

    // ดอกเบญจมาศ — กลีบเส้นเล็กละเอียดหลายชั้น
    $f['chrysanthemum'] =
        svgRing(30, '<ellipse cx="32" cy="12" rx="2.1" ry="10.5" fill="#f9c8e0" stroke="#e58fba" stroke-width=".35"/>')
        . svgRing(24, '<ellipse cx="32" cy="15.5" rx="2.1" ry="8.5" fill="#f4a3cb" stroke="#d870a5" stroke-width=".35"/>', 7.5)
        . svgRing(18, '<ellipse cx="32" cy="19" rx="2" ry="6.5" fill="#ee86b9" stroke="#cc5c93" stroke-width=".35"/>', 5)
        . svgRing(12, '<ellipse cx="32" cy="23" rx="1.9" ry="4.4" fill="#f9d86a"/>', 10)
        . '<circle cx="32" cy="32" r="2.6" fill="#e8b012"/>';

    // ดอกลีลาวดี — กลีบกังหันสีขาว โคนเหลือง ขอบชมพู
    $plumeria = '<path d="M32 33 C18 33 11 17 22 7 C34 3 42 21 32 33Z" fill="#fffaf2" stroke="#f0a1b6" stroke-width="1"/>';
    $f['plumeria'] =
        svgRing(5, $plumeria)
        . '<circle cx="32" cy="32" r="10" fill="url(#gradPlumeria)"/>'
        . svgRing(5, '<path d="M32 32 C30 26 31 22 33 19" fill="none" stroke="#f6b8c6" stroke-width=".9" stroke-linecap="round"/>');

    // ดอกแก้ว — กลีบขาวเรียวแหลม มีใบเขียวคู่
    $f['orange-jasmine'] =
        '<ellipse cx="13" cy="46" rx="12" ry="5.5" transform="rotate(-38 13 46)" fill="#3f8f47"/>'
        . '<ellipse cx="51" cy="46" rx="12" ry="5.5" transform="rotate(38 51 46)" fill="#3f8f47"/>'
        . svgRing(5, '<path d="M32 33 C26 25 27 12 32 4 C37 12 38 25 32 33Z" fill="#ffffff" stroke="#c4cfad" stroke-width=".8"/>')
        . '<circle cx="32" cy="32" r="3.6" fill="#f1d04c"/><circle cx="32" cy="32" r="1.6" fill="#c89a14"/>';
    return $f;
}

/* ------------------------------------------------------------------ */
/* กระทง 10 แบบ (viewBox 200x130, หน้ากระทงอยู่ที่ประมาณ (100,68))       */
/* ------------------------------------------------------------------ */

function svgKrathongShadow(): string
{
    return '<ellipse cx="100" cy="118" rx="84" ry="8" fill="#021311" opacity=".42"/>';
}

function svgKrathongSymbols(): array
{
    $k = [];
    $shadow = svgKrathongShadow();

    // 1) กระทงใบตอง — จานใบตองพับจีบรอบขอบ
    $pleatsBack = '';
    $pleatsFront = '';
    for ($i = 0; $i < 15; $i++) {
        $t = deg2rad(180 + ($i + 0.5) * 180 / 15);
        $x = 100 + 77 * cos($t);
        $y = 68 + 25 * sin($t);
        $pleatsBack .= '<path d="M' . svgFmt($x - 7) . ' ' . svgFmt($y) . ' L' . svgFmt($x) . ' ' . svgFmt($y - 11) . ' L' . svgFmt($x + 7) . ' ' . svgFmt($y) . 'Z" fill="#5da944" stroke="#2f6f2d" stroke-width=".8"/>';
        $t2 = deg2rad(($i + 0.5) * 180 / 15);
        $x2 = 100 + 77 * cos($t2);
        $y2 = 68 + 25 * sin($t2);
        $pleatsFront .= '<path d="M' . svgFmt($x2 - 7.5) . ' ' . svgFmt($y2 - 2) . ' L' . svgFmt($x2) . ' ' . svgFmt($y2 + 15) . ' L' . svgFmt($x2 + 7.5) . ' ' . svgFmt($y2 - 2) . 'Z" fill="' . ($i % 2 ? '#3f8c37' : '#49983e') . '" stroke="#245a24" stroke-width=".8"/>';
    }
    $veins = '';
    for ($i = 0; $i < 11; $i++) {
        $a = deg2rad($i * 360 / 11);
        $veins .= '<line x1="100" y1="68" x2="' . svgFmt(100 + 62 * cos($a)) . '" y2="' . svgFmt(68 + 18 * sin($a)) . '" stroke="#3b7f31" stroke-width="1" opacity=".55"/>';
    }
    $k['banana-leaf'] = $shadow . $pleatsBack
        . '<path d="M22 70 C22 100 58 114 100 114 C142 114 178 100 178 70Z" fill="#2c6a2d"/>'
        . '<ellipse cx="100" cy="68" rx="78" ry="26" fill="#58a540" stroke="#8ccb6b" stroke-width="1.5"/>'
        . '<ellipse cx="100" cy="68" rx="64" ry="19" fill="#6cb94f"/>' . $veins . $pleatsFront;

    // 2) กระทงดอกบัว — ทั้งใบเป็นทรงดอกบัวบาน
    $k['lotus'] = $shadow
        . '<path d="M100 92 C54 92 26 70 22 36 C52 44 88 62 100 92Z" fill="#f2a3c0" stroke="#d46c92" stroke-width="1.2"/>'
        . '<path d="M100 92 C146 92 174 70 178 36 C148 44 112 62 100 92Z" fill="#f2a3c0" stroke="#d46c92" stroke-width="1.2"/>'
        . '<path d="M100 92 C68 84 48 60 54 24 C82 42 98 64 100 92Z" fill="#f58cb4" stroke="#d46c92" stroke-width="1.2"/>'
        . '<path d="M100 92 C132 84 152 60 146 24 C118 42 102 64 100 92Z" fill="#f58cb4" stroke="#d46c92" stroke-width="1.2"/>'
        . '<path d="M100 96 C80 76 82 38 100 10 C118 38 120 76 100 96Z" fill="#ffd9e8" stroke="#dd7ba0" stroke-width="1.2"/>'
        . '<path d="M30 94 Q100 128 170 94 Q100 108 30 94Z" fill="#3a8546"/>'
        . '<path d="M100 114 C60 112 34 98 30 76 C60 80 92 92 100 114Z" fill="#f9bdd3" stroke="#d46c92" stroke-width="1.2"/>'
        . '<path d="M100 114 C140 112 166 98 170 76 C140 80 108 92 100 114Z" fill="#f9bdd3" stroke="#d46c92" stroke-width="1.2"/>'
        . '<path d="M100 116 C84 102 86 86 100 74 C114 86 116 102 100 116Z" fill="#fff0f6" stroke="#dd7ba0" stroke-width="1.2"/>';

    // 3) กระทงขนมปัง — ก้อนขนมปังอบสีทอง
    $dots = '';
    foreach ([[62, 66], [78, 76], [92, 60], [112, 78], [128, 62], [142, 72], [104, 66], [70, 58], [134, 80]] as [$x, $y]) {
        $dots .= '<circle cx="' . $x . '" cy="' . $y . '" r="1.3" fill="#c98a35" opacity=".7"/>';
    }
    $k['bread'] = $shadow
        . '<path d="M22 68 C22 100 58 114 100 114 C142 114 178 100 178 68Z" fill="#c58630" stroke="#9c6320" stroke-width="1"/>'
        . '<path d="M30 92 C60 108 140 108 170 92" fill="none" stroke="#e2a954" stroke-width="2" stroke-linecap="round" opacity=".6"/>'
        . '<ellipse cx="100" cy="66" rx="78" ry="27" fill="#e3ab55" stroke="#a96d22" stroke-width="1"/>'
        . '<ellipse cx="100" cy="64" rx="66" ry="20" fill="#f0c775"/>'
        . '<path d="M58 62 L76 50 M84 70 L104 54 M112 74 L134 58 M140 66 L152 58" stroke="#b97a28" stroke-width="3.5" stroke-linecap="round" opacity=".75"/>'
        . $dots
        . '<path d="M40 56 C52 44 74 38 96 38" fill="none" stroke="#fff3d0" stroke-width="3" stroke-linecap="round" opacity=".45"/>';

    // 4) กระทงกะลา — ครึ่งกะลามะพร้าว
    $fibres = '';
    foreach ([32, 52, 76, 100, 124, 148, 168] as $x) {
        $fibres .= '<path d="M' . $x . ' 72 Q' . ($x + ($x < 100 ? 2 : -2)) . ' 96 ' . (100 + ($x - 100) * 0.62) . ' 116" fill="none" stroke="#3f2412" stroke-width="1.4" opacity=".55" stroke-linecap="round"/>';
    }
    $k['coconut-shell'] = $shadow
        . '<path d="M22 66 C22 104 60 118 100 118 C140 118 178 104 178 66Z" fill="#6a3f22"/>' . $fibres
        . '<path d="M22 66 C22 104 60 118 100 118 C100 100 90 80 22 66Z" fill="#000" opacity=".12"/>'
        . '<ellipse cx="100" cy="66" rx="78" ry="25" fill="#8a5630" stroke="#4d2c15" stroke-width="1.2"/>'
        . '<ellipse cx="100" cy="66" rx="68" ry="19" fill="#f6edd8"/>'
        . '<ellipse cx="100" cy="68" rx="58" ry="14" fill="#e7d8b4"/>'
        . '<ellipse cx="100" cy="70" rx="48" ry="9" fill="#d7c294" opacity=".8"/>';

    // 5) กระทงหยวกกล้วย — ท่อนหยวกกลมประดับหมุด
    $rings = '';
    foreach ([57, 43, 29, 15] as $rx) {
        $rings .= '<ellipse cx="100" cy="70" rx="' . $rx . '" ry="' . svgFmt($rx * 0.34) . '" fill="none" stroke="#bfcd90" stroke-width="1.2"/>';
    }
    $stripes = '';
    foreach ([42, 58, 74, 90, 110, 126, 142, 158] as $x) {
        $stripes .= '<path d="M' . $x . ' 78 Q' . $x . ' 100 ' . svgFmt(100 + ($x - 100) * 0.7) . ' 118" fill="none" stroke="#aebd7c" stroke-width="1.1" opacity=".8"/>';
    }
    $pins = '';
    foreach ([[50, 94], [76, 103], [100, 106], [124, 103], [150, 94]] as [$x, $y]) {
        $pins .= '<circle cx="' . $x . '" cy="' . $y . '" r="2.6" fill="#e8c77e" stroke="#a8782a" stroke-width=".8"/>';
    }
    $k['banana-stem'] = $shadow
        . '<path d="M30 70 L30 98 C30 112 66 120 100 120 C134 120 170 112 170 98 L170 70Z" fill="#dfe8b8" stroke="#9fb06a" stroke-width="1.2"/>' . $stripes
        . '<path d="M30 92 C50 106 150 106 170 92" fill="none" stroke="#9fb06a" stroke-width="1.5"/>'
        . '<ellipse cx="100" cy="70" rx="70" ry="24" fill="#f3f7d9" stroke="#9fb06a" stroke-width="1.4"/>' . $rings . $pins;

    // 6) กระทงใบไม้ — ใบไม้เรียงเป็นดอก
    $leafShape = '<path d="M0 0 C20 -20 22 -62 0 -88 C-22 -62 -20 -20 0 0Z" fill="__F__" stroke="#255c28" stroke-width="1.2"/><path d="M0 -4 L0 -78" stroke="#a7d98a" stroke-width="1.6" opacity=".8"/>'
        . '<path d="M0 -24 L12 -38 M0 -24 L-12 -38 M0 -46 L10 -58 M0 -46 L-10 -58" stroke="#a7d98a" stroke-width="1" opacity=".6" fill="none"/>';
    $leafOrder = [[0, '#2e7432'], [45, '#33803a'], [315, '#33803a'], [90, '#3f9142'], [270, '#3f9142'], [135, '#4ba44a'], [225, '#4ba44a'], [180, '#5bb556']];
    $leaves = '';
    foreach ($leafOrder as [$angle, $fill]) {
        $leaves .= '<g transform="rotate(' . $angle . ')">' . str_replace('__F__', $fill, $leafShape) . '</g>';
    }
    $k['leaf'] = $shadow
        . '<path d="M24 72 C24 98 60 112 100 112 C140 112 176 98 176 72Z" fill="#245a28"/>'
        . '<g transform="translate(100 72) scale(1 .33)">' . $leaves . '</g>'
        . '<ellipse cx="100" cy="72" rx="12" ry="5" fill="#2e7432"/>';

    // 7) กระทงประดิษฐ์ — ชั้นม่วงประดับทองและเพชร
    $scallops = '';
    for ($i = 0; $i < 19; $i++) {
        $t = deg2rad(($i + 0.5) * 180 / 19);
        $scallops .= '<circle cx="' . svgFmt(100 + 79 * cos($t)) . '" cy="' . svgFmt(70 + 27 * sin($t)) . '" r="4.4" fill="#f0cf86" stroke="#a8782a" stroke-width=".8"/>';
    }
    $gems = '';
    foreach ([[40, 82, '#5fd3e3'], [70, 96, '#ff7ab6'], [100, 102, '#5fd3e3'], [130, 96, '#ff7ab6'], [160, 82, '#5fd3e3']] as [$x, $y, $c]) {
        $gems .= '<path d="M' . $x . ' ' . ($y - 6) . ' L' . ($x + 5) . ' ' . $y . ' L' . $x . ' ' . ($y + 6) . ' L' . ($x - 5) . ' ' . $y . 'Z" fill="' . $c . '" stroke="#fff" stroke-width=".8"/>';
    }
    $k['ornate'] = $shadow
        . '<path d="M20 76 C20 102 58 116 100 116 C142 116 180 102 180 76Z" fill="#5d2470" stroke="#3e154f" stroke-width="1"/>'
        . '<path d="M22 88 C50 108 150 108 178 88" fill="none" stroke="#e8c77e" stroke-width="2.4"/>'
        . '<ellipse cx="100" cy="70" rx="80" ry="27" fill="#85379a" stroke="#e8c77e" stroke-width="1.6"/>' . $scallops
        . '<ellipse cx="100" cy="70" rx="64" ry="20" fill="#f4dc96" stroke="#c99a45" stroke-width="1"/>'
        . '<ellipse cx="100" cy="70" rx="54" ry="15" fill="#fff0c2"/>' . $gems;

    // 8) กระทงกลีบบัว — ฐานใบไม้เรียงกลีบบัวพับ
    $petalBack = '';
    $petalFront = '';
    for ($i = 0; $i < 12; $i++) {
        $t = deg2rad(180 + ($i + 0.5) * 180 / 12);
        $x = 100 + 72 * cos($t);
        $y = 68 + 23 * sin($t);
        $petalBack .= '<path d="M' . svgFmt($x - 9) . ' ' . svgFmt($y + 3) . ' Q' . svgFmt($x - 11) . ' ' . svgFmt($y - 16) . ' ' . svgFmt($x) . ' ' . svgFmt($y - 26) . ' Q' . svgFmt($x + 11) . ' ' . svgFmt($y - 16) . ' ' . svgFmt($x + 9) . ' ' . svgFmt($y + 3) . 'Z" fill="#fbd0df" stroke="#d6668b" stroke-width=".9"/>';
        $t2 = deg2rad(($i + 0.5) * 180 / 12);
        $x2 = 100 + 72 * cos($t2);
        $y2 = 68 + 23 * sin($t2);
        $petalFront .= '<path d="M' . svgFmt($x2 - 10) . ' ' . svgFmt($y2 + 4) . ' Q' . svgFmt($x2 - 12) . ' ' . svgFmt($y2 - 15) . ' ' . svgFmt($x2) . ' ' . svgFmt($y2 - 25) . ' Q' . svgFmt($x2 + 12) . ' ' . svgFmt($y2 - 15) . ' ' . svgFmt($x2 + 10) . ' ' . svgFmt($y2 + 4) . 'Z" fill="#fff4f8" stroke="#d6668b" stroke-width=".9"/>'
            . '<path d="M' . svgFmt($x2) . ' ' . svgFmt($y2 - 22) . ' Q' . svgFmt($x2 + 4) . ' ' . svgFmt($y2 - 14) . ' ' . svgFmt($x2 + 3) . ' ' . svgFmt($y2 - 2) . '" fill="none" stroke="#f08cab" stroke-width="1.6" stroke-linecap="round" opacity=".8"/>';
    }
    $k['lotus-petals'] = $shadow . $petalBack
        . '<path d="M24 72 C24 100 60 114 100 114 C140 114 176 100 176 72Z" fill="#37805a"/>'
        . '<ellipse cx="100" cy="70" rx="76" ry="25" fill="#5eb07c" stroke="#9fd8b2" stroke-width="1.4"/>'
        . '<ellipse cx="100" cy="70" rx="62" ry="18" fill="#76c493"/>'
        . '<ellipse cx="100" cy="70" rx="76" ry="25" fill="none" stroke="#e8c77e" stroke-width="1.2" stroke-dasharray="3 4" opacity=".9"/>'
        . $petalFront;

    // 9) กระทงกรวยใบตอง — กรวยใบตองพับตั้งเรียงรอบ
    $coneBack = '';
    $coneFront = '';
    for ($i = 0; $i < 7; $i++) {
        $x = 38 + $i * 20.7;
        $y = 60 + 4 * abs(sin(M_PI * $i / 6));
        $coneBack .= '<path d="M' . svgFmt($x - 10) . ' ' . svgFmt($y + 4) . ' L' . svgFmt($x) . ' ' . svgFmt($y - 36) . ' L' . svgFmt($x + 10) . ' ' . svgFmt($y + 4) . 'Z" fill="#3c8a3a" stroke="#225522" stroke-width=".9"/>'
            . '<path d="M' . svgFmt($x) . ' ' . svgFmt($y - 36) . ' L' . svgFmt($x + 2) . ' ' . svgFmt($y + 4) . '" stroke="#7dc25a" stroke-width="1.2" opacity=".8"/>';
    }
    for ($i = 0; $i < 6; $i++) {
        $x = 49 + $i * 20.4;
        $y = 90 + 6 * sin(M_PI * $i / 5);
        $coneFront .= '<path d="M' . svgFmt($x - 12) . ' ' . svgFmt($y + 6) . ' L' . svgFmt($x) . ' ' . svgFmt($y - 40) . ' L' . svgFmt($x + 12) . ' ' . svgFmt($y + 6) . 'Z" fill="' . ($i % 2 ? '#58a845' : '#69b84f') . '" stroke="#245a24" stroke-width="1"/>'
            . '<path d="M' . svgFmt($x) . ' ' . svgFmt($y - 40) . ' L' . svgFmt($x - 3) . ' ' . svgFmt($y + 6) . '" stroke="#a6dc86" stroke-width="1.3" opacity=".85"/>'
            . '<path d="M' . svgFmt($x - 8) . ' ' . svgFmt($y - 8) . ' L' . svgFmt($x + 9) . ' ' . svgFmt($y - 14) . '" stroke="#2f7a2f" stroke-width=".9" opacity=".7"/>';
    }
    $k['banana-cone'] = $shadow
        . '<path d="M22 76 C22 102 58 116 100 116 C142 116 178 102 178 76Z" fill="#2a6429"/>'
        . '<ellipse cx="100" cy="72" rx="78" ry="26" fill="#4a9a3d" stroke="#2f6f2d" stroke-width="1.2"/>'
        . $coneBack
        . '<ellipse cx="100" cy="76" rx="56" ry="15" fill="#3f8a38" opacity=".9"/>'
        . $coneFront;

    // 10) กระทงประทีป — ฐานไม้ประดับตะเกียงเล็ก ๆ เรืองแสง
    $lamps = '';
    foreach ([[34, 72], [166, 72], [56, 92], [144, 92], [100, 98]] as [$x, $y]) {
        $lamps .= '<circle cx="' . $x . '" cy="' . ($y - 8) . '" r="13" fill="url(#gradFlame)"/>'
            . '<path d="M' . ($x - 7) . ' ' . ($y - 2) . ' Q' . $x . ' ' . ($y + 7) . ' ' . ($x + 7) . ' ' . ($y - 2) . 'Z" fill="#b5651d" stroke="#6d3a0e" stroke-width=".8"/>'
            . '<path d="M' . $x . ' ' . ($y - 3) . ' C' . ($x - 4.5) . ' ' . ($y - 9) . ' ' . $x . ' ' . ($y - 14) . ' ' . $x . ' ' . ($y - 19) . ' C' . $x . ' ' . ($y - 14) . ' ' . ($x + 4.5) . ' ' . ($y - 9) . ' ' . $x . ' ' . ($y - 3) . 'Z" fill="#ffb12e"/>'
            . '<path d="M' . $x . ' ' . ($y - 4) . ' C' . ($x - 2) . ' ' . ($y - 8) . ' ' . $x . ' ' . ($y - 11) . ' ' . $x . ' ' . ($y - 13) . ' C' . $x . ' ' . ($y - 11) . ' ' . ($x + 2) . ' ' . ($y - 8) . ' ' . $x . ' ' . ($y - 4) . 'Z" fill="#fff3b0"/>';
    }
    $k['pratheep'] = $shadow
        . '<ellipse cx="100" cy="62" rx="92" ry="54" fill="url(#gradFlame)" opacity=".55"/>'
        . '<path d="M22 72 C22 102 60 116 100 116 C140 116 178 102 178 72Z" fill="#552a18" stroke="#33170b" stroke-width="1"/>'
        . '<path d="M26 90 C56 108 144 108 174 90" fill="none" stroke="#e8c77e" stroke-width="2"/>'
        . '<ellipse cx="100" cy="72" rx="78" ry="26" fill="#7b4224" stroke="#e8c77e" stroke-width="1.6"/>'
        . '<ellipse cx="100" cy="72" rx="64" ry="19" fill="#3d1d10"/>'
        . '<ellipse cx="100" cy="72" rx="64" ry="19" fill="none" stroke="#e8c77e" stroke-width=".8" stroke-dasharray="2 4" opacity=".8"/>'
        . $lamps;

    return $k;
}

/** ชื่อ svg_key ทั้งหมด (ใช้ตรวจสอบความถูกต้อง) */
function svgFlowerKeys(): array
{
    return array_keys(svgFlowerSymbols());
}

function svgKrathongKeys(): array
{
    return array_keys(svgKrathongSymbols());
}

/**
 * ดอกไม้สำหรับวางบนกระทง (id = "fk-{svg_key}") — ปรับจากดอกชุดเดิมให้เข้ากับมุมมองเฉียงของกระทง:
 * ย่อแนวตั้ง, มีเงาใต้ดอก, ไฮไลต์ให้ดูนูน, และมีใบเขียวรองด้านหลังสำหรับดอกที่ไม่มีใบ
 */
function svgFlowerOnKrathongSymbols(): array
{
    $hasLeaves = ['gardenia', 'ixora', 'orange-jasmine', 'lotus'];
    $standing = ['lotus', 'orchid'];          // วาดเป็นมุมข้างอยู่แล้ว ไม่ต้องย่อ
    $domed = ['rose', 'ixora', 'globe-amaranth', 'marigold', 'chrysanthemum'];
    $leaf = '<ellipse cx="14" cy="42" rx="11" ry="5" transform="rotate(-28 14 42)" fill="#2f7a3c" stroke="#1f5a2b" stroke-width=".6"/>'
        . '<ellipse cx="50" cy="42" rx="11" ry="5" transform="rotate(28 50 42)" fill="#3b9148" stroke="#1f5a2b" stroke-width=".6"/>'
        . '<ellipse cx="32" cy="50" rx="9" ry="4" fill="#2f7a3c" stroke="#1f5a2b" stroke-width=".6"/>';
    $out = [];
    foreach (svgFlowerSymbols() as $key => $body) {
        $shadow = '<ellipse cx="32" cy="53" rx="23" ry="6" fill="#021311" opacity=".38"/>';
        $under = in_array($key, $hasLeaves, true) ? '' : $leaf;
        if (in_array($key, $standing, true)) {
            $flower = '<g transform="translate(3.2 4) scale(.9)">' . $body . '</g>';
        } else {
            $hi = in_array($key, $domed, true)
                ? '<circle cx="32" cy="32" r="24" fill="url(#gradHi)"/>' : '';
            $flower = '<g transform="translate(0 7) scale(1 .74)">' . $body . $hi . '</g>';
        }
        $out[$key] = $shadow . $under . $flower;
    }
    return $out;
}

/** พิมพ์ sprite ซ่อนไว้ในหน้า (ใช้ width/height 0 ไม่ใช้ display:none เพื่อให้ gradient ทำงาน) */
function renderSvgSprite(): void
{
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>';
    echo '<radialGradient id="gradPlumeria"><stop offset="0" stop-color="#ffc42e"/><stop offset=".55" stop-color="#ffe58a"/><stop offset="1" stop-color="#fffaf2" stop-opacity="0"/></radialGradient>';
    echo '<radialGradient id="gradFlame"><stop offset="0" stop-color="#ffd36b" stop-opacity=".85"/><stop offset="1" stop-color="#ff9a2e" stop-opacity="0"/></radialGradient>';
    echo '<linearGradient id="gradCandle" x1="0" x2="1"><stop offset="0" stop-color="#f4dfae"/><stop offset=".5" stop-color="#fff6dc"/><stop offset="1" stop-color="#e8cf96"/></linearGradient>';
    echo '<radialGradient id="gradHi" cx=".38" cy=".3" r=".7"><stop offset="0" stop-color="#ffffff" stop-opacity=".42"/><stop offset=".5" stop-color="#ffffff" stop-opacity=".08"/><stop offset="1" stop-color="#000000" stop-opacity=".16"/></radialGradient>';
    echo '</defs>';
    foreach (svgFlowerOnKrathongSymbols() as $key => $body) {
        echo '<symbol id="fk-' . $key . '" viewBox="0 0 64 64">' . $body . '</symbol>';
    }
    foreach (svgFlowerSymbols() as $key => $body) {
        echo '<symbol id="fl-' . $key . '" viewBox="0 0 64 64">' . $body . '</symbol>';
    }
    foreach (svgKrathongSymbols() as $key => $body) {
        echo '<symbol id="kt-' . $key . '" viewBox="0 0 200 130">' . $body . '</symbol>';
    }
    echo '</svg>';
}

/** ไอคอนดอกบัวเล็ก ๆ ใช้เป็นโลโก้/ลายประดับ (แยกอิสระ ไม่ต้องพึ่ง sprite) */
function svgLotusIcon(string $class = 'lotus-icon'): string
{
    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 64 64" aria-hidden="true" focusable="false">'
        . '<path d="M32 58 C14 55 5 41 7 26 C19 32 28 43 32 58Z" fill="currentColor" opacity=".55"/>'
        . '<path d="M32 58 C50 55 59 41 57 26 C45 32 36 43 32 58Z" fill="currentColor" opacity=".55"/>'
        . '<path d="M32 58 C19 51 13 36 17 16 C27 26 32 41 32 58Z" fill="currentColor" opacity=".8"/>'
        . '<path d="M32 58 C45 51 51 36 47 16 C37 26 32 41 32 58Z" fill="currentColor" opacity=".8"/>'
        . '<path d="M32 60 C21 49 23 26 32 6 C41 26 43 49 32 60Z" fill="currentColor"/>'
        . '</svg>';
}
