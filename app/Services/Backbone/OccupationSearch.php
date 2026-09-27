<?php

namespace App\Services\Backbone;

use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — OccupationSearch
//  Location: app/Services/Backbone/OccupationSearch.php
//
//  Finds occupations by any title, in Arabic or English, in the
//  standard the user works in:
//
//      ->esco('محاسبة')   ESCO occupations (id => best match)
//      ->units('محاسبة')  ISCO-08 unit groups = ENOC occupations
//
//  How: the text is normalised (TextNormalizer), every word must
//  appear in a title, and each occupation is ranked by its best
//  title: exact match, then starts-with, then a word that starts
//  with the text, then anywhere; official titles before alternative
//  ones; shorter before longer.
//
//  This searches the backbone's ~41,000 titles, which is fast with a
//  plain indexed scan. Searching inside millions of CVs is a separate
//  engine (Scope v2 §7 Arabic search engine) built with the CV Bank.
// ══════════════════════════════════════════════════════════════════

class OccupationSearch
{
    private const CANDIDATES = 4000;

    /** @return array<int, array{label: string, lang: string, kind: string, score: float}> esco_occupation_id => best match */
    public function esco(string $text, ?string $major = null): array
    {
        return $this->search($text, 'esco_occupation_id', $major);
    }

    /** @return array<int, array{label: string, lang: string, kind: string, score: float}> isco_group_id => best match */
    public function units(string $text, ?string $major = null): array
    {
        return $this->search($text, 'isco_group_id', $major);
    }

    private function search(string $text, string $target, ?string $major): array
    {
        $tokens = TextNormalizer::tokens($text);
        if (! $tokens) {
            return [];
        }
        $phrase = implode(' ', $tokens);

        $query = DB::table('occupation_labels')->select(['isco_group_id', 'esco_occupation_id', 'lang', 'kind', 'label', 'normalized']);
        foreach ($tokens as $token) {
            $query->where('normalized', 'like', '%'.$token.'%');
        }
        if ($target === 'esco_occupation_id') {
            $query->whereNotNull('esco_occupation_id');
        }
        if ($major !== null && $major !== '') {
            $query->whereIn('isco_group_id', DB::table('isco_groups')->select('id')->where('major_code', $major));
        }

        $best = [];
        foreach ($query->limit(self::CANDIDATES)->cursor() as $row) {
            $id = $row->{$target};
            $score = $this->score($row->normalized, $phrase, $tokens[0], $row->kind);
            if (! isset($best[$id]) || $score < $best[$id]['score']) {
                $best[$id] = ['label' => $row->label, 'lang' => $row->lang, 'kind' => $row->kind, 'score' => $score];
            }
        }

        uasort($best, fn ($a, $b) => $a['score'] <=> $b['score']);

        return $best;
    }

    private function score(string $normalized, string $phrase, string $first, string $kind): float
    {
        $score = match (true) {
            $normalized === $phrase                  => 0,
            str_starts_with($normalized, $phrase)    => 10,
            str_contains(' '.$normalized, ' '.$first) => 20,
            default                                  => 30,
        };
        if (in_array($kind, ['alt', 'hidden'], true)) {
            $score += 5;
        }

        return $score + min(mb_strlen($normalized), 200) / 1000;
    }
}
