<?php

namespace App\Services\Matches;

use App\Models\Backbone\EscoOccupation;
use App\Models\Beneficiary;
use App\Models\Opportunity;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — MatchFit (Step 13 · Matches)
//  Location: app/Services/Matches/MatchFit.php
//  Scope: docs/SCOPE_MATCHES.md §4
//
//  How well an Eligible person fits a job or training. INFORMATION ONLY:
//  it sorts and informs, it never blocks a referral and never changes
//  an eligibility result. Nothing is guessed.
//
//  occupation()   the person's occupation against the ones the job asks
//                 for, at every level:
//                   same       what is asked (an ESCO job, or inside the
//                              ISCO-08 / ENOC group asked for)
//                   unit       the same 4-digit group (= ENOC occupation)
//                   minor      the same minor group (3 digits)
//                   sub_major  the same sub-major group (2 digits)
//                   major      the same major group (1 digit)
//                   none       not related
//                   unknown    the profile has no occupation yet
//  occupationRankSql()  the same, as an SQL expression (5 … 0), so the
//                 shortlist can be sorted by it across pages
//  skills()       how many of the ESSENTIAL ESCO skills of the job's
//                 occupations appear in the person's skills:
//                   an ESCO job        → its own essential skills
//                   a 4-digit group    → the essential skills most ESCO
//                                        jobs in the group need (the same
//                                        list as the Occupations page)
//                   a higher group     → none (too broad to say)
//                 A person's skill counts when it IS one of the skill's
//                 names (Arabic or English, any alternative name), or
//                 contains one as whole words.
// ══════════════════════════════════════════════════════════════════

class MatchFit
{
    public const LEVELS = ['same' => 5, 'unit' => 4, 'minor' => 3, 'sub_major' => 2, 'major' => 1, 'none' => 0, 'unknown' => -1];

    private const PREFIX_LEVEL = [4 => 'unit', 3 => 'minor', 2 => 'sub_major', 1 => 'major'];

    /** Skills shown for a 4-digit group — the same number as the Occupations page. */
    private const UNIT_SKILLS = 30;

    /** @var array<int, array> needed skills per opportunity (per request) */
    private array $needed = [];

    /** @var array<string, ?array{unit: ?string, esco: ?string}> */
    private array $asks = [];

    // ── Occupation ───────────────────────────────────────────────────

    /** @return array{level: string, rank: int, code: ?string} */
    public function occupation(Opportunity $o, Beneficiary $b): array
    {
        $unit = (string) $b->isco_code;
        if ($unit === '') {
            return ['level' => 'unknown', 'rank' => self::LEVELS['unknown'], 'code' => null];
        }
        $esco = $b->relationLoaded('escoOccupation') ? $b->escoOccupation?->code
            : ($b->esco_occupation_id ? EscoOccupation::query()->whereKey($b->esco_occupation_id)->value('code') : null);

        $best = ['level' => 'none', 'rank' => 0, 'code' => null];
        foreach ($o->occupations ?? [] as $v) {
            $ask = $this->ask($v);
            if (! $ask) {
                continue;
            }
            [$std, $code] = explode(':', $v, 2);
            $same = $std === 'esco'
                ? ($esco && ($esco === $code || str_starts_with($esco, $code.'.')))
                : str_starts_with($unit, $ask['unit']);
            if ($same) {
                return ['level' => 'same', 'rank' => self::LEVELS['same'], 'code' => $std === 'esco' ? $code : $ask['unit']];
            }
            // The deepest level the two share (4, 3, 2 or 1 digits).
            $askUnit = $ask['unit'];
            for ($n = min(4, strlen($askUnit)); $n >= 1; $n--) {
                if (substr($unit, 0, $n) === substr($askUnit, 0, $n)) {
                    $level = self::PREFIX_LEVEL[$n];
                    if (self::LEVELS[$level] > $best['rank']) {
                        $best = ['level' => $level, 'rank' => self::LEVELS[$level], 'code' => substr($unit, 0, $n)];
                    }
                    break;
                }
            }
        }

        return $best;
    }

