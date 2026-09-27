<?php

namespace App\Services\Backbone;

use App\Models\Backbone\EnocOccupation;
use App\Models\Backbone\EnocMarketProfile;
use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Models\Backbone\LabourMarketEdition;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — OccupationCatalog (browse, search and occupation pages)
//  Location: app/Services/Backbone/OccupationCatalog.php
//
//  Everything a screen needs to show the occupation backbone, in one
//  place, so the Super Admin screens (Admin\BackboneController) and
//  the partner Occupations page (App\OccupationController) always
//  show the same thing:
//    · browse / search in the chosen standard
//        ENOC    → the 426 Egyptian occupations, by Egyptian major group
//        ISCO-08 → the four-level tree (or a flat list when searching)
//        ESCO    → the ~3,000 detailed occupations
//    · one unit group (= one ENOC occupation) with its ESCO jobs
//    · one ESCO occupation, shown in all three standards
//    · the Egypt labour market figures of the CURRENT edition
//    · the ESCO skills (Step 5, from SkillCatalog): a unit group's most
//      needed skills, an ESCO job's essential and optional skills
//
//  PARTNERS ($forPartners = true)
//  Figures flagged at import (they cannot be right as published: a
//  sector share of 976%, 408 hours a week …) are REMOVED here, on the
//  server, before anything reaches a partner's browser. The panel then
//  says "under review" for them. The six regions outside Greater Cairo
//  are removed the same way while the edition says they are identical
//  everywhere. The Super Admin still sees everything, with warnings.
// ══════════════════════════════════════════════════════════════════

class OccupationCatalog
{
    public const STANDARDS = ['enoc', 'isco', 'esco'];

    private const PER_PAGE = 50;

    /** Which figures each import flag hides from partners. */
    private const HIDDEN_BY_FLAG = [
        'sectors'        => ['sectors'],
        'hours'          => ['weekly_hours'],
        'public_private' => ['pct_public', 'pct_private'],
        'employment'     => ['pct_paid', 'pct_unpaid', 'pct_formal', 'pct_regular', 'pct_women', 'pct_public', 'pct_private'],
    ];

    private const PROFILE_FIELDS = [
        'workers', 'workers_trend', 'share_of_employment', 'pct_paid', 'pct_unpaid', 'pct_formal', 'pct_regular',
        'pct_women', 'pct_public', 'pct_private', 'sectors', 'regions', 'wage_avg', 'wage_male', 'wage_female',
        'wage_public', 'wage_private', 'weekly_hours', 'education', 'knowledge', 'abilities', 'skills',
        'skill_groups', 'outlook_trend', 'outlook_jobs', 'green',
    ];

    public function __construct(private readonly OccupationSearch $search, private readonly SkillCatalog $skills) {}

    public function loaded(): bool
    {
        return DB::table('isco_groups')->exists();
    }

    /**
     * The standard to show (?standard=…, else the user's own choice, else
     * ENOC) and the cleaned search filters.
     *
     * @return array{0: string, 1: array{q: string, major: string}}
     */
    public function filters(Request $request): array
    {
        $standard = in_array($request->query('standard'), self::STANDARDS, true)
            ? $request->query('standard')
            : ($request->user()?->occupation_standard ?: 'enoc');

        return [$standard, [
            'q'     => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'major' => preg_match('/^\d$/', (string) $request->query('major')) ? (string) $request->query('major') : '',
        ]];
    }

    /** The list for the browse screen, in the chosen standard. */
    public function browse(string $standard, array $f): array
    {
        return match ($standard) {
            'enoc'  => ['enoc' => $this->enocList($f)],
            'isco'  => $f['q'] !== '' ? ['units' => $this->unitSearch($f)] : ['tree' => $this->iscoTree($f['major'])],
            default => ['esco' => $this->escoList($f)],
        };
    }

