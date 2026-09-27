<?php

namespace App\Services\Backbone;

use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — SkillSearch
//  Location: app/Services/Backbone/SkillSearch.php
//
//      ->skills('محاسبة')   ESCO skills (id => best match), best first
//
//  Same rules as OccupationSearch, so both searches behave alike: the
//  text is normalised (TextNormalizer — Arabic with or without "ال",
//  hamza forms, tashkeel), every word must appear in a name, and each
//  skill is ranked by its best name: exact, starts with the words,
//  contains the words, starts with the text, a word that starts with
//  the text, anywhere; official names before alternative ones; shorter
//  before longer. (Whole words rank first, so "excel" finds the
//  spreadsheet skill before "demand excellence".) Searches the ~115,000 skill names in
//  skill_labels. Only active skills are returned.
// ══════════════════════════════════════════════════════════════════

class SkillSearch
{
    private const CANDIDATES = 4000;

    /** @return array<int, array{label: string, lang: string, kind: string, score: float}> */
    public function skills(string $text, ?string $type = null): array
    {
        $tokens = TextNormalizer::tokens($text);
        if (! $tokens) {
            return [];
        }
        $phrase = implode(' ', $tokens);

        $query = DB::table('skill_labels')
            ->join('esco_skills', 'esco_skills.id', '=', 'skill_labels.skill_id')
            ->where('esco_skills.is_active', true)
            ->select(['skill_labels.skill_id', 'skill_labels.lang', 'skill_labels.kind', 'skill_labels.label', 'skill_labels.normalized']);
        foreach ($tokens as $token) {
            $query->where('skill_labels.normalized', 'like', '%'.$token.'%');
        }
        if ($type !== null) {
            $query->where('esco_skills.type', $type);
        }

        $best = [];
        foreach ($query->limit(self::CANDIDATES)->cursor() as $row) {
            $score = $this->score($row->normalized, $phrase, $tokens[0], $row->kind);
            if (! isset($best[$row->skill_id]) || $score < $best[$row->skill_id]['score']) {
                $best[$row->skill_id] = ['label' => $row->label, 'lang' => $row->lang, 'kind' => $row->kind, 'score' => $score];
            }
        }

        uasort($best, fn ($a, $b) => $a['score'] <=> $b['score']);

        return $best;
    }

    private function score(string $normalized, string $phrase, string $first, string $kind): float
    {
        $score = match (true) {
            $normalized === $phrase                   => 0,
            str_starts_with($normalized, $phrase.' ') => 10,
            str_contains(' '.$normalized.' ', ' '.$phrase.' ') => 12,   // the whole words: "use excel"
            str_starts_with($normalized, $phrase)     => 14,
            str_contains(' '.$normalized, ' '.$first) => 20,          // a word starting with it: "excellence"
            default                                   => 30,
        };
        if ($kind !== 'preferred') {
            $score += 5;
        }

        return $score + min(mb_strlen($normalized), 200) / 1000;
    }
}
