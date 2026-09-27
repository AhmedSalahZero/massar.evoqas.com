<?php

namespace App\Services\Dashboard;

use App\Models\Backbone\IscoGroup;
use App\Models\Beneficiary;
use App\Models\CvDocument;
use App\Models\EligibilityAssessment;
use App\Models\Opportunity;
use App\Models\OpportunityMatch;
use App\Models\User;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Beneficiaries\OccupationDisplay;
use App\Services\Matches\MatchFit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — PartnerDashboard (Step 15 · the dashboard, as the agreed demo)
//  Location: app/Services/Dashboard/PartnerDashboard.php
//  The agreed demo: docs/dashboard-demo.html
//
//  Every number on a partner's dashboard, for one date range
//  (30 days · 3 months · 12 months · all time). "Your people" means the
//  people REGISTERED in the range (all time = everyone); placements count
//  on the date they happened.
//
//    kpis        beneficiaries (+ new), CV Bank (% read automatically),
//                open jobs & training (seats), placed (hired · completed),
//                placement rate (of people assessed; days on average)
//    funnel      registered → assessed → eligible → matched → placed:
//                people who reached each stage at least once
//    attention   matches with no change for 14 days, results to look at
//                again, CVs waiting for review, deadlines passed, full jobs
//    months      new people by month (by how they joined), placements by month
//    people      gender, age, education, governorates, industry
//    market      the top occupations next to the Egypt market wage and its
//                outlook to 2030; expected salary vs market wage; the ESCO
//                skills most lacking among Eligible people
//    opportunities, follow_up, team   the tables at the bottom
//
//  Only this workspace's own records are ever read.
// ══════════════════════════════════════════════════════════════════

class PartnerDashboard
{
    public const RANGES = ['m1', 'm3', 'm12', 'all'];

    /** Pairs of (person, job or training) looked at for the missing skills, at most. */
    private const SKILL_PAIRS = 400;

    public function __construct(
        private readonly OccupationCatalog $catalog,
        private readonly MatchFit $fit,
    ) {}

    /** @return array{0: ?Carbon, 1: Carbon} */
    public static function window(string $range): array
    {
        $now = now();

        return [match ($range) {
            'm1'  => $now->copy()->subDays(30)->startOfDay(),
            'm3'  => $now->copy()->subMonthsNoOverflow(3)->startOfDay(),
            'm12' => $now->copy()->subMonthsNoOverflow(12)->startOfDay(),
            default => null,
        }, $now];
    }

    /** The months a monthly chart shows for this range ('Y-m'). */
    public static function months(string $range, ?Carbon $first = null): array
    {
        [$from] = self::window($range);
        $start = ($from ?? ($first ?? now()->subMonthsNoOverflow(11)))->copy()->startOfMonth();
        if ($range === 'all' && $start->lt(now()->subMonthsNoOverflow(23)->startOfMonth())) {
            $start = now()->subMonthsNoOverflow(23)->startOfMonth();   // at most two years of bars
        }
        if ($range === 'm12') {
            $start = now()->subMonthsNoOverflow(11)->startOfMonth();
        }
        $out = [];
        for ($m = $start->copy(); $m->lte(now()); $m->addMonthNoOverflow()) {
            $out[] = $m->format('Y-m');
        }

        return $out;
    }