    /** Page data for one 4-digit group (ISCO-08 unit = ENOC occupation). Null when not found. */
    public function unitPage(string $code, bool $forPartners = false): ?array
    {
        $unit = IscoGroup::query()->where('code', $code)->where('level', IscoGroup::LEVEL_UNIT)->first();
        if (! $unit) {
            return null;
        }
        $enoc = EnocOccupation::query()->where('isco_group_id', $unit->id)->first();

        $esco = EscoOccupation::query()
            ->where('isco_group_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_key')
            ->get(['id', 'code', 'parent_id', 'depth', 'title_en', 'title_ar_male', 'title_ar_female', 'is_regulated']);

        return [
            'unit' => [
                ...$this->groupBrief($unit),
                'definition_en' => $unit->definition_en,
                'tasks_en'      => $unit->tasks_en,
                'included_en'   => $unit->included_en,
                'excluded_en'   => $unit->excluded_en,
                'notes_en'      => $unit->notes_en,
            ],
            'lineage' => array_map(fn (IscoGroup $g) => $this->groupBrief($g), $unit->lineage()),
            'enoc'    => $enoc ? $this->enocBrief($enoc) + ['description_ar' => $enoc->description_ar] : null,
            'market'  => $this->market($unit->id, $forPartners),
            'skills'  => $this->skills->forUnit($unit->id),
            'esco'    => $esco->map(fn (EscoOccupation $o) => [
                'id'        => $o->id,
                'code'      => $o->code,
                'depth'     => $o->depth,
                'title_en'  => $o->title_en,
                'title_ar'  => $o->title_ar_male,
                'title_ar_f'=> $o->title_ar_female,
                'regulated' => $o->is_regulated,
            ]),
        ];
    }

    /** Page data for one ESCO occupation. */
    public function escoPage(EscoOccupation $escoOccupation, bool $forPartners = false): array
    {
        $o = $escoOccupation->load('iscoGroup');
        $unit = $o->iscoGroup;
        $enoc = EnocOccupation::query()->where('isco_group_id', $unit->id)->first();

        $labels = $o->labels()->whereIn('kind', ['alt'])->orderBy('label')->get(['lang', 'label']);

        return [
            'occupation' => [
                'id'             => $o->id,
                'uri'            => $o->uri,
                'code'           => $o->code,
                'title_en'       => $o->title_en,
                'title_ar'       => $o->title_ar,
                'title_ar_m'     => $o->title_ar_male,
                'title_ar_f'     => $o->title_ar_female,
                'description_en' => $o->description_en,
                'description_ar' => $o->description_ar,
                'definition_en'  => $o->definition_en,
                'scope_note_en'  => $o->scope_note_en,
                'regulated'      => $o->is_regulated,
                'active'         => $o->is_active,
                'version'        => $o->esco_version,
                'alt_en'         => $labels->where('lang', 'en')->pluck('label')->values(),
                'alt_ar'         => $labels->where('lang', 'ar')->pluck('label')->values(),
            ],
            'ancestors' => array_map(fn (EscoOccupation $a) => $this->escoBrief($a), $o->ancestors()),
            'children'  => $o->children()->where('is_active', true)->orderBy('sort_key')->get()->map(fn ($c) => $this->escoBrief($c)),
            'lineage'   => array_map(fn (IscoGroup $g) => $this->groupBrief($g), $unit->lineage()),
            'unit'      => $this->groupBrief($unit),
            'enoc'      => $enoc ? $this->enocBrief($enoc) : null,
            'market'    => $this->market($unit->id, $forPartners),
            'skills'    => $this->skills->forOccupation($o->id),
        ];
    }

    // ── Egypt labour market ──────────────────────────────────────────

    /** The current edition's figures for one unit group (= ENOC occupation), or null. */
    public function market(int $unitId, bool $forPartners = false): ?array
    {
        $edition = LabourMarketEdition::current();
        if (! $edition) {
            return null;
        }

        $regionsSuspect = (bool) ($edition->quality['regions_outside_cairo_identical'] ?? false);
        $p = EnocMarketProfile::query()->where('edition_id', $edition->id)->where('isco_group_id', $unitId)->first();
        $profile = $p ? $p->only(self::PROFILE_FIELDS) + ['flags' => $p->flags ?? []] : null;

        return [
            'edition'         => $this->editionBrief($edition),
            'regions_suspect' => $regionsSuspect,
            'profile'         => $profile && $forPartners ? $this->forPartners($profile, $regionsSuspect) : $profile,
        ];
    }

    public function editionBrief(LabourMarketEdition $e): array
    {
        return [
            'id'               => $e->id,
            'name'             => $e->name,
            'name_ar'          => $e->name_ar,
            'reference_period' => $e->reference_period,
            'reference_estimated' => $e->reference_estimated,
            'source'           => $e->source,
            'is_current'       => $e->is_current,
            'loaded_at'        => $e->created_at?->toIso8601String(),
        ];
    }

