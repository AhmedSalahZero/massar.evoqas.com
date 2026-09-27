<?php

namespace App\Services\Reports;

use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Models\Company;
use App\Models\Sector;
use App\Services\Backbone\OccupationCatalog;
use App\Support\ScreenWords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — ReportBuilder (Step 14 · Reports)
//  Location: app/Services/Reports/ReportBuilder.php
//  Scope: docs/SCOPE_REPORTS.md (agreed as recommended)
//
//  A report is a QUESTION about the workspace's people:
//
//    filters   (all together; an empty filter means everyone)
//              occupation (any standard, any level; one or more: a person
//              in ANY of them) · industry (sector or sub-sector of the
//              current or last job) · gender · age · experience · expected
//              salary · governorate · education · journey stage
//    measure   count · average age · average experience · average expected salary
//    split     rows, and optionally columns (a cross-table)
//    range     the date the person was registered — or, with the stage
//              filter "Placed", the date they were hired / completed
//
//  normalize()  turns anything sent by a browser (or a saved report) into
//               a clean question: unknown values are dropped, never guessed
//  run()        the answer: rows, columns, cells {v, n}, totals. Averages
//               say how many people they are based on (n); people who did
//               not give that value are not counted in them.
//  ids()        the people behind the whole answer or one cell
//               ("Open these people")
//  summary()    the question in words, for the screen, Excel and PDF
//
//  $companyId null = every partner (the Super Admin): counts only, and any
//  number from 1 to 4 is hidden as "fewer than 5". The public pool's own
//  workspace is never counted.
//
//  The database only narrows the people down (workspace, dates, and the
//  simple filters); the splitting and the averages are worked out here,
//  the same way on MySQL and on the test database.
// ══════════════════════════════════════════════════════════════════

class ReportBuilder
{
    public const DIMENSIONS = ['occupation', 'industry', 'gender', 'age', 'experience', 'salary', 'governorate', 'education', 'stage', 'month', 'quarter'];
    public const MEASURES = ['count', 'age', 'experience', 'salary'];
    public const OCC_LEVELS = ['major', 'sub_major', 'minor', 'unit', 'esco'];
    public const IND_LEVELS = ['sector', 'sub_sector'];
    public const STAGES = ['registered', 'assessed', 'eligible', 'matched', 'placed'];
    public const RANGES = ['this_month', 'last_3', 'this_year', 'last_year', 'all', 'custom'];

    /** The Super Admin never sees a number from 1 to 4. */
    public const SMALL = 5;

    private const AGE = [['lt20', null, 19], ['20_24', 20, 24], ['25_29', 25, 29], ['30_34', 30, 34], ['35_44', 35, 44], ['45p', 45, null]];
    private const EXP = [['none', 0, 0], ['lt1', 1, 11], ['1_2', 12, 35], ['3_5', 36, 71], ['6_10', 72, 131], ['gt10', 132, null]];
    private const SAL = [['lt4', null, 3999], ['4_6', 4000, 5999], ['6_8', 6000, 7999], ['8_10', 8000, 9999], ['10_15', 10000, 14999], ['15p', 15000, null]];
    private const PREFIX = ['major' => 1, 'sub_major' => 2, 'minor' => 3, 'unit' => 4];

    public function __construct(private readonly OccupationCatalog $catalog) {}

    // ── The question ─────────────────────────────────────────────────

    public static function defaults(): array
    {
        return [
            'f' => ['occ' => [], 'industry' => [], 'gender' => '', 'age' => [null, null], 'exp' => [null, null], 'salary' => [null, null],
                'gov' => [], 'edu' => [], 'stage' => ''],
            'measure'   => 'count',
            'rows'      => 'occupation',
            'cols'      => '',
            'occ_level' => 'unit',
            'ind_level' => 'sector',
            'range'     => ['preset' => 'all', 'from' => null, 'to' => null],
        ];
    }