    public function build(User $user, string $range): array
    {
        $cid = (int) $user->company_id;
        [$from, $to] = self::window($range);
        $seePeople = $user->can('beneficiaries.view');
        $seeOpps = $user->can('opportunities.view');
        $seeMatches = $user->can('matches.view');

        $people = Beneficiary::query()->withoutGlobalScopes()->where('company_id', $cid)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->get(['id', 'gender', 'date_of_birth', 'governorate', 'education_level', 'isco_group_id', 'isco_code', 'expected_salary', 'industry', 'source', 'created_at']);
        $ids = $people->pluck('id')->all();
        $stage = $this->stages($cid);
        $months = self::months($range, Beneficiary::query()->withoutGlobalScopes()->where('company_id', $cid)->min('created_at') ? Carbon::parse(Beneficiary::query()->withoutGlobalScopes()->where('company_id', $cid)->min('created_at')) : null);

        $placedQ = fn () => OpportunityMatch::query()->withoutGlobalScopes()->where('company_id', $cid)->placed()
            ->when($from, fn ($q) => $q->where('stage_on', '>=', $from));
        $placedRows = $placedQ()->get(['kind', 'stage_on', 'referred_on']);

        // The funnel: the people registered in the range, and how far they got.
        $funnel = ['registered' => count($ids), 'assessed' => 0, 'eligible' => 0, 'matched' => 0, 'placed' => 0];
        foreach ($ids as $id) {
            $r = $stage[$id] ?? 0;
            $r >= 1 && $funnel['assessed']++;
            $r >= 2 && $funnel['eligible']++;
            $r >= 3 && $funnel['matched']++;
            $r >= 4 && $funnel['placed']++;
        }

        $out = [
            'range'  => $range,
            'months' => $months,
            'kpis'   => $this->kpis($cid, $from, $people, $placedRows, $funnel, $months),
            'funnel' => $seePeople ? $funnel : null,
            'attention' => $this->attention($cid, $user),
            'months_new' => $seePeople ? $this->byMonth($people->all(), $months, 'created_at', 'source', ['intake', 'cv_upload', 'pool', 'manual']) : null,
            'months_placed' => $this->byMonth($placedRows->all(), $months, 'stage_on', 'kind', ['job', 'training']),
            'people' => $seePeople ? $this->people($people) : null,
            'market' => $seePeople ? $this->market($cid, $people, $stage) : null,
            'skills' => $seePeople && $seeOpps ? $this->skillsLacking($cid) : null,
            'opportunities' => $seeOpps ? $this->opportunities($cid) : null,
            'follow_up' => $seeMatches ? $this->followUp($cid) : null,
            'team' => $seePeople ? $this->team($cid, $from) : null,
        ];

        return $out;
    }

    // ── The numbers at the top ───────────────────────────────────────

    private function kpis(int $cid, ?Carbon $from, $people, $placedRows, array $funnel, array $months): array
    {
        $allPeople = Beneficiary::query()->withoutGlobalScopes()->where('company_id', $cid)->count();
        $cvs = CvDocument::query()->withoutGlobalScopes()->where('company_id', $cid);
        $uploaded = (clone $cvs)->where('status', '!=', CvDocument::REJECTED)->when($from, fn ($q) => $q->where('created_at', '>=', $from));
        $uploadedN = (clone $uploaded)->count();
        $auto = (clone $uploaded)->where('status', CvDocument::ADDED)->count();
        $open = Opportunity::query()->withoutGlobalScopes()->where('company_id', $cid)->open();
        $days = $placedRows->map(fn ($m) => $m->referred_on && $m->stage_on ? $m->referred_on->diffInDays($m->stage_on) : null)->filter(fn ($d) => $d !== null);
        $spark = fn (array $dates) => $this->sparkline($dates);

        return [
            'people'     => $allPeople,
            'people_new' => $people->count(),
            'people_spark' => $spark(Beneficiary::query()->withoutGlobalScopes()->where('company_id', $cid)->where('created_at', '>=', now()->subMonthsNoOverflow(11)->startOfMonth())->pluck('created_at')->all()),
            'cvs'        => (clone $cvs)->whereIn('status', CvDocument::ON_PROFILE)->count(),
            'cvs_auto'   => $uploadedN ? (int) round($auto / $uploadedN * 100) : null,
            'cvs_spark'  => $spark((clone $cvs)->where('created_at', '>=', now()->subMonthsNoOverflow(11)->startOfMonth())->pluck('created_at')->all()),
            'jobs'       => (clone $open)->ofKind(Opportunity::JOB)->count(),
            'trainings'  => (clone $open)->ofKind(Opportunity::TRAINING)->count(),
            'seats'      => (int) (clone $open)->sum('seats'),
            'hired'      => $placedRows->where('kind', Opportunity::JOB)->count(),
            'completed'  => $placedRows->where('kind', Opportunity::TRAINING)->count(),
            'placed_spark' => $spark(OpportunityMatch::query()->withoutGlobalScopes()->where('company_id', $cid)->placed()->where('stage_on', '>=', now()->subMonthsNoOverflow(11)->startOfMonth())->pluck('stage_on')->all()),
            'rate'       => $funnel['assessed'] ? round($funnel['placed'] / $funnel['assessed'] * 100, 1) : null,
            'days'       => $days->count() ? (int) round($days->avg()) : null,
        ];
    }