    /**
     * The occupation fit as an SQL expression (5 = same … 0 = not related),
     * on a query that joins the beneficiaries as $alias.
     *
     * @return array{0: string, 1: list<mixed>}  [sql, bindings]
     */
    public function occupationRankSql(Opportunity $o, string $alias = 'b'): array
    {
        $same = [];
        $bind = [];
        $byLevel = [4 => [], 3 => [], 2 => [], 1 => []];
        foreach ($o->occupations ?? [] as $v) {
            $ask = $this->ask($v);
            if (! $ask) {
                continue;
            }
            [$std, $code] = explode(':', $v, 2);
            if ($std === 'esco') {
                $ids = EscoOccupation::query()->where(fn ($q) => $q->where('code', $code)->orWhere('code', 'like', $code.'.%'))->pluck('id')->all();
                if ($ids) {
                    $same[] = "$alias.esco_occupation_id IN (".implode(',', array_map('intval', $ids)).')';
                }
            } else {
                $same[] = "$alias.isco_code LIKE ?";
                $bind[] = $ask['unit'].'%';
            }
            for ($n = min(4, strlen($ask['unit'])); $n >= 1; $n--) {
                $byLevel[$n][] = substr($ask['unit'], 0, $n);
            }
        }
        $sql = 'CASE';
        if ($same) {
            $sql .= ' WHEN ('.implode(' OR ', $same).') THEN 5';
        }
        foreach ($byLevel as $n => $prefixes) {
            $prefixes = array_values(array_unique($prefixes));
            if ($prefixes) {
                $sql .= ' WHEN substr('.$alias.'.isco_code, 1, '.$n.') IN ('.implode(',', array_fill(0, count($prefixes), '?')).') THEN '.$n;
                array_push($bind, ...$prefixes);
            }
        }
        $sql .= ' ELSE 0 END';

        return [$sql === 'CASE ELSE 0 END' ? '0' : $sql, $bind];
    }

    /** The ISCO-08 digits an occupation value sits in ('esco:2411.1' → 2411, 'isco:24' → 24). */
    private function ask(string $v): ?array
    {
        if (array_key_exists($v, $this->asks)) {
            return $this->asks[$v];
        }
        [$std, $code] = array_pad(explode(':', $v, 2), 2, '');
        $unit = null;
        if ($std === 'esco') {
            try {
                $unit = DB::table('esco_occupations')->join('isco_groups', 'isco_groups.id', '=', 'esco_occupations.isco_group_id')
                    ->where('esco_occupations.code', $code)->value('isco_groups.code');
            } catch (\Throwable) {
                $unit = null;
            }
            $unit ??= substr(preg_replace('/\D/', '', explode('.', $code)[0]) ?? '', 0, 4) ?: null;
        } elseif (in_array($std, ['isco', 'enoc'], true)) {
            $unit = substr(preg_replace('/\D/', '', $code) ?? '', 0, 4) ?: null;
        }

        return $this->asks[$v] = $unit ? ['unit' => (string) $unit] : null;
    }

    // ── Skills ───────────────────────────────────────────────────────

    /**
     * @return array{state: string, total: int, found: list<array>, lacking: list<array>}
     *   state: ok | no_person_skills | no_job_skills
     */
    public function skills(Opportunity $o, Beneficiary $b): array
    {
        $needed = $this->neededSkills($o);
        if ($needed === []) {
            return ['state' => 'no_job_skills', 'total' => 0, 'found' => [], 'lacking' => []];
        }
        $have = [];
        foreach ($b->skills ?? [] as $s) {
            $n = TextNormalizer::normalize((string) $s);
            if ($n !== '') {
                $have[] = $n;
            }
        }
        if ($have === []) {
            return ['state' => 'no_person_skills', 'total' => count($needed), 'found' => [], 'lacking' => array_map(fn ($s) => $s['title'], $needed)];
        }

        $found = [];
        $lacking = [];
        foreach ($needed as $s) {
            $hit = false;
            foreach ($s['labels'] as $label) {
                foreach ($have as $h) {
                    if ($h === $label || $this->contains($h, $label) || (str_contains($h, ' ') && $this->contains($label, $h))) {
                        $hit = true;
                        break 2;
                    }
                }
            }
            $hit ? $found[] = $s['title'] : $lacking[] = $s['title'];
        }

        return ['state' => 'ok', 'total' => count($needed), 'found' => $found, 'lacking' => $lacking];
    }