    public static function normalize(mixed $in): array
    {
        $in = is_array($in) ? $in : [];
        $d = self::defaults();
        $f = is_array($in['f'] ?? null) ? $in['f'] : [];
        $list = fn ($v, callable $ok, int $max = 20) => array_values(array_slice(array_unique(array_filter(is_array($v) ? $v : [], fn ($x) => is_string($x) && $ok($x))), 0, $max));
        $pair = function ($v, int $max) {
            $v = is_array($v) ? array_values($v) : [];
            $a = isset($v[0]) && $v[0] !== '' && $v[0] !== null && is_numeric($v[0]) ? max(0, min($max, (int) $v[0])) : null;
            $b = isset($v[1]) && $v[1] !== '' && $v[1] !== null && is_numeric($v[1]) ? max(0, min($max, (int) $v[1])) : null;

            return ($a !== null && $b !== null && $a > $b) ? [$b, $a] : [$a, $b];
        };

        $out = $d;
        $out['f'] = [
            'occ'      => $list($f['occ'] ?? [], fn ($x) => (bool) preg_match('/^(isco|enoc|esco):[\d.]{1,40}$/', $x)),
            'industry' => $list($f['industry'] ?? [], fn ($x) => (bool) preg_match('/^(IND|TRD|SRV|[ITS]\d{2})$/', $x)),
            'gender'   => in_array($f['gender'] ?? '', config('beneficiaries.genders'), true) ? $f['gender'] : '',
            'age'      => $pair($f['age'] ?? [], 100),
            'exp'      => $pair($f['exp'] ?? [], 60),
            'salary'   => $pair($f['salary'] ?? [], 1000000),
            'gov'      => $list($f['gov'] ?? [], fn ($x) => in_array($x, config('beneficiaries.governorates'), true), 27),
            'edu'      => $list($f['edu'] ?? [], fn ($x) => in_array($x, config('beneficiaries.education_levels'), true)),
            'stage'    => in_array($f['stage'] ?? '', self::STAGES, true) ? $f['stage'] : '',
        ];
        $out['measure'] = in_array($in['measure'] ?? '', self::MEASURES, true) ? $in['measure'] : 'count';
        $out['rows'] = in_array($in['rows'] ?? '', self::DIMENSIONS, true) ? $in['rows'] : $d['rows'];
        $out['cols'] = in_array($in['cols'] ?? '', self::DIMENSIONS, true) && $in['cols'] !== $out['rows'] ? $in['cols'] : '';
        $out['occ_level'] = in_array($in['occ_level'] ?? '', self::OCC_LEVELS, true) ? $in['occ_level'] : 'unit';
        $out['ind_level'] = in_array($in['ind_level'] ?? '', self::IND_LEVELS, true) ? $in['ind_level'] : 'sector';
        $r = is_array($in['range'] ?? null) ? $in['range'] : [];
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
        $out['range'] = ['preset' => in_array($r['preset'] ?? '', self::RANGES, true) ? $r['preset'] : 'all', 'from' => $date($r['from'] ?? null), 'to' => $date($r['to'] ?? null)];
        if ($out['range']['preset'] !== 'custom') {
            $out['range']['from'] = $out['range']['to'] = null;
        } elseif ($out['range']['from'] && $out['range']['to'] && $out['range']['from'] > $out['range']['to']) {
            [$out['range']['from'], $out['range']['to']] = [$out['range']['to'], $out['range']['from']];
        }

        return $out;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} the first and last moment of the range */
    public static function dates(array $range): array
    {
        $now = now();

        return match ($range['preset'] ?? 'all') {
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_3'     => [$now->copy()->subMonthsNoOverflow(2)->startOfMonth(), $now->copy()->endOfDay()],
            'this_year'  => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'last_year'  => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'custom'     => [$range['from'] ? Carbon::parse($range['from'])->startOfDay() : null, $range['to'] ? Carbon::parse($range['to'])->endOfDay() : null],
            default      => [null, null],
        };
    }

    // ── The answer ───────────────────────────────────────────────────