    /** Twelve monthly counts (the small lines in the top cards). */
    private function sparkline(array $dates): array
    {
        $months = [];
        for ($m = now()->subMonthsNoOverflow(11)->startOfMonth(); $m->lte(now()); $m->addMonthNoOverflow()) {
            $months[$m->format('Y-m')] = 0;
        }
        foreach ($dates as $d) {
            $k = Carbon::parse($d)->format('Y-m');
            isset($months[$k]) && $months[$k]++;
        }

        return array_values($months);
    }

    // ── Needs your attention ─────────────────────────────────────────

    private function attention(int $cid, User $user): array
    {
        $open = Opportunity::query()->withoutGlobalScopes()->where('company_id', $cid)->open();
        $openIds = (clone $open)->pluck('id');
        $taken = OpportunityMatch::query()->withoutGlobalScopes()->where('company_id', $cid)->whereIn('opportunity_id', $openIds)->seated()
            ->selectRaw('opportunity_id, count(*) as n')->groupBy('opportunity_id')->pluck('n', 'opportunity_id');
        $full = (clone $open)->get(['id', 'seats'])->filter(fn ($o) => ($taken[$o->id] ?? 0) >= $o->seats)->count();
        $look = EligibilityAssessment::query()->withoutGlobalScopes()->where('eligibility_assessments.company_id', $cid)
            ->join('opportunities as o', 'o.id', '=', 'eligibility_assessments.opportunity_id')->where('o.status', Opportunity::OPEN)
            ->where(fn ($w) => $w->whereNotNull('eligibility_assessments.decision_flag')->orWhereColumn('eligibility_assessments.rules_version', '<', 'o.rules_version'))
            ->count();

        return [
            'follow'   => $user->can('matches.view') ? OpportunityMatch::query()->withoutGlobalScopes()->where('company_id', $cid)->needsFollowUp()->count() : null,
            'look'     => $user->can('opportunities.view') ? $look : null,
            'queue'    => $user->can('cv.review') ? CvDocument::query()->withoutGlobalScopes()->where('company_id', $cid)->whereIn('status', CvDocument::OPEN)->count() : null,
            'deadline' => $user->can('opportunities.view') ? (clone $open)->whereNotNull('deadline')->where('deadline', '<', today())->count() : null,
            'full'     => $user->can('opportunities.view') ? $full : null,
        ];
    }

    // ── By month ─────────────────────────────────────────────────────

    /** @return array<string, list<int>> one line per series, one number per month */
    private function byMonth(array $rows, array $months, string $dateField, string $seriesField, array $series): array
    {
        $at = array_flip($months);
        $out = array_fill_keys($series, array_fill(0, count($months), 0));
        foreach ($rows as $r) {
            $d = $r->{$dateField};
            if (! $d) {
                continue;
            }
            $k = Carbon::parse($d)->format('Y-m');
            $s = in_array($r->{$seriesField}, $series, true) ? $r->{$seriesField} : ($seriesField === 'source' ? 'manual' : null);
            if ($s !== null && isset($at[$k])) {
                $out[$s][$at[$k]]++;
            }
        }

        return $out;
    }

    // ── Your people ──────────────────────────────────────────────────