    /**
     * Remove what cannot be shown to partners. What was removed is
     * listed in 'hidden' (so the panel can say "under review"); the
     * import flags themselves are not sent.
     */
    private function forPartners(array $profile, bool $regionsSuspect): array
    {
        $hidden = [];
        foreach ($profile['flags'] as $flag) {
            foreach (self::HIDDEN_BY_FLAG[$flag] ?? [] as $field) {
                $profile[$field] = null;
                $hidden[] = $field;
            }
        }
        if ($regionsSuspect && is_array($profile['regions'])) {
            $profile['regions'] = array_intersect_key($profile['regions'], ['cairo' => true]);
            $hidden[] = 'regions_outside_cairo';
        }
        unset($profile['flags']);

        return $profile + ['hidden' => array_values(array_unique($hidden))];
    }

    // ── Browse ───────────────────────────────────────────────────────

    /** The 10 major groups with both names (ENOC has its own Egyptian Arabic names for 1–9). */
    public function majors(): array
    {
        $enoc = DB::table('enoc_major_groups')->pluck('title_ar', 'code');

        return IscoGroup::query()->where('level', IscoGroup::LEVEL_MAJOR)->orderBy('code')->get(['code', 'title_en', 'title_ar'])
            ->map(fn ($g) => [
                'code'          => $g->code,
                'title_en'      => $g->title_en,
                'title_ar'      => $g->title_ar,
                'enoc_title_ar' => $enoc[$g->code] ?? null,
            ])->all();
    }

    // ── Browse: ENOC ─────────────────────────────────────────────────

    private function enocList(array $f): LengthAwarePaginator
    {
        $base = EnocOccupation::query()->where('enoc_occupations.is_active', true)
            ->when($f['major'] !== '', fn ($q) => $q->where('enoc_occupations.major_code', $f['major']));

        $matches = [];
        if ($f['q'] !== '') {
            $matches = $this->search->units($f['q'], $f['major'] ?: null);
            $ids = (clone $base)->whereIn('isco_group_id', array_keys($matches) ?: [0])->pluck('isco_group_id')->flip();
            $matches = array_filter($matches, fn ($id) => isset($ids[$id]), ARRAY_FILTER_USE_KEY);
        }

        $load = fn ($query) => $query->join('isco_groups', 'isco_groups.id', '=', 'enoc_occupations.isco_group_id')
            ->select(['enoc_occupations.*', 'isco_groups.title_en as isco_title_en', 'isco_groups.title_ar as isco_title_ar'])
            ->selectSub(fn ($s) => $s->from('esco_occupations')->selectRaw('count(*)')
                ->whereColumn('esco_occupations.isco_group_id', 'enoc_occupations.isco_group_id')->where('esco_occupations.is_active', true), 'esco_count');

        $row = fn ($o, ?array $m) => [
            'code'        => $o->code,
            'title_ar'    => $o->title_ar,
            'title_en'    => $o->isco_title_en,
            'esco_count'  => (int) $o->esco_count,
            'match'       => $m ? ['label' => $m['label'], 'lang' => $m['lang']] : null,
        ];

        if ($f['q'] === '') {
            return $load($base)->orderBy('enoc_occupations.code')->paginate(self::PER_PAGE)->withQueryString()
                ->through(fn ($o) => $row($o, null));
        }

        return $this->pageOf($matches, fn (array $ids) => $load(EnocOccupation::query())->whereIn('enoc_occupations.isco_group_id', $ids)
            ->get()->keyBy('isco_group_id'), $row);
    }

    // ── Browse: ISCO-08 ──────────────────────────────────────────────

