<?php
declare(strict_types=1);

/**
 * ตัวกรองคำหยาบ (ไทย + อังกฤษ)
 *
 * หลักการ: ภาษาไทยไม่เว้นวรรคระหว่างคำ จึงต้องค้นหาแบบ "มีคำนี้อยู่ในข้อความ" แต่คำสั้น ๆ ที่เป็นคำปกติด้วย
 * (เช่น ห่า/ห่าง, สัด/สัดส่วน, แมง/แม่ง) จะตรวจแบบ "ต้องเป็นคำเดี่ยว ๆ" เท่านั้น เพื่อลดการตัดสินผิด
 *
 * จัดการการหลบเลี่ยงที่พบบ่อย: ตัวอักษรซ้ำ (ควยยยย), เว้นวรรคคั่น (ค ว ย / f u c k),
 * ใส่สัญลักษณ์คั่น (f*ck), ตัวเลขแทนตัวอักษร (sh1t), ตัวอักษรล่องหน (zero-width), ไม่ใส่วรรณยุกต์
 *
 * เพิ่ม/ลบคำได้ที่ profanityWordLists() ด้านล่าง
 */

function profanityWordLists(): array
{
    return [
        // ค้นหาแบบ "ปรากฏอยู่ที่ใดก็ได้ในข้อความ" โดยตัดวรรณยุกต์ออกก่อนเทียบ (จับกรณีลืมใส่/ใส่วรรณยุกต์ผิด)
        'loose' => [
            // ไทย: คำหยาบ/ทางเพศ
            'ควย', 'เย็ด', 'ฉิบหาย', 'ชิบหาย', 'ฉิบหาย', 'ตอแหล', 'อีตัว', 'ระยำ', 'เสือก', 'ส้นตีน', 'เงี่ยน',
            'จัญไร', 'ปากหมา', 'ชาติหมา', 'สันดานหมา', 'หน้าหี', 'รูหี', 'ไอ้สัส', 'อีสัส', 'ไอ้สัตว์',
            'อีสัตว์', 'ไอ้เวร', 'ฟัค', 'ฟักกิ้ง', 'กระหรี่', 'กะหรี่', 'อีดอก', 'ดอกทอง', 'อีเวร', 'หน้าด้าน',
            'หน้าตัวเมีย', 'ขายตัว', 'เสพสม', 'อมควย', 'ดูดควย', 'ตีนกู', 'ไอ้ตีน', 'ไอ้ห่า', 'ห่าเหว',
            // ไทย: ด่า/ดูถูก/แช่ง
            'มึง', 'ไอ้ควาย', 'อีควาย', 'ไอ้โง่', 'โง่เง่า', 'ไอ้บ้า', 'ไอ้เลว', 'ไอ้ชั่ว', 'ชาติชั่ว', 'ชาติเลว',
            'ปัญญาอ่อน', 'สมองหมา', 'สถุล', 'สถุน', 'ไอ้โรคจิต', 'ไอ้หน้า', 'อีหน้า', 'ไอ้ขี้', 'อีขี้',
            'ไปตาย', 'ตายห่า', 'ตายโหง', 'ตายซะ', 'ขอให้ตาย', 'ไปลงนรก', 'ไปลงหลุม',
            // อังกฤษ
            'fuck', 'fuk', 'fck', 'fuq', 'fvck', 'phuck', 'bitch', 'bastard', 'asshole', 'dumbass', 'jackass',
            'shit', 'whore', 'slut', 'nigger', 'nigga', 'nigg', 'faggot', 'tranny', 'dickhead', 'cocksucker',
            'blowjob', 'handjob', 'jerkoff', 'wanker', 'bollocks', 'motherfuck', 'pornhub', 'xvideos', 'retard',
            // ไทยทับศัพท์อังกฤษที่พบบ่อย
            'aisat', 'aihia', 'eesat', 'eetua',
        ],
        // ค้นหาแบบ "ปรากฏอยู่ที่ใดก็ได้" โดยคงวรรณยุกต์ไว้ (คำที่ถ้าตัดวรรณยุกต์จะไปซ้ำกับคำปกติ เช่น เหี่ยว/เหี้ย)
        'strict' => [
            'เหี้ย', 'แม่ง',
        ],
        // ต้องเป็นคำเดี่ยว ๆ เท่านั้น (คั่นด้วยช่องว่าง/เครื่องหมาย หรือเป็นทั้งข้อความ)
        'token' => [
            'หี', 'ห่า', 'สัด', 'สัส', 'แตด', 'เหีย', 'ไอ้ห่า', 'ไอ้สัด', 'กู', 'หำ', 'จิ๋ม', 'จู๋', 'หมอย',
            'ควาย', 'แรด', 'ร่าน', 'โคตร', 'ไอ้', 'อี',
            'ass', 'dick', 'cock', 'cunt', 'cunts', 'pussy', 'piss', 'pissed', 'fag', 'twat', 'prick', 'arse',
            'tit', 'tits', 'boob', 'boobs', 'porn', 'sex', 'cum', 'anal', 'nude', 'nudes', 'rape', 'rapist', 'nazi',
            'hoe', 'dyke', 'kike', 'spic', 'chink', 'gook', 'coon', 'mofo', 'kys', 'stfu', 'wtf', 'crap', 'damn', 'hell',
        ],
        // ตัดช่องว่าง/สัญลักษณ์ออกทั้งหมดแล้วค้นหา (จับ "fu ck", "b i t c h") ใช้เฉพาะคำที่ไม่ไปซ้ำกับคำปกติเมื่อเอามาติดกัน
        'compact' => [
            'fuck', 'bitch', 'nigger', 'nigga', 'faggot', 'motherfucker', 'cocksucker',
        ],
        // คำปกติที่มีคำหยาบเป็นส่วนหนึ่ง ตัดทิ้งก่อนตรวจ เพื่อไม่ให้ตัดสินผิด
        'allow' => [
            'เหี้ยม', 'แม่งาน', 'แม่งู', 'แม่งั้น',
        ],
    ];
}