    /**
     * @return array{total: array, rows: list<array>, cols: ?list<array>, cells: array, row_totals: array, col_totals: array, people: int, market: ?array}
     */
    public function run(array $p, ?int $companyId, string $locale = 'en', string $standard = 'enoc'): array
    {
        $people = $this->people($p, $companyId);
        $measure = $companyId === null ? 'count' : $p['measure'];

        $cells = $rowT = $colT = [];
        $total = ['c' => 0, 's' => 0.0, 'n' => 0];
        $add = function (array &$acc, ?float $v) {
            $acc['c'] = ($acc['c'] ?? 0) + 1;
            if ($v !== null) {
                $acc['s'] = ($acc['s'] ?? 0) + $v;
                $acc['n'] = ($acc['n'] ?? 0) + 1;
            }
        };
        foreach ($people as $b) {
            $v = $this->value($measure, $b);
            $r = $this->key($p['rows'], $b, $p);
            $c = $p['cols'] ? $this->key($p['cols'], $b, $p) : '_';
            $cells[$r][$c] ??= ['c' => 0, 's' => 0.0, 'n' => 0];
            $add($cells[$r][$c], $v);
            $rowT[$r] ??= ['c' => 0, 's' => 0.0, 'n' => 0];
            $add($rowT[$r], $v);
            $colT[$c] ??= ['c' => 0, 's' => 0.0, 'n' => 0];
            $add($colT[$c], $v);
            $add($total, $v);
        }

        $rows = $this->order($p['rows'], array_keys($rowT), $rowT);
        $cols = $p['cols'] ? $this->order($p['cols'], array_keys($colT), $colT) : null;
        $cell = fn (array $a) => $this->cell($a, $measure, $companyId === null);

        return [
            'measure'    => $measure,
            'total'      => $cell($total),
            'people'     => $companyId === null && $total['c'] > 0 && $total['c'] < self::SMALL ? null : $total['c'],
            'rows'       => array_map(fn ($k) => ['key' => (string) $k, 'label' => $this->label($p['rows'], (string) $k, $p, $locale, $standard)], $rows),
            'cols'       => $cols === null ? null : array_map(fn ($k) => ['key' => (string) $k, 'label' => $this->label($p['cols'], (string) $k, $p, $locale, $standard)], $cols),
            'cells'      => array_map(fn ($byCol) => array_map($cell, $byCol), $cells),
            'row_totals' => array_map($cell, $rowT),
            'col_totals' => array_map($cell, $colT),
            // Next to an average expected salary split by 4-digit occupation: the market average wage.
            'market'     => $measure === 'salary' && $p['rows'] === 'occupation' && $p['occ_level'] === 'unit' ? $this->market($rows) : null,
        ];
    }

    /** The people behind the whole answer, or one row / one cell. @return list<int> */
    public function ids(array $p, int $companyId, ?string $row = null, ?string $col = null): array
    {
        $out = [];
        foreach ($this->people($p, $companyId) as $b) {
            if ($row !== null && $this->key($p['rows'], $b, $p) !== $row) {
                continue;
            }
            if ($col !== null && $p['cols'] && $this->key($p['cols'], $b, $p) !== $col) {
                continue;
            }
            $out[] = (int) $b->id;
        }

        return $out;
    }

    // ── The people ───────────────────────────────────────────────────

    /** @return list<object> the people the filters keep, with a `stage` */
    private function people(array $p, ?int $companyId): array
    {
        $f = $p['f'];
        [$from, $to] = self::dates($p['range']);
        $placedRange = $f['stage'] === 'placed';

        $q = DB::table('beneficiaries as b')
            ->select('b.id', 'b.company_id', 'b.gender', 'b.date_of_birth', 'b.governorate', 'b.education_level', 'b.experience_months',
                'b.expected_salary', 'b.isco_code', 'b.esco_occupation_id', 'b.industry', 'b.created_at');
        $companyId !== null
            ? $q->where('b.company_id', $companyId)
            : $q->whereIn('b.company_id', Company::query()->select('id'));   // partners only (never the public pool)

        if (! $placedRange) {
            $from && $q->where('b.created_at', '>=', $from);
            $to && $q->where('b.created_at', '<=', $to);
        }
        $f['gender'] && $q->where('b.gender', $f['gender']);
        $f['gov'] && $q->whereIn('b.governorate', $f['gov']);
        $f['edu'] && $q->whereIn('b.education_level', $f['edu']);
        [$a1, $a2] = $f['age'];
        // Age from … to …: born between two dates (the same on every database).
        $a1 !== null && $q->where('b.date_of_birth', '<=', today()->subYears($a1)->endOfDay());
        $a2 !== null && $q->where('b.date_of_birth', '>', today()->subYears($a2 + 1)->endOfDay());
        [$e1, $e2] = $f['exp'];
        $e1 !== null && $q->where('b.experience_months', '>=', $e1 * 12);
        $e2 !== null && $q->where('b.experience_months', '<', ($e2 + 1) * 12);
        [$s1, $s2] = $f['salary'];
        $s1 !== null && $q->where('b.expected_salary', '>=', $s1);
        $s2 !== null && $q->where('b.expected_salary', '<=', $s2);
        if ($f['occ']) {
            $q->where(function ($w) use ($f) {
                foreach ($f['occ'] as $v) {
                    [$std, $code] = explode(':', $v, 2);
                    if ($std === 'esco') {
                        $ids = EscoOccupation::query()->where(fn ($x) => $x->where('code', $code)->orWhere('code', 'like', $code.'.%'))->pluck('id')->all();
                        $w->orWhereIn('b.esco_occupation_id', $ids ?: [0]);
                    } else {
                        $w->orWhere('b.isco_code', 'like', substr(preg_replace('/\D/', '', $code), 0, 4).'%');
                    }
                }
            });
        }
        if ($f['industry']) {
            $q->where(function ($w) use ($f) {
                foreach ($f['industry'] as $v) {
                    in_array($v, ['IND', 'TRD', 'SRV'], true) ? $w->orWhere('b.industry', 'like', $v[0].'%') : $w->orWhere('b.industry', $v);
                }
            });
        }

        $rows = $q->orderBy('b.id')->get()->all();
        $needStage = $f['stage'] !== '' || in_array('stage', [$p['rows'], $p['cols']], true);
        if (! $needStage || $rows === []) {
            return $rows;
        }

        // The furthest stage each person reached.
        $stage = $this->stages($companyId, $placedRange ? [$from, $to] : null);
        $min = array_search($f['stage'] ?: 'registered', self::STAGES, true);
        $out = [];
        foreach ($rows as $b) {
            $b->stage = self::STAGES[$stage[$b->id] ?? 0];
            if ($f['stage'] === 'placed' ? $b->stage === 'placed' : ($stage[$b->id] ?? 0) >= $min) {
                $out[] = $b;
            }
        }

        return $out;
    }

