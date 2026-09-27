<?php

namespace App\Services\Cv;

// ══════════════════════════════════════════════════════════════════
//  Massar — ArabicRepair
//  Location: app/Services/Cv/ArabicRepair.php
//
//  Some PDF makers write the Arabic letter pair "لا" (and لأ لإ لآ) in
//  the wrong order, so the text reads "ال" instead:
//      الإنجليزية → اإلنجليزية      الأم → األم      الآن → اآلن
//      الاجتماعية → االجتماعية      الميلاد → الميالد
//
//  repair() fixes only the cases that are always safe — the article
//  "ال" in front of ا أ إ آ at the START of a word, because no real
//  word starts with "اإل", "األ", "اآل" or "اال". Letters inside a
//  word ("الميالد") cannot be fixed safely ("أعمال" is a real word),
//  so the reader's dictionaries also accept the broken spellings
//  (variants()), and 'true' is returned so that Arabic NAMES from such
//  a file are marked "Check" (علاء might show as عالء).
// ══════════════════════════════════════════════════════════════════

final class ArabicRepair
{
    /** @return array{0: string, 1: bool} repaired text, whether the problem was seen */
    public static function repair(string $text): array
    {
        $seen = false;
        $text = preg_replace_callback('/(?<![\p{Arabic}])ا([اأإآ])ل(?=\p{Arabic})/u', function ($m) use (&$seen) {
            $seen = true;

            return 'ال'.$m[1];
        }, $text) ?? $text;

        return [$text, $seen];
    }

    /**
     * A dictionary phrase plus its "broken" spelling, so both are
     * recognised: 'تاريخ الميلاد' → ['تاريخ الميلاد', 'تاريخ الميالد'].
     *
     * @return list<string>
     */
    public static function variants(string $phrase): array
    {
        $broken = preg_replace('/ل([اأإآ])/u', '$1ل', $phrase) ?? $phrase;
        [$brokenRepaired] = self::repair($broken);

        return array_values(array_unique([$phrase, $broken, $brokenRepaired]));
    }
}
