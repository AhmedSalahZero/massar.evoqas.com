<?php

namespace App\Support;

// ══════════════════════════════════════════════════════════════════
//  Massar — ScreenWords (Step 14)
//  Location: app/Support/ScreenWords.php
//
//  The screens' own words (resources/js/lang/translations.js), read on
//  the server, so an Excel or PDF report names a governorate, an
//  education level or a gender EXACTLY as the screen does, in English
//  and Arabic, without keeping a second copy of every name.
//
//      ScreenWords::get('gov.cai', 'ar')   → 'القاهرة'
//
//  Only simple one-line entries ('key': 'text') are read. A missing
//  Arabic word falls back to English, then to the key itself.
// ══════════════════════════════════════════════════════════════════

class ScreenWords
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $words = null;

    public static function get(string $key, string $locale = 'en'): string
    {
        $all = self::load();

        return $all[$locale][$key] ?? $all['en'][$key] ?? $key;
    }

    /** @return array<string, array<string, string>> */
    private static function load(): array
    {
        if (self::$words !== null) {
            return self::$words;
        }
        $path = resource_path('js/lang/translations.js');
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $arAt = strpos($src, "\n    ar: {");
        $parts = ['en' => $arAt === false ? $src : substr($src, 0, $arAt), 'ar' => $arAt === false ? '' : substr($src, $arAt)];

        self::$words = [];
        foreach ($parts as $locale => $text) {
            preg_match_all("/'([a-z_]+(?:\\.[a-z0-9_]+)+)':\\s*'((?:[^'\\\\]|\\\\.)*)'/", $text, $m, PREG_SET_ORDER);
            foreach ($m as [, $key, $value]) {
                self::$words[$locale][$key] ??= stripcslashes($value);
            }
        }

        return self::$words;
    }
}