    /** @return array<int, int> person id → furthest stage (0 registered … 4 placed) */
    private function stages(?int $companyId, ?array $placedBetween): array
    {
        $scope = fn ($q, $col) => $companyId !== null ? $q->where($col, $companyId) : $q;
        $out = [];
        $mark = function (iterable $ids, int $rank) use (&$out) {
            foreach ($ids as $id) {
                $out[(int) $id] = max($out[(int) $id] ?? 0, $rank);
            }
        };
        $mark($scope(DB::table('eligibility_assessments'), 'company_id')->distinct()->pluck('beneficiary_id'), 1);
        $mark($scope(DB::table('eligibility_assessments'), 'company_id')->where('result', 'eligible')->distinct()->pluck('beneficiary_id'), 2);
        $mark($scope(DB::table('opportunity_matches'), 'company_id')->distinct()->pluck('beneficiary_id'), 3);
        $placed = $scope(DB::table('opportunity_matches'), 'company_id')->where('status', 'active')->where('stage', 'done');
        if ($placedBetween) {
            [$from, $to] = $placedBetween;
            $from && $placed->where('stage_on', '>=', $from->copy()->startOfDay());
            $to && $placed->where('stage_on', '<=', $to->copy()->endOfDay());
        }
        $mark($placed->distinct()->pluck('beneficiary_id'), 4);

        return $out;
    }

    // ── Keys and values ──────────────────────────────────────────────

    private function value(string $measure, object $b): ?float
    {
        return match ($measure) {
            'age'        => $b->date_of_birth ? round(Carbon::parse($b->date_of_birth)->floatDiffInYears(today()), 2) : null,
            'experience' => round(((int) $b->experience_months) / 12, 2),
            'salary'     => $b->expected_salary ? (float) $b->expected_salary : null,
            default      => null,
        };
    }

    private function key(string $dim, object $b, array $p): string
    {
        switch ($dim) {
            case 'occupation':
                if (! $b->isco_code) {
                    return '_none';
                }
                if ($p['occ_level'] === 'esco') {
                    return $b->esco_occupation_id ? 'esco:'.$this->escoCode((int) $b->esco_occupation_id) : 'unit:'.$b->isco_code;
                }

                return substr((string) $b->isco_code, 0, self::PREFIX[$p['occ_level']]);
            case 'industry':
                if (! $b->industry) {
                    return '_none';
                }

                return $p['ind_level'] === 'sector' ? (['I' => 'IND', 'T' => 'TRD', 'S' => 'SRV'][$b->industry[0]] ?? '_none') : $b->industry;
            case 'gender':
                return (string) $b->gender;
            case 'age':
                if (! $b->date_of_birth) {
                    return '_none';
                }

                return $this->band(self::AGE, Carbon::parse($b->date_of_birth)->age);
            case 'experience':
                return $this->band(self::EXP, (int) $b->experience_months);
            case 'salary':
                return $b->expected_salary ? $this->band(self::SAL, (int) $b->expected_salary) : '_none';
            case 'governorate':
                return (string) $b->governorate;
            case 'education':
                return $b->education_level ?: '_none';
            case 'stage':
                return $b->stage ?? 'registered';
            case 'month':
                return Carbon::parse($b->created_at)->format('Y-m');
            case 'quarter':
                $d = Carbon::parse($b->created_at);

                return $d->year.'-Q'.$d->quarter;
        }

        return '_none';
    }

