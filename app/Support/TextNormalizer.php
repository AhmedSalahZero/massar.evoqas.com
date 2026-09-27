<?php

namespace App\Support;

// ══════════════════════════════════════════════════════════════════
//  Massar — TextNormalizer
//  Location: app/Support/TextNormalizer.php
//
//  Turns any job title or search text into one comparable form, so
//  the way something is typed does not decide whether it is found.
//  Used for the occupation labels now, and by CV reading and search
//  later — ONE rule set everywhere, or matches drift apart.
//
//      TextNormalizer::normalize('المُحاسِبة  القانونيّة') → 'محاسبه قانونيه'
//      TextNormalizer::normalize('Senior  Accountant.')  → 'senior accountant'
//
//  Arabic (light normalisation, the usual choice for search):
//    · removes tashkeel (ـَ ـُ ـِ ـّ …) and tatweel (ـ)
//    · أ إ آ ٱ → ا     ى → ي     ة → ه     ؤ → و     ئ → ي
//    · drops the article "ال" at the start of a word, so
//      "المدير الفني" and "مدير فني" match. Only a bare-alef "ال"
//      followed by at least two letters is removed — "ألوان" and
//      "آلة" are left alone.
//    · Arabic-Indic digits ٠-٩ / ۰-۹ → 0-9
//  English: lower case.
//  Both: punctuation → space, repeated spaces collapsed.
// ══════════════════════════════════════════════════════════════════

final class TextNormalizer
{
    private const DIACRITICS = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u';

    private const LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = preg_replace(self::DIACRITICS, '', $text) ?? $text;
        $text = mb_strtolower($text, 'UTF-8');

        // Punctuation and symbols → space (letters and digits of any script stay).
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        // The article is removed BEFORE hamza forms are unified, so only a
        // real bare-alef "ال" counts (see header).
        $words = array_filter(explode(' ', $text), fn ($w) => $w !== '');
        $words = array_map(
            fn ($w) => (mb_substr($w, 0, 2) === 'ال' && mb_strlen($w) >= 4) ? mb_substr($w, 2) : $w,
            $words,
        );

        return strtr(implode(' ', $words), self::LETTERS);
    }

    /**
     * The words of a search text, normalised, without duplicates.
     *
     * @return list<string>
     */
    public static function tokens(?string $text): array
    {
        $normalized = self::normalize($text);

        return $normalized === '' ? [] : array_values(array_unique(explode(' ', $normalized)));
    }
}