    private function people($people): array
    {
        $ageBands = ['lt20' => 0, '20_24' => 0, '25_29' => 0, '30_34' => 0, '35_44' => 0, '45p' => 0];
        foreach ($people as $b) {
            if (! $b->date_of_birth) {
                continue;
            }
            $a = $b->date_of_birth->age;
            $k = $a < 20 ? 'lt20' : ($a < 25 ? '20_24' : ($a < 30 ? '25_29' : ($a < 35 ? '30_34' : ($a < 45 ? '35_44' : '45p'))));
            $ageBands[$k]++;
        }
        $top = function ($counts, int $n) {
            arsort($counts);
            $keys = array_keys($counts);
            $out = [];
            foreach (array_slice($keys, 0, $n) as $k) {
                $out[] = ['key' => (string) $k, 'n' => $counts[$k]];
            }
            if (count($keys) > $n) {
                $out[] = ['key' => '_other', 'n' => array_sum(array_slice($counts, $n)), 'more' => count($keys) - $n];
            }

            return $out;
        };
        $industry = $people->map(fn ($b) => $b->industry ?: '_none')->countBy()->all();
        $none = $industry['_none'] ?? 0;
        unset($industry['_none']);
        $ind = $top($industry, 7);
        if ($none) {
            $ind[] = ['key' => '_none', 'n' => $none];
        }
        $sectors = DB::table('sectors')->whereIn('code', array_keys($industry))->get(['code', 'name_en', 'name_ar'])->keyBy('code');

        return [
            'gender'      => ['female' => $people->where('gender', 'female')->count(), 'male' => $people->where('gender', 'male')->count()],
            'age'         => $ageBands,
            'education'   => $top($people->map(fn ($b) => $b->education_level ?: '_none')->countBy()->all(), 5),
            'governorate' => $top($people->pluck('governorate')->countBy()->all(), 7),
            'industry'    => array_map(fn ($r) => $r + ['name_en' => $sectors[$r['key']]->name_en ?? null, 'name_ar' => $sectors[$r['key']]->name_ar ?? null], $ind),
        ];
    }

    // ── Your people and Egypt's labour market ────────────────────────

    private function market(int $cid, $people, array $stage): array
    {
        $byUnit = $people->whereNotNull('isco_group_id')->groupBy('isco_group_id')->sortByDesc(fn ($g) => $g->count())->take(10);
        $units = IscoGroup::query()->with('enoc')->whereIn('id', $byUnit->keys())->get()->keyBy('id');
        $rows = [];
        foreach ($byUnit as $unitId => $group) {
            $unit = $units[$unitId] ?? null;
            if (! $unit) {
                continue;
            }
            $m = $this->catalog->market((int) $unitId, forPartners: true);
            $p = $m['profile'] ?? [];
            $salaries = $group->pluck('expected_salary')->filter()->sort()->values();
            $median = $salaries->count() ? (int) ($salaries->count() % 2 ? $salaries[intdiv($salaries->count(), 2)] : round(($salaries[$salaries->count() / 2 - 1] + $salaries[$salaries->count() / 2]) / 2)) : null;
            $rows[] = [
                'code'     => $unit->code,
                'block'    => OccupationDisplay::block($unit, null),
                'people'   => $group->count(),
                'eligible' => $group->filter(fn ($b) => ($stage[$b->id] ?? 0) >= 2)->count(),
                'placed'   => $group->filter(fn ($b) => ($stage[$b->id] ?? 0) >= 4)->count(),
                'wage'     => isset($p['wage_avg']) && (float) $p['wage_avg'] > 0 ? (int) round((float) $p['wage_avg']) : null,
                'trend'    => $p['outlook_trend'] ?? null,
                'expected' => $median,
                'expected_n' => $salaries->count(),
            ];
        }

        return ['rows' => $rows, 'edition' => $rows ? ($this->catalog->market((int) $byUnit->keys()->first(), forPartners: true)['edition'] ?? null) : null];
    }