/** ตัดเครื่องหมายวรรณยุกต์/ไม้ไต่คู้/การันต์ (่ ้ ๊ ๋ ็ ์) */
function profanityStripMarks(string $text): string
{
    return (string) preg_replace('/[\x{0E47}-\x{0E4C}]/u', '', $text);
}

/** ยุบตัวอักษรซ้ำ: ภาษาไทยซ้ำ 2 ตัวขึ้นไปให้เหลือตัวเดียว, อังกฤษซ้ำ 3 ตัวขึ้นไปให้เหลือตัวเดียว */
function profanityCollapse(string $text): string
{
    $text = (string) preg_replace('/(\p{Thai})\1+/u', '$1', $text);
    return (string) preg_replace('/(.)\1{2,}/us', '$1', $text);
}

function profanityBaseNormalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($text, Normalizer::FORM_KC);
        if (is_string($normalized)) {
            $text = $normalized;
        }
    }
    // ตัวอักษรล่องหน
    $text = (string) preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00AD}]/u', '', $text);
    // ไม้ยมก (ๆ) ไม่มีผลต่อความหมายของคำหยาบ: ตัดทิ้งกันการแทรกกลางคำ
    $text = (string) preg_replace('/[\x{0E46}]/u', '', $text);
    // พิมพ์วรรณยุกต์ก่อนสระ (เห้ีย) → เรียงใหม่ให้เป็นสระก่อนวรรณยุกต์ (เหี้ย) ไม่ให้หลบตัวกรองด้วยลำดับการพิมพ์
    $text = (string) preg_replace('/([\x{0E48}-\x{0E4B}])([\x{0E31}\x{0E34}-\x{0E3A}])/u', '$2$1', $text);
    // ตัวอักษรซีริลลิก/กรีกที่หน้าตาเหมือนละติน (fսck, ѕhit) → ละติน
    $text = strtr($text, [
        'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'х' => 'x', 'у' => 'y', 'і' => 'i', 'ѕ' => 's', 'ј' => 'j', 'ƒ' => 'f',
        'α' => 'a', 'ε' => 'e', 'ο' => 'o', 'ι' => 'i', 'υ' => 'u', 'ν' => 'v', 'κ' => 'k', 'ρ' => 'p', 'τ' => 't',
    ]);
    // ตัวเลข/สัญลักษณ์แทนตัวอักษรที่อยู่ติดกับตัวอักษรละติน เช่น sh1t, b!tch, @ss
    $leet = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '8' => 'b', '9' => 'g', '@' => 'a', '$' => 's', '!' => 'i'];
    $text = (string) preg_replace_callback(
        '/(?<=\p{Latin}|[01345789@$!])[01345789@$!](?=\p{Latin}|[01345789@$!])/u',
        static fn(array $m): string => $leet[$m[0]],
        $text
    );
    $text = (string) preg_replace_callback(
        '/(?<=\p{Latin})[01345789@$!]|[01345789@$!](?=\p{Latin})/u',
        static fn(array $m): string => $leet[$m[0]],
        $text
    );
    // ตัวอักษรละตินเดี่ยว ๆ คั่นด้วยช่องว่าง/จุด/ขีด เช่น "f u c k", "s.h.i.t"
    $text = (string) preg_replace_callback(
        '/(?<![\p{L}\p{N}])(?:\p{Latin}[\s.\-_*]+){2,}\p{Latin}(?![\p{L}\p{N}])/u',
        static fn(array $m): string => (string) preg_replace('/[\s.\-_*]+/u', '', $m[0]),
        $text
    );
    // สัญลักษณ์คั่นกลางคำ เช่น f*ck
    $text = (string) preg_replace('/(?<=\p{Latin})[*._\-\x{2022}]+(?=\p{Latin})/u', '', $text);
    return $text;
}

