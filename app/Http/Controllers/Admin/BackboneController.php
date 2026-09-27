<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backbone\BackboneImport;
use App\Models\Backbone\EnocOccupation;
use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\EscoSkill;
use App\Models\Backbone\LabourMarketEdition;
use App\Models\Backbone\IscoGroup;
use App\Services\Backbone\MarketImporter;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Backbone\SkillCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — BackboneController (Super Admin · Occupation Backbone)
//  Location: app/Http/Controllers/Admin/BackboneController.php
//  Permission: platform.backbone
//
//  GET /admin/backbone              index  — what is loaded, data
//                                            checks, import history,
//                                            and a browser/search in
//                                            the chosen standard
//  GET /admin/backbone/units/{code} unit   — one ISCO-08 unit group
//                                            = one ENOC occupation,
//                                            with all its ESCO
//                                            occupations
//  GET /admin/backbone/esco/{id}    esco   — one ESCO occupation
//                                            shown in all three
//                                            standards
//  GET /admin/backbone/skills/{id} skill  — one ESCO skill: where it
//                                            sits, related skills and
//                                            the jobs that need it
//  PATCH /admin/backbone/editions/{id}/use
//                                   useEdition — show this labour
//                                            market edition everywhere
//                                            (after review). Records
//                                            who and when.
//
//  Unit and ESCO pages also carry the Egypt labour market figures of
//  the CURRENT edition (an ESCO job shows its ENOC occupation's).
//
//  The index also shows the ESCO skills (Step 5): counts, data checks,
//  the skills import history and a skill search (?skill_q=…).
//
//  Browsing, search and the occupation pages come from the shared
//  OccupationCatalog, which the partner Occupations page uses too.
//  The browser follows the Standard switch in the top bar:
//    ENOC    → the 426 Egyptian occupations, by Egyptian major group
//    ISCO-08 → the four-level tree (or a flat list when searching)
//    ESCO    → the ~3,000 detailed occupations
//
//  The figures themselves are changed only by `backbone:import` and
//  `market:import`; the only change made here is which labour market
//  edition is in use.
// ══════════════════════════════════════════════════════════════════

class BackboneController extends Controller
{
    public function __construct(private readonly OccupationCatalog $catalog, private readonly SkillCatalog $skills) {}

    public function index(Request $request): Response
    {
        [$standard, $filters] = $this->catalog->filters($request);
        $loaded = $this->catalog->loaded();

        return Inertia::render('Admin/Backbone/Index', [
            'standard' => $standard,
            'filters'  => $filters,
            'loaded'   => $loaded,
            'summary'  => $loaded ? $this->summary() : null,
            'majors'   => $loaded ? $this->catalog->majors() : [],
            'browse'   => $loaded ? $this->catalog->browse($standard, $filters) : null,
            'skills'   => $loaded ? $this->skills->summary() : null,
            'skillSearch' => fn () => [
                'q'       => $q = mb_substr(trim((string) $request->query('skill_q', '')), 0, 100),
                'results' => $loaded ? $this->skills->search($q) : [],
            ],
            'imports'  => BackboneImport::query()->with('user:id,name')->latest('id')->limit(8)->get()
                ->map(fn (BackboneImport $i) => [
                    'id'          => $i->id,
                    'status'      => $i->status,
                    'message'     => $i->message,
                    'editions'    => $i->editions,
                    'counts'      => $i->counts ? array_intersect_key($i->counts, array_flip(['isco_unit', 'enoc_occupations', 'esco_occupations', 'labels'])) : null,
                    'by'          => $i->user?->name,
                    'via'         => $i->triggered_via,
                    'started_at'  => $i->started_at?->toIso8601String(),
                    'seconds'     => $i->started_at && $i->finished_at ? $i->started_at->diffInSeconds($i->finished_at) : null,
                ]),
        ]);
    }

    /** "Use this edition" — the reviewed edition becomes the one shown everywhere. */
    public function useEdition(Request $request, LabourMarketEdition $edition): RedirectResponse
    {
        if ($edition->status !== LabourMarketEdition::COMPLETED) {
            return back()->with('error', __('common.edition_not_usable', ['id' => $edition->id]));
        }

        if (! $edition->is_current) {
            MarketImporter::make()->makeCurrent($edition, $request->user()->id);
        }

        return back()->with('success', __('common.edition_in_use', ['id' => $edition->id]));
    }