    /** The ESCO essential skills most often missing among people Eligible for an open job or training. */
    private function skillsLacking(int $cid): array
    {
        $pairs = EligibilityAssessment::query()->withoutGlobalScopes()->where('eligibility_assessments.company_id', $cid)
            ->where('eligibility_assessments.result', EligibilityAssessment::ELIGIBLE)
            ->join('opportunities as o', 'o.id', '=', 'eligibility_assessments.opportunity_id')->where('o.status', Opportunity::OPEN)
            ->orderByDesc('eligibility_assessments.checked_at')->limit(self::SKILL_PAIRS)
            ->get(['eligibility_assessments.opportunity_id', 'eligibility_assessments.beneficiary_id']);
        if ($pairs->isEmpty()) {
            return ['rows' => [], 'people' => 0];
        }
        $opps = Opportunity::query()->withoutGlobalScopes()->whereIn('id', $pairs->pluck('opportunity_id')->unique())->get()->keyBy('id');
        $people = Beneficiary::query()->withoutGlobalScopes()->whereIn('id', $pairs->pluck('beneficiary_id')->unique())->get(['id', 'skills'])->keyBy('id');
        $lacking = [];
        foreach ($pairs as $pair) {
            $o = $opps[$pair->opportunity_id] ?? null;
            $b = $people[$pair->beneficiary_id] ?? null;
            if (! $o || ! $b) {
                continue;
            }
            $s = $this->fit->skills($o, $b);
            foreach ($s['lacking'] as $skill) {
                $key = $skill['en'];
                $lacking[$key] ??= ['en' => $skill['en'], 'ar' => $skill['ar'], 'people' => []];
                $lacking[$key]['people'][$b->id] = true;
            }
        }
        $rows = array_map(fn ($r) => ['en' => $r['en'], 'ar' => $r['ar'], 'n' => count($r['people'])], array_values($lacking));
        usort($rows, fn ($a, $b) => [$b['n'], $a['en']] <=> [$a['n'], $b['en']]);

        return ['rows' => array_slice($rows, 0, 7), 'people' => $people->count()];
    }

    // ── The tables ───────────────────────────────────────────────────

    private function opportunities(int $cid): array
    {
        $list = Opportunity::query()->withoutGlobalScopes()->where('company_id', $cid)->open()
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END')->orderBy('deadline')->orderByDesc('id')->limit(8)->get();
        $ids = $list->pluck('id');
        $eligible = EligibilityAssessment::query()->withoutGlobalScopes()->whereIn('opportunity_id', $ids)->where('result', 'eligible')
            ->selectRaw('opportunity_id, count(*) as n')->groupBy('opportunity_id')->pluck('n', 'opportunity_id');
        $referred = OpportunityMatch::query()->withoutGlobalScopes()->whereIn('opportunity_id', $ids)
            ->selectRaw('opportunity_id, count(*) as n')->groupBy('opportunity_id')->pluck('n', 'opportunity_id');
        $taken = OpportunityMatch::query()->withoutGlobalScopes()->whereIn('opportunity_id', $ids)->seated()
            ->selectRaw('opportunity_id, count(*) as n')->groupBy('opportunity_id')->pluck('n', 'opportunity_id');

        return [
            'total' => Opportunity::query()->withoutGlobalScopes()->where('company_id', $cid)->open()->count(),
            'rows'  => $list->map(fn (Opportunity $o) => [
                'id' => $o->id, 'kind' => $o->kind, 'section' => $o->section(), 'title' => $o->title, 'by' => $o->isJob() ? $o->employer : $o->provider,
                'seats' => $o->seats, 'eligible' => (int) ($eligible[$o->id] ?? 0), 'referred' => (int) ($referred[$o->id] ?? 0),
                'taken' => (int) ($taken[$o->id] ?? 0), 'deadline' => $o->deadline?->toDateString(),
                'state' => ($taken[$o->id] ?? 0) >= $o->seats ? 'full' : ($o->deadline && $o->deadline->lt(today()) ? 'passed' : 'open'),
            ])->values()->all(),
        ];
    }

