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
            'ควย', 'เย็ด', 'ฉิบหาย', 'ชิบหาย', 'ตอแหล', 'อีตัว', 'ระยำ', 'เสือก', 'ส้นตีน', 'เงี่ยน',
            'จัญไร', 'ปากหมา', 'ชาติหมา', 'สันดานหมา', 'หน้าหี', 'รูหี', 'ไอ้สัส', 'อีสัส', 'ไอ้สัตว์',
            'อีสัตว์', 'ไอ้เวร', 'ฟัค', 'ฟักกิ้ง',
            'fuck', 'fuk', 'fck', 'fuq', 'phuck', 'bitch', 'bastard', 'asshole', 'dumbass', 'jackass',
            'shit', 'whore', 'slut', 'nigger', 'nigga', 'faggot',
        ],
        // ค้นหาแบบ "ปรากฏอยู่ที่ใดก็ได้" โดยคงวรรณยุกต์ไว้ (คำที่ถ้าตัดวรรณยุกต์จะไปซ้ำกับคำปกติ เช่น เหี่ยว/เหี้ย)
        'strict' => [
            'เหี้ย', 'แม่ง',
        ],
        // ต้องเป็นคำเดี่ยว ๆ เท่านั้น (คั่นด้วยช่องว่าง/เครื่องหมาย หรือเป็นทั้งข้อความ)
        'token' => [
            'หี', 'ห่า', 'สัด', 'สัส', 'แตด', 'เหีย', 'ไอ้ห่า', 'ไอ้สัด',
            'ass', 'dick', 'cock', 'cunt', 'cunts', 'pussy', 'piss', 'fag',
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
    // ตัวเลข/สัญลักษณ์แทนตัวอักษรที่อยู่ติดกับตัวอักษรละติน เช่น sh1t, b!tch, @ss
    $leet = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '!' => 'i'];
    $text = (string) preg_replace_callback(
        '/(?<=\p{Latin}|[0134578@$!])[0134578@$!](?=\p{Latin}|[0134578@$!])/u',
        static fn(array $m): string => $leet[$m[0]],
        $text
    );
    $text = (string) preg_replace_callback(
        '/(?<=\p{Latin})[0134578@$!]|[0134578@$!](?=\p{Latin})/u',
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

/** @return array{strict:string, loose:string, tokens:string[]} */
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

    return [
        'strict' => $view,
        'loose' => profanityCollapse(profanityStripMarks($view)),
        'tokens' => $tokens,
    ];
}

function containsProfanity(string $text): bool
{
    static $prepared = null;
    if ($prepared === null) {
        $lists = profanityWordLists();
        $prepared = ['loose' => [], 'strict' => [], 'token' => []];
        foreach ($lists['loose'] as $word) {
            $prepared['loose'][] = profanityCollapse(profanityStripMarks(profanityBaseNormalize($word)));
        }
        foreach ($lists['strict'] as $word) {
            $prepared['strict'][] = profanityCollapse(profanityBaseNormalize($word));
        }
        foreach ($lists['token'] as $word) {
            $prepared['token'][] = profanityCollapse(profanityBaseNormalize($word));
        }
    }

    if (trim($text) === '') {
        return false;
    }

    $views = profanityViews($text);
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
