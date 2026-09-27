<?php

namespace App\Services\Cv;

use App\Models\Backbone\EnocOccupation;
use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Models\Beneficiary;
use App\Models\CvDocument;
use App\Services\Beneficiaries\OccupationDisplay;
use App\Support\TextNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvBankSearch (Scope v2 §3 Searchable CV Bank)
//  Location: app/Services/Cv/CvBankSearch.php
//
//  Finds people in ONE workspace:
//
//    words        every word must be in the profile or in one of its
//                 CVs (cv_bank_index), compared normalised: "المحاسبة"
//                 finds "محاسبه", "ACCOUNTING" finds "accounting".
//    synonyms     the words are also looked up in the occupation
//                 titles (ENOC, ESCO titles and alternative titles,
//                 ISCO-08): people whose OCCUPATION is one of those
//                 groups are found too — "bookkeeper" finds the
//                 accountants, even when their CV never says it.
//                 Word matches are listed first.
//    occupation   "isco:2", "isco:241", "isco:2411" (any level — ENOC
//                 uses the same codes: "enoc:2411"), "esco:2411.1"
//                 (that ESCO job and the narrower ones under it)
//    governorate, min_years, cv_lang (ar | en — a mixed CV counts for both)
//    sector       worked in a sector (IND · TRD · SRV) or sub-sector (I01 …)
//                 in any job (Step 10.5)
//    stage        journey stage: not_assessed | assessed | eligible (Step 11)
//                 | matched | placed (Step 13)
//    report       the people behind one number of a report (Step 14)
//
//  Each result carries up to 3 snippets: the CV lines (or profile
//  lines) with the words, split into parts marked hit / not hit.
// ══════════════════════════════════════════════════════════════════

class CvBankSearch
{
    public const PER_PAGE = 20;

    /** Step 11: the journey stages the CV Bank can filter on. */
    public const STAGES = ['not_assessed', 'assessed', 'eligible', 'matched', 'placed'];