    private function followUp(int $cid): array
    {
        $q = OpportunityMatch::query()->withoutGlobalScopes()->where('opportunity_matches.company_id', $cid)->needsFollowUp();

        return [
            'total' => (clone $q)->count(),
            'rows'  => (clone $q)->with(['beneficiary' => fn ($x) => $x->withoutGlobalScopes(), 'opportunity' => fn ($x) => $x->withoutGlobalScopes()])
                ->orderBy('changed_at')->limit(5)->get()
                ->map(fn (OpportunityMatch $m) => [
                    'id' => $m->id, 'kind' => $m->kind, 'stage' => $m->stage,
                    'days' => (int) $m->changed_at->copy()->startOfDay()->diffInDays(today()),
                    'person' => $m->beneficiary ? ['number' => $m->beneficiary->number, 'name' => $m->beneficiary->displayName()] : null,
                    'opportunity' => $m->opportunity ? ['id' => $m->opportunity->id, 'title' => $m->opportunity->title, 'section' => $m->opportunity->section()] : null,
                ])->values()->all(),
        ];
    }

    private function team(int $cid, ?Carbon $from): array
    {
        $users = User::query()->where('company_id', $cid)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $since = fn ($q, $col) => $from ? $q->where($col, '>=', $from) : $q;
        $registered = $since(Beneficiary::query()->withoutGlobalScopes()->where('company_id', $cid), 'created_at')
            ->selectRaw('created_by, count(*) as n')->groupBy('created_by')->pluck('n', 'created_by');
        $checked = $since(EligibilityAssessment::query()->withoutGlobalScopes()->where('company_id', $cid), 'checked_at')
            ->selectRaw('checked_by_name, count(*) as n')->groupBy('checked_by_name')->pluck('n', 'checked_by_name');
        $referred = $since(OpportunityMatch::query()->withoutGlobalScopes()->where('company_id', $cid), 'created_at')
            ->selectRaw('referred_by, count(*) as n')->groupBy('referred_by')->pluck('n', 'referred_by');
        $placed = $since(DB::table('opportunity_match_events')->where('company_id', $cid)->where('action', 'moved')->where('to_stage', OpportunityMatch::DONE), 'created_at')
            ->selectRaw('user_id, count(*) as n')->groupBy('user_id')->pluck('n', 'user_id');

        return $users->map(fn (User $u) => [
            'id' => $u->id, 'name' => $u->name,
            'registered' => (int) ($registered[$u->id] ?? 0), 'checked' => (int) ($checked[$u->name] ?? 0),
            'referred' => (int) ($referred[$u->id] ?? 0), 'placed' => (int) ($placed[$u->id] ?? 0),
        ])->sortByDesc(fn ($r) => $r['registered'] + $r['checked'] + $r['referred'] + $r['placed'])->values()->all();
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** @return array<int, int> person id → furthest stage (0 registered … 4 placed) */
    private function stages(int $cid): array
    {
        $out = [];
        $mark = function ($ids, int $rank) use (&$out) {
            foreach ($ids as $id) {
                $out[(int) $id] = max($out[(int) $id] ?? 0, $rank);
            }
        };
        $mark(DB::table('eligibility_assessments')->where('company_id', $cid)->distinct()->pluck('beneficiary_id'), 1);
        $mark(DB::table('eligibility_assessments')->where('company_id', $cid)->where('result', 'eligible')->distinct()->pluck('beneficiary_id'), 2);
        $mark(DB::table('opportunity_matches')->where('company_id', $cid)->distinct()->pluck('beneficiary_id'), 3);
        $mark(DB::table('opportunity_matches')->where('company_id', $cid)->where('status', 'active')->where('stage', 'done')->distinct()->pluck('beneficiary_id'), 4);

        return $out;
    }
}