    /** "microsoft excel" is in "advanced microsoft excel"; "excel" is not in "excellence". */
    private function contains(string $hay, string $needle): bool
    {
        return $needle !== '' && str_contains(' '.$hay.' ', ' '.$needle.' ');
    }

    /** @return list<array{id: int, title: array{en: string, ar: ?string}, labels: list<string>}> */
    public function neededSkills(Opportunity $o): array
    {
        if (isset($this->needed[$o->id])) {
            return $this->needed[$o->id];
        }
        $ids = [];
        try {
            foreach ($o->occupations ?? [] as $v) {
                [$std, $code] = array_pad(explode(':', $v, 2), 2, '');
                if ($std === 'esco') {
                    $rows = DB::table('esco_occupation_skills')
                        ->join('esco_occupations', 'esco_occupations.id', '=', 'esco_occupation_skills.esco_occupation_id')
                        ->join('esco_skills', 'esco_skills.id', '=', 'esco_occupation_skills.skill_id')
                        ->where('esco_occupations.code', $code)
                        ->where('esco_occupation_skills.is_essential', true)->where('esco_skills.is_active', true)
                        ->orderBy('esco_skills.title_en')->distinct()->pluck('esco_skills.id');
                } elseif (in_array($std, ['isco', 'enoc'], true) && strlen($code) === 4) {
                    $rows = DB::table('esco_occupation_skills')
                        ->join('esco_occupations', 'esco_occupations.id', '=', 'esco_occupation_skills.esco_occupation_id')
                        ->join('isco_groups', 'isco_groups.id', '=', 'esco_occupations.isco_group_id')
                        ->join('esco_skills', 'esco_skills.id', '=', 'esco_occupation_skills.skill_id')
                        ->where('isco_groups.code', $code)->where('esco_occupations.is_active', true)
                        ->where('esco_occupation_skills.is_essential', true)->where('esco_skills.is_active', true)
                        ->groupBy('esco_skills.id', 'esco_skills.title_en')
                        ->orderByRaw('count(*) desc')->orderBy('esco_skills.title_en')
                        ->limit(self::UNIT_SKILLS)->pluck('esco_skills.id');
                } else {
                    continue;
                }
                foreach ($rows as $id) {
                    $ids[(int) $id] = true;
                }
            }
            if ($ids === []) {
                return $this->needed[$o->id] = [];
            }
            $skills = DB::table('esco_skills')->whereIn('id', array_keys($ids))->get(['id', 'title_en', 'title_ar'])->keyBy('id');
            $labels = DB::table('skill_labels')->whereIn('skill_id', array_keys($ids))->whereIn('kind', ['preferred', 'alt'])
                ->get(['skill_id', 'normalized'])->groupBy('skill_id');
        } catch (\Throwable) {
            return $this->needed[$o->id] = [];   // the skills are not loaded yet
        }

        $out = [];
        foreach (array_keys($ids) as $id) {
            $s = $skills[$id] ?? null;
            if (! $s) {
                continue;
            }
            $ls = collect($labels[$id] ?? [])->pluck('normalized')->push(TextNormalizer::normalize($s->title_en))
                ->push(TextNormalizer::normalize((string) $s->title_ar))->filter()->unique()->values()->all();
            $out[] = ['id' => (int) $id, 'title' => ['en' => $s->title_en, 'ar' => $s->title_ar], 'labels' => $ls];
        }

        return $this->needed[$o->id] = $out;
    }
}