/** @return array{strict:string, loose:string, tokens:string[], compact:string} */
function profanityViews(string $text): array
{
    $base = profanityBaseNormalize($text);

    // ข้อความไทยที่ถูกคั่นด้วยช่องว่าง/เครื่องหมายทีละตัวอักษร เช่น "ค ว ย" → "ควย"
    $joined = (string) preg_replace('/(?<=\p{Thai})[\s\p{P}\p{S}]+(?=\p{Thai})/u', '', $base);
    // ภาษาอังกฤษยังคงเว้นวรรคระหว่างคำไว้ (เช่น "this hit" จะไม่ถูกต่อเป็น "shit")
    $view = profanityCollapse((string) preg_replace('/[^\p{L}\p{M}\p{N}\s]+/u', ' ', $joined));
    $view = trim((string) preg_replace('/\s+/u', ' ', $view));

    $tokens = array_values(array_filter(
        preg_split('/[^\p{L}\p{M}\p{N}]+/u', profanityCollapse($base)) ?: [],
        static fn($token) => $token !== ''
    ));

    // ละตินล้วน ตัดทุกอย่างที่ไม่ใช่ a-z ออก เช่น "f u-ck" → "fuck"
    $compact = profanityCollapse((string) preg_replace('/[^a-z]+/', '', $base));

    return [
        'strict' => $view,
        'loose' => profanityCollapse(profanityStripMarks($view)),
        'tokens' => $tokens,
        'compact' => $compact,
    ];
}

function containsProfanity(string $text): bool
{
    static $prepared = null;
    if ($prepared === null) {
        $lists = profanityWordLists();
        $prepared = ['loose' => [], 'strict' => [], 'token' => [], 'compact' => [], 'allow' => []];
        foreach ($lists['loose'] as $word) {
            $prepared['loose'][] = profanityCollapse(profanityStripMarks(profanityBaseNormalize($word)));
        }
        foreach ($lists['strict'] as $word) {
            $prepared['strict'][] = profanityCollapse(profanityBaseNormalize($word));
        }
        foreach ($lists['token'] as $word) {
            $prepared['token'][] = profanityCollapse(profanityBaseNormalize($word));
        }
        foreach ($lists['compact'] as $word) {
            $prepared['compact'][] = profanityCollapse($word);
        }
        foreach ($lists['allow'] as $word) {
            $prepared['allow'][] = profanityCollapse(profanityBaseNormalize($word));
        }
    }

    if (trim($text) === '') {
        return false;
    }

    $views = profanityViews($text);
    // ตัดคำปกติที่บังเอิญมีคำหยาบอยู่ข้างใน (เช่น เหี้ยม, แม่งาน) ออกจากการตรวจแบบ "อยู่ตรงไหนก็ได้"
    foreach ($prepared['allow'] as $ok) {
        if ($ok === '') {
            continue;
        }
        $views['strict'] = str_replace($ok, ' ', $views['strict']);
        $views['loose'] = str_replace(profanityCollapse(profanityStripMarks($ok)), ' ', $views['loose']);
    }
    foreach ($prepared['compact'] as $word) {
        if (str_contains($views['compact'], $word)) {
            return true;
        }
    }
    foreach ($prepared['strict'] as $word) {
        if ($word !== '' && str_contains($views['strict'], $word)) {
            return true;
        }
    }
    foreach ($prepared['loose'] as $word) {
        if ($word !== '' && str_contains($views['loose'], $word)) {
            return true;
        }
    }
    foreach ($views['tokens'] as $token) {
        if (in_array($token, $prepared['token'], true)) {
            return true;
        }
    }
    return false;
}