    public function unit(string $code): Response
    {
        $page = $this->catalog->unitPage($code);
        abort_if(! $page, 404);

        return Inertia::render('Admin/Backbone/Unit', $page);
    }

    public function esco(EscoOccupation $escoOccupation): Response
    {
        return Inertia::render('Admin/Backbone/Esco', $this->catalog->escoPage($escoOccupation));
    }

    public function skill(EscoSkill $escoSkill): Response
    {
        return Inertia::render('Admin/Backbone/Skill', $this->skills->skillPage($escoSkill));
    }

    // ── Summary & checks ─────────────────────────────────────────────

    private function summary(): array
    {
        $last = BackboneImport::lastCompleted();
        $counts = $last?->counts ?? [];

        $unitTitles = fn (array $codes) => IscoGroup::query()->whereIn('code', $codes)->orderBy('code')
            ->get(['code', 'title_en', 'title_ar'])->map(fn ($g) => $g->only(['code', 'title_en', 'title_ar']))->all();

        return [
            'counts' => [
                'isco'        => DB::table('isco_groups')->count(),
                'isco_levels' => DB::table('isco_groups')->selectRaw('level, count(*) as n')->groupBy('level')->pluck('n', 'level'),
                'enoc'        => DB::table('enoc_occupations')->where('is_active', true)->count(),
                'enoc_major'  => DB::table('enoc_major_groups')->count(),
                'esco'        => DB::table('esco_occupations')->where('is_active', true)->count(),
                'labels'      => DB::table('occupation_labels')->count(),
                'labels_ar'   => DB::table('occupation_labels')->where('lang', 'ar')->count(),
                'labels_en'   => DB::table('occupation_labels')->where('lang', 'en')->count(),
            ],
            'editions'  => $last?->editions ?? config('backbone.editions'),
            'market'    => $this->marketSummary(),
            'last'      => $last ? ['id' => $last->id, 'at' => $last->finished_at?->toIso8601String()] : null,
            'checks'    => [
                'units_without_enoc'   => $unitTitles($counts['units_without_enoc'] ?? []),
                'units_without_esco'   => $unitTitles($counts['units_without_esco'] ?? []),
                'enoc_without_esco'    => $counts['enoc_without_esco'] ?? null,
                'esco_duplicates'      => $counts['esco_duplicate_rows_skipped'] ?? null,
                'esco_with_arabic_alt' => $counts['esco_with_arabic_alt'] ?? null,
            ],
        ];
    }


    // ── Egypt labour market ──────────────────────────────────────────

    private function marketSummary(): array
    {
        $flagged = fn (array $codes) => EnocOccupation::query()->whereIn('code', $codes)->orderBy('code')
            ->get(['code', 'title_ar'])->map(fn ($e) => $e->only(['code', 'title_ar']))->all();

        return [
            'current'  => ($c = LabourMarketEdition::current()) ? $this->catalog->editionBrief($c) + [
                'counts'  => $c->counts,
                'quality' => [
                    'regions_outside_cairo_identical' => $c->quality['regions_outside_cairo_identical'] ?? false,
                    'wage_copies_disagree'            => $c->quality['wage_copies_disagree'] ?? [],
                    'flagged'                         => array_map($flagged, $c->quality['flagged'] ?? []),
                ],
            ] : null,
            'editions' => LabourMarketEdition::query()->with('approver:id,name')->latest('id')->limit(10)->get()->map(fn ($e) => $this->catalog->editionBrief($e) + [
                'status'     => $e->status,
                'message'    => $e->message,
                // What to review before choosing it: how many occupations
                // have each kind of figure to check.
                'flagged'    => array_map('count', $e->quality['flagged'] ?? []),
                'regions_suspect' => (bool) ($e->quality['regions_outside_cairo_identical'] ?? false),
                'made_current_at' => $e->made_current_at?->toIso8601String(),
                'made_current_by' => $e->approver?->name,
                'comparison' => $e->comparison ? [
                    'added'       => count($e->comparison['added'] ?? []),
                    'dropped'     => count($e->comparison['dropped'] ?? []),
                    'big_changes' => count($e->comparison['big_changes'] ?? []),
                    'against'     => $e->comparison['against_edition'] ?? null,
                ] : null,
            ])->all(),
        ];
    }
}