    /** The whole four-level tree (619 rows — small enough to send at once). */
    private function iscoTree(string $major): array
    {
        $groups = IscoGroup::query()
            ->when($major !== '', fn ($q) => $q->where('major_code', $major))
            ->orderBy('code')->get(['id', 'code', 'level', 'title_en', 'title_ar']);

        $escoCounts = DB::table('esco_occupations')->where('is_active', true)
            ->selectRaw('isco_code, count(*) as n')->groupBy('isco_code')->pluck('n', 'isco_code');
        $inEnoc = DB::table('enoc_occupations')->where('is_active', true)->pluck('code')->flip();

        $nodes = [];
        foreach ($groups as $g) {
            $nodes[$g->code] = [
                'code' => $g->code, 'level' => $g->level, 'title_en' => $g->title_en, 'title_ar' => $g->title_ar,
                'esco_count' => $g->level === 4 ? (int) ($escoCounts[$g->code] ?? 0) : null,
                'in_enoc' => $g->level === 4 ? isset($inEnoc[$g->code]) : null,
                'units' => 0, 'children' => [],
            ];
        }
        // Attach bottom-up so each node carries its finished children.
        foreach (array_reverse(array_keys($nodes)) as $code) {
            if (strlen($code) > 1 && isset($nodes[substr($code, 0, -1)])) {
                $parent = substr($code, 0, -1);
                $nodes[$parent]['units'] += $nodes[$code]['level'] === 4 ? 1 : $nodes[$code]['units'];
                array_unshift($nodes[$parent]['children'], $nodes[$code]);
            }
        }

        return array_values(array_filter($nodes, fn ($n) => $n['level'] === 1));
    }

    private function unitSearch(array $f): LengthAwarePaginator
    {
        $matches = $this->search->units($f['q'], $f['major'] ?: null);

        return $this->pageOf($matches, fn (array $ids) => IscoGroup::query()->whereIn('id', $ids)
            ->withCount(['escoOccupations as esco_count' => fn ($q) => $q->where('is_active', true)])
            ->with('enoc:id,isco_group_id,code')->get()->keyBy('id'),
            fn ($g, ?array $m) => [
                'code'       => $g->code,
                'title_en'   => $g->title_en,
                'title_ar'   => $g->title_ar,
                'esco_count' => $g->esco_count,
                'in_enoc'    => (bool) $g->enoc,
                'match'      => $m ? ['label' => $m['label'], 'lang' => $m['lang']] : null,
            ]);
    }

    // ── Browse: ESCO ─────────────────────────────────────────────────

    private function escoList(array $f): LengthAwarePaginator
    {
        $row = fn ($o, ?array $m) => [
            'id'        => $o->id,
            'code'      => $o->code,
            'isco_code' => $o->isco_code,
            'title_en'  => $o->title_en,
            'title_ar'  => $o->title_ar_male,
            'title_ar_f'=> $o->title_ar_female,
            'regulated' => $o->is_regulated,
            'match'     => $m ? ['label' => $m['label'], 'lang' => $m['lang']] : null,
        ];
        $columns = ['id', 'code', 'isco_code', 'title_en', 'title_ar_male', 'title_ar_female', 'is_regulated'];

        if ($f['q'] === '') {
            return EscoOccupation::query()->where('is_active', true)
                ->when($f['major'] !== '', fn ($q) => $q->where('isco_code', 'like', $f['major'].'%'))
                ->orderBy('sort_key')->paginate(self::PER_PAGE, $columns)->withQueryString()
                ->through(fn ($o) => $row($o, null));
        }

        return $this->pageOf($this->search->esco($f['q'], $f['major'] ?: null),
            fn (array $ids) => EscoOccupation::query()->whereIn('id', $ids)->where('is_active', true)->get($columns)->keyBy('id'),
            $row);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * One page of ranked search results: slice the ranked ids, load only
     * that page's rows, keep the ranking order.
     */
    private function pageOf(array $ranked, callable $load, callable $row): LengthAwarePaginator
    {
        $page = max(1, (int) request()->query('page', 1));
        $ids = array_slice(array_keys($ranked), ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $models = $ids ? $load($ids) : collect();

        $items = [];
        foreach ($ids as $id) {
            if (isset($models[$id])) {
                $items[] = $row($models[$id], $ranked[$id]);
            }
        }

        return (new LengthAwarePaginator($items, count($ranked), self::PER_PAGE, $page, [
            'path' => request()->url(),
        ]))->withQueryString();
    }

    public function groupBrief(IscoGroup $g): array
    {
        return ['code' => $g->code, 'level' => $g->level, 'title_en' => $g->title_en, 'title_ar' => $g->title_ar];
    }

    private function enocBrief(EnocOccupation $e): array
    {
        return [
            'code'           => $e->code,
            'title_ar'       => $e->title_ar,
            'major_title_ar' => DB::table('enoc_major_groups')->where('code', $e->major_code)->value('title_ar'),
        ];
    }

    private function escoBrief(EscoOccupation $o): array
    {
        return ['id' => $o->id, 'code' => $o->code, 'title_en' => $o->title_en, 'title_ar' => $o->title_ar_male, 'title_ar_f' => $o->title_ar_female];
    }
}