    private function band(array $bands, int $v): string
    {
        foreach ($bands as [$k, $lo, $hi]) {
            if (($lo === null || $v >= $lo) && ($hi === null || $v <= $hi)) {
                return $k;
            }
        }

        return '_none';
    }

    /** @var array<int, string> */
    private array $escoCodes = [];

    private function escoCode(int $id): string
    {
        return $this->escoCodes[$id] ??= (string) EscoOccupation::query()->whereKey($id)->value('code');
    }

    private function cell(array $a, string $measure, bool $countsOnly): array
    {
        $c = (int) ($a['c'] ?? 0);
        if ($measure === 'count') {
            if ($countsOnly && $c > 0 && $c < self::SMALL) {
                return ['v' => null, 'n' => null, 'small' => true];
            }

            return ['v' => $c, 'n' => $c];
        }
        $n = (int) ($a['n'] ?? 0);

        return ['v' => $n ? round($a['s'] / $n, 1) : null, 'n' => $n];
    }

    // ── Order and names ──────────────────────────────────────────────

    private function order(string $dim, array $keys, array $totals): array
    {
        $natural = match ($dim) {
            'age'        => array_column(self::AGE, 0),
            'experience' => array_column(self::EXP, 0),
            'salary'     => array_column(self::SAL, 0),
            'stage'      => self::STAGES,
            'gender'     => ['female', 'male'],
            default      => null,
        };
        if (in_array($dim, ['month', 'quarter'], true)) {
            sort($keys);

            return $keys;
        }
        usort($keys, function ($a, $b) use ($natural, $totals) {
            if ($a === '_none' || $b === '_none') {
                return $a === '_none' ? 1 : -1;
            }
            if ($natural) {
                return array_search($a, $natural, true) <=> array_search($b, $natural, true);
            }

            return [$totals[$b]['c'] ?? 0, $a] <=> [$totals[$a]['c'] ?? 0, $b];   // the biggest first
        });

        return $keys;
    }

    /** @var array<string, string> */
    private array $labels = [];

    public function label(string $dim, string $key, array $p, string $locale, string $standard = 'enoc'): string
    {
        $cacheKey = "$dim|$key|{$p['occ_level']}|{$p['ind_level']}|$locale|$standard";
        if (isset($this->labels[$cacheKey])) {
            return $this->labels[$cacheKey];
        }
        $ar = $locale === 'ar';
        if ($key === '_none') {
            return $this->labels[$cacheKey] = __('reports.not_given', [], $locale);
        }

        $text = match ($dim) {
            'occupation'  => $this->occupationLabel($key, $p['occ_level'], $ar, $standard),
            'industry'    => ($s = Sector::query()->where('code', $key)->first()) ? ($ar ? $s->name_ar : $s->name_en) : $key,
            'gender'      => ScreenWords::get('ben.gender_'.$key, $locale),
            'governorate' => ScreenWords::get('gov.'.$key, $locale),
            'education'   => ScreenWords::get('ben.edu_'.$key, $locale),
            'age', 'experience', 'salary' => __("reports.band.$dim.$key", [], $locale),
            'stage'       => __("reports.stage.$key", [], $locale),
            'month'       => Carbon::parse($key.'-01')->locale($locale)->translatedFormat('M Y'),
            'quarter'     => str_replace('-', ' ', $key),
            default       => $key,
        };

        return $this->labels[$cacheKey] = $text;
    }