    /**
     * @param  array{q: string, occ: string, governorate: string, min_years: int, cv_lang: string}  $f
     * @param  ?\Closure  $narrow  more limits on the query — the Talent Pool (Step 10) passes "only people who chose to be seen"
     */
    public function search(int $companyId, array $f, string $locale, ?\Closure $narrow = null): LengthAwarePaginator
    {
        $tokens = array_values(array_unique(TextNormalizer::tokens($f['q'])));
        $units = $this->synonymUnits($f['q']);

        return $this->query($companyId, $f, $narrow, $tokens, $units)
            ->with(['unit.enoc', 'escoOccupation'])
            ->orderByDesc('beneficiaries.number')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Beneficiary $b) => $this->present($b, $tokens, $units, $locale));
    }

    /**
     * Step 11: the ids of EVERY person the search finds (not one page),
     * for "Check these people against a job or training".
     *
     * @return list<int>
     */
    public function ids(int $companyId, array $f, int $limit = 50000): array
    {
        $tokens = array_values(array_unique(TextNormalizer::tokens($f['q'] ?? '')));

        return $this->query($companyId, $f, null, $tokens, $this->synonymUnits($f['q'] ?? ''), ordered: false)
            ->reorder()->orderBy('beneficiaries.id')->limit($limit)
            ->pluck('beneficiaries.id')->map(fn ($id) => (int) $id)->all();
    }

    /** The search itself, without the page: words, synonyms and every filter. */
    private function query(int $companyId, array $f, ?\Closure $narrow, array $tokens, array $units, bool $ordered = true): Builder
    {

        // The explicit workspace filter (not the signed-in person's): the Talent Pool is searched by partner staff.
        $query = Beneficiary::query()->withoutGlobalScopes()->inWorkspace($companyId)
            ->join('cv_bank_index as i', 'i.beneficiary_id', '=', 'beneficiaries.id')
            ->select('beneficiaries.*', 'i.cv_languages', 'i.cv_count');

        if ($tokens) {
            $wordsSql = implode(' AND ', array_fill(0, count($tokens), 'i.body LIKE ?'));
            $bindings = array_map(fn ($t) => '%'.$this->like($t).'%', $tokens);
            $query->where(function (Builder $w) use ($wordsSql, $bindings, $units) {
                $w->whereRaw('('.$wordsSql.')', $bindings);
                if ($units) {
                    $w->orWhereIn('beneficiaries.isco_group_id', $units);
                }
            });
            // Word matches first, then people found through their occupation only.
            if ($ordered) {
                $query->orderByRaw('CASE WHEN ('.$wordsSql.') THEN 0 ELSE 1 END', $bindings);
            }
        }

        if ($narrow) {
            $narrow($query);
        }
        $this->occupationFilter($query, $f['occ']);
        // Step 10.5: worked in this sector (IND) or sub-sector (I01) — in any job.
        if (preg_match('/^(IND|TRD|SRV|[ITS]\d{2})$/', $f['sector'] ?? '')) {
            $v = strlen($f['sector']) === 3 ? $f['sector'][0] : $f['sector'];
            $query->where(fn ($w) => $w->where('beneficiaries.work_history', 'like', '%"sub_sector":"'.$v.'%')
                ->orWhere('beneficiaries.work_history', 'like', '%"sub_sector": "'.$v.'%'));
        }
        $query
            ->when(($f['governorate'] ?? '') !== '', fn ($q) => $q->where('beneficiaries.governorate', $f['governorate']))
            ->when(($f['min_years'] ?? 0) > 0, fn ($q) => $q->where('beneficiaries.experience_months', '>=', $f['min_years'] * 12))
            ->when(in_array($f['cv_lang'] ?? '', ['ar', 'en'], true), fn ($q) => $q->where(fn ($w) => $w
                ->where('i.cv_languages', 'like', '%'.$f['cv_lang'].'%')->orWhere('i.cv_languages', 'like', '%mixed%')));
        $this->stageFilter($query, $f['stage'] ?? '');
        // Step 14: "Open these people" from a report — only the people behind that number.
        if (($report = self::reportOf($f['report'] ?? '')) !== null) {
            $query->whereIn('beneficiaries.id', app(\App\Services\Reports\ReportBuilder::class)->ids($report['p'], $companyId, $report['row'], $report['col']) ?: [0]);
        }

        return $query;
    }

    /**
     * Step 11: the journey stage (Registered → Assessed → …).
     *   not_assessed  never checked against a job or training
     *   assessed      checked against at least one job or training
     *   eligible      eligible for at least one job or training (after the case worker's decision)
     *   matched       referred to at least one job or training (Step 13)
     *   placed        hired, or completed a training (Step 13)
     */
    private function stageFilter(Builder $query, string $stage): void
    {
        if (! in_array($stage, self::STAGES, true)) {
            return;
        }
        if ($stage === 'matched' || $stage === 'placed') {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('opportunity_matches as om')->whereColumn('om.beneficiary_id', 'beneficiaries.id')
                ->when($stage === 'placed', fn ($w) => $w->where('om.status', 'active')->where('om.stage', 'done')));

            return;
        }
        $sub = fn ($q) => $q->selectRaw('1')->from('eligibility_assessments as ea')->whereColumn('ea.beneficiary_id', 'beneficiaries.id')
            ->when($stage === 'eligible', fn ($w) => $w->where('ea.result', 'eligible'));
        $stage === 'not_assessed' ? $query->whereNotExists($sub) : $query->whereExists($sub);
    }

    /**
     * Step 14: a report's question and the number clicked, sent by "Open these people"
     * as base64url JSON {p, row, col}. Null when it is not one.
     *
     * @return array{p: array, row: ?string, col: ?string}|null
     */
    public static function reportOf(mixed $token): ?array
    {
        if (! is_string($token) || $token === '' || strlen($token) > 6000) {
            return null;
        }
        $json = base64_decode(strtr($token, '-_', '+/'), true);
        $in = $json !== false ? json_decode($json, true) : null;
        if (! is_array($in) || ! is_array($in['p'] ?? null)) {
            return null;
        }
        $key = fn ($v) => is_string($v) && $v !== '' && strlen($v) <= 40 ? $v : null;

        return ['p' => \App\Services\Reports\ReportBuilder::normalize($in['p']), 'row' => $key($in['row'] ?? null), 'col' => $key($in['col'] ?? null)];
    }

    /** The occupation groups whose titles contain the searched words (for the synonyms). @return list<int> */
    public function synonymUnits(string $q): array
    {
        $phrase = TextNormalizer::normalize($q);
        if (mb_strlen($phrase) < 3 || ctype_digit(str_replace(' ', '', $phrase))) {
            return [];
        }
        try {
            return DB::table('occupation_labels')->where('normalized', 'like', '%'.$this->like($phrase).'%')
                ->limit(300)->pluck('isco_group_id')->unique()->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function occupationFilter(Builder $query, string $occ): void
    {
        if (! preg_match('/^(isco|enoc|esco):([\d.]{1,40})$/', $occ, $m)) {
            return;
        }
        [, $standard, $code] = $m;
        if ($standard === 'esco') {
            // That ESCO job and every narrower job under it (2411.1 → 2411.1, 2411.1.1 …).
            $ids = EscoOccupation::query()->where(fn ($w) => $w->where('code', $code)->orWhere('code', 'like', $code.'.%'))->pluck('id');
            $query->whereIn('beneficiaries.esco_occupation_id', $ids->all() ?: [0]);

            return;
        }
        // ISCO-08 and ENOC share the codes: a major group (1 digit) down to a unit group (4).
        $query->where('beneficiaries.isco_code', 'like', substr(preg_replace('/\D/', '', $code), 0, 4).'%');
    }

    private function present(Beneficiary $b, array $tokens, array $units, string $locale): array
    {
        $byWords = true;
        $snippets = [];
        if ($tokens) {
            $snippets = $this->snippets($b, $tokens);
            $byWords = $snippets !== [] || ! in_array((int) $b->isco_group_id, array_map('intval', $units), true);
        }

        return [
            'number'      => $b->number,
            'name'        => $b->displayName($locale),
            'initials'    => $b->initials(),
            'governorate' => $b->governorate,
            'city'        => $b->city,
            'experience'  => $b->experience_months,
            'occupation'  => OccupationDisplay::block($b->unit, $b->escoOccupation, $b->gender),
            'cv_languages' => array_values(array_filter(explode(',', (string) $b->getAttribute('cv_languages')))),
            'cv_count'    => (int) $b->getAttribute('cv_count'),
            'found_by'    => $byWords ? 'words' : 'occupation',
            'snippets'    => $snippets,
        ];
    }

    /**
     * Up to 3 lines with the searched words — from the CVs first, then the
     * profile (job titles, duties, skills) — split into parts:
     * [{t: 'text', hit: bool}, …].
     */
    private function snippets(Beneficiary $b, array $tokens): array
    {
        $lines = [];
        $texts = CvDocument::query()->withoutGlobalScopes()->where('company_id', $b->company_id)->where('beneficiary_id', $b->id)
            ->whereIn('status', CvDocument::ON_PROFILE)->whereNotNull('text')->orderByDesc('id')->pluck('text');
        foreach ($texts as $text) {
            foreach (explode("\n", $text) as $line) {
                $lines[] = $line;
            }
        }
        foreach ($b->work_history ?? [] as $job) {
            $lines[] = implode(' · ', array_filter([$job['title'] ?? null, $job['employer'] ?? null, $job['location'] ?? null]));
            foreach ($job['responsibilities'] ?? [] as $d) {
                $lines[] = $d;
            }
        }
        $lines[] = implode(', ', $b->skills ?? []);

        $out = [];
        $seen = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
            $norm = TextNormalizer::normalize($line);
            if ($line === '' || isset($seen[$norm])) {
                continue;
            }
            $hits = array_filter($tokens, fn ($t) => str_contains($norm, $t));
            if (! $hits) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = $this->mark($this->around($line, $hits), $tokens);
            if (count($out) >= 3) {
                break;
            }
        }

        return $out;
    }

    /** A long line is cut around the first word found (about 180 characters). */
    private function around(string $line, array $hits): string
    {
        if (mb_strlen($line) <= 180) {
            return $line;
        }
        $words = preg_split('/\s+/u', $line);
        foreach ($words as $k => $w) {
            $n = TextNormalizer::normalize($w);
            foreach ($hits as $t) {
                if ($n !== '' && (str_contains($n, $t) || str_contains($t, $n))) {
                    $from = max(0, $k - 10);

                    return ($from > 0 ? '… ' : '').implode(' ', array_slice($words, $from, 28)).($from + 28 < count($words) ? ' …' : '');
                }
            }
        }

        return mb_substr($line, 0, 180).' …';
    }

    /** @return list<array{t: string, hit: bool}> */
    private function mark(string $line, array $tokens): array
    {
        $parts = [];
        foreach (preg_split('/(\s+)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE) as $piece) {
            $n = TextNormalizer::normalize($piece);
            $hit = $n !== '' && (bool) array_filter($tokens, fn ($t) => str_contains($n, $t));
            $last = count($parts) - 1;
            if ($last >= 0 && $parts[$last]['hit'] === $hit) {
                $parts[$last]['t'] .= $piece;
            } else {
                $parts[] = ['t' => $piece, 'hit' => $hit];
            }
        }

        return $parts;
    }

    /** Occupations of any standard and level, for the occupation filter. */
    public function occupationOptions(string $q, string $locale): array
    {
        $q = trim($q);
        $norm = TextNormalizer::normalize($q);
        if (mb_strlen($norm) < 1) {
            return [];
        }
        $isCode = (bool) preg_match('/^[\d.]+$/', $q);
        $like = '%'.$this->like($q).'%';
        $out = [];

        $groups = IscoGroup::query()
            ->when($isCode, fn ($w) => $w->where('code', 'like', $q.'%'), fn ($w) => $w->where(fn ($x) => $x->where('title_en', 'like', $like)->orWhere('title_ar', 'like', $like)))
            ->orderBy('level')->orderBy('code')->limit(12)->get();
        foreach ($groups as $g) {
            $out[] = ['value' => 'isco:'.$g->code, 'standard' => 'isco', 'code' => $g->code, 'level' => $g->level,
                'title' => $locale === 'ar' && $g->title_ar ? $g->title_ar : $g->title_en];
        }
        $enoc = EnocOccupation::query()
            ->when($isCode, fn ($w) => $w->where('code', 'like', $q.'%'), fn ($w) => $w->where('title_ar', 'like', $like))
            ->orderBy('code')->limit(8)->get();
        foreach ($enoc as $e) {
            $out[] = ['value' => 'enoc:'.$e->code, 'standard' => 'enoc', 'code' => $e->code, 'level' => 4, 'title' => $e->title_ar];
        }
        $esco = EscoOccupation::query()->where('is_active', true)
            ->when($isCode, fn ($w) => $w->where('code', 'like', $q.'%'), fn ($w) => $w->where(fn ($x) => $x->where('title_en', 'like', $like)->orWhere('title_ar', 'like', $like)))
            ->orderBy('sort_key')->limit(12)->get();
        foreach ($esco as $e) {
            $out[] = ['value' => 'esco:'.$e->code, 'standard' => 'esco', 'code' => $e->code, 'level' => 5,
                'title' => $locale === 'ar' && $e->title_ar ? $e->title_ar : $e->title_en];
        }

        return $out;
    }

    /** The label of an occupation filter value, to show it chosen. */
    public function occupationLabel(string $occ, string $locale): ?array
    {
        if (! preg_match('/^(isco|enoc|esco):([\d.]{1,40})$/', $occ, $m)) {
            return null;
        }
        foreach ($this->occupationOptions($m[2], $locale) as $o) {
            if ($o['value'] === $occ) {
                return $o;
            }
        }

        return null;
    }

    /** Escape % and _ for LIKE. */
    private function like(string $v): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v);
    }
}
