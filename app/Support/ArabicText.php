<?php

namespace App\Support;

/**
 * Normalises Arabic text for searching, so that "احمد" finds "أحمد",
 * "جبنه" finds "جبنة" and diacritics / tatweel are ignored.
 */
final class ArabicText
{
    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Harakat, tanween, shadda, sukun, superscript alef and tatweel.
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;

        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه',
            'ى' => 'ي',
            'ؤ' => 'و',
            'ئ' => 'ي',
        ]);

        $text = mb_strtolower($text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