    private function occupationLabel(string $key, string $level, bool $ar, string $standard): string
    {
        if (str_starts_with($key, 'esco:')) {
            $e = EscoOccupation::query()->where('code', substr($key, 5))->first();

            return 'ESCO '.substr($key, 5).($e ? ' · '.($ar ? ($e->title_ar_male ?: $e->title_ar ?: $e->title_en) : ucfirst((string) $e->title_en)) : '');
        }
        $code = str_starts_with($key, 'unit:') ? substr($key, 5) : $key;
        $g = IscoGroup::query()->with('enoc')->where('code', $code)->first();
        $title = $g ? ($ar ? ($g->title_ar ?: $g->title_en) : $g->title_en) : '';
        if (strlen($code) === 4 && $standard === 'enoc' && $g?->enoc) {
            return 'ENOC '.$code.' · '.($ar ? $g->enoc->title_ar : $g->title_en);
        }

        return 'ISCO-08 '.$code.($title !== '' ? ' · '.$title : '');
    }

    /** The Egypt market average wage of each 4-digit occupation row. @return array<string, ?int> */
    private function market(array $rows): array
    {
        $out = [];
        foreach ($rows as $code) {
            if ($code === '_none' || strlen((string) $code) !== 4) {
                continue;
            }
            $unit = IscoGroup::query()->where('code', $code)->value('id');
            $m = $unit ? $this->catalog->market((int) $unit, forPartners: true) : null;
            $w = $m['profile']['wage_avg'] ?? null;
            $out[(string) $code] = $w && (float) $w > 0 ? (int) round((float) $w) : null;
        }

        return $out;
    }

    // ── The question in words ────────────────────────────────────────

    /** @return list<string> */
    public function summary(array $p, string $locale, string $standard = 'enoc'): array
    {
        $f = $p['f'];
        $nf = fn ($n) => number_format((int) $n);
        [$from, $to] = self::dates($p['range']);
        $rangeText = $p['range']['preset'] === 'custom'
            ? __('reports.range.custom', ['from' => $from?->locale($locale)->translatedFormat('j M Y') ?? '…', 'to' => $to?->locale($locale)->translatedFormat('j M Y') ?? '…'], $locale)
            : __('reports.range.'.$p['range']['preset'], [], $locale);
        $out = [__($f['stage'] === 'placed' ? 'reports.summary.placed' : 'reports.summary.registered', ['range' => '('.$rangeText.')'], $locale)];

        if ($f['occ']) {
            $out[] = implode(' / ', array_map(fn ($v) => $this->filterOccupation($v, $locale === 'ar', $standard), $f['occ']));
        }
        if ($f['industry']) {
            $out[] = implode(' / ', array_map(fn ($v) => $this->label('industry', $v, $p, $locale), $f['industry']));
        }
        if ($f['gender']) {
            $out[] = ScreenWords::get('ben.gender_'.$f['gender'], $locale);
        }
        foreach (['age' => 'age', 'exp' => 'exp', 'salary' => 'sal'] as $k => $w) {
            [$a, $b] = $f[$k];
            if ($a !== null || $b !== null) {
                $key = $a !== null && $b !== null ? $w : ($a !== null ? $w.'_min' : $w.'_max');
                $out[] = __("reports.summary.$key", ['from' => $nf($a), 'to' => $nf($b)], $locale);
            }
        }
        if ($f['gov']) {
            $out[] = implode(', ', array_map(fn ($g) => ScreenWords::get('gov.'.$g, $locale), $f['gov']));
        }
        if ($f['edu']) {
            $out[] = implode(', ', array_map(fn ($e) => ScreenWords::get('ben.edu_'.$e, $locale), $f['edu']));
        }
        if ($f['stage'] && $f['stage'] !== 'placed') {
            $out[] = __('reports.stage_filter.'.$f['stage'], [], $locale);
        }
        $out[] = __('reports.measure.'.$p['measure'], [], $locale).' · '.($p['cols']
            ? __('reports.summary.by_two', ['rows' => __('reports.dim.'.$p['rows'], [], $locale), 'cols' => __('reports.dim.'.$p['cols'], [], $locale)], $locale)
            : __('reports.summary.by', ['rows' => __('reports.dim.'.$p['rows'], [], $locale)], $locale));

        return $out;
    }

    private function filterOccupation(string $v, bool $ar, string $standard): string
    {
        [$std, $code] = explode(':', $v, 2);
        if ($std === 'esco') {
            return $this->occupationLabel('esco:'.$code, 'esco', $ar, $standard);
        }
        $label = $this->occupationLabel($code, 'unit', $ar, $std === 'enoc' ? 'enoc' : 'isco');

        return $label;
    }
}
