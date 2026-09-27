<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Services\Cv\CvBankIndex;
use App\Services\Cv\CvBankSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\CvBankController (Scope v2 §3 Searchable CV Bank)
//  Location: app/Http/Controllers/App/CvBankController.php
//  Permission: beneficiaries.view
//
//  GET /app/cv-bank                     index        search the people of this workspace
//                                                    by every word in their CVs and profiles
//  GET /app/cv-bank/occupations?q=…     occupations  occupations of any standard and level,
//                                                    for the occupation filter (JSON)
//
//  Only the signed-in person's workspace is ever searched.
// ══════════════════════════════════════════════════════════════════

class CvBankController extends Controller
{
    public function __construct(private readonly CvBankSearch $search) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($companyId, 404);
        $locale = app()->getLocale();
        $f = $this->filters($request);
        $searched = $f['q'] !== '' || $f['occ'] !== '' || $f['governorate'] !== '' || $f['min_years'] > 0 || $f['cv_lang'] !== '' || $f['sector'] !== '' || $f['stage'] !== '' || $f['report'] !== '';
        // Step 14: "Open these people" from a report — the question in words, shown above the list.
        $report = CvBankSearch::reportOf($f['report']);

        return Inertia::render('App/CvBank/Index', [
            'filters'  => $f,
            'report'   => $report ? [
                'summary' => app(\App\Services\Reports\ReportBuilder::class)->summary($report['p'], $locale, $request->user()->occupation_standard ?: 'enoc'),
                'row'     => $report['row'] !== null ? app(\App\Services\Reports\ReportBuilder::class)->label($report['p']['rows'], $report['row'], $report['p'], $locale, $request->user()->occupation_standard ?: 'enoc') : null,
                'col'     => $report['col'] !== null && $report['p']['cols'] ? app(\App\Services\Reports\ReportBuilder::class)->label($report['p']['cols'], $report['col'], $report['p'], $locale, $request->user()->occupation_standard ?: 'enoc') : null,
            ] : null,
            'occupation' => $f['occ'] ? $this->search->occupationLabel($f['occ'], $locale) : null,
            'searched' => $searched,
            'list'     => $searched && CvBankIndex::ready() ? $this->search->search($companyId, $f, $locale) : null,
            'total'    => Beneficiary::query()->inWorkspace($companyId)->count(),
            'ready'    => CvBankIndex::ready(),
            'governorates' => config('beneficiaries.governorates'),
            'sectors'      => \App\Models\Sector::tree(),
            // Step 11/12: "Check these people against a job or training".
            'opportunities' => $request->user()->can('eligibility.check')
                ? \App\Models\Opportunity::query()->inWorkspace($companyId)->open()->orderBy('kind')->orderBy('title')->get()
                    ->map(fn ($o) => $o->brief())->values()
                : [],
        ]);
    }

    public function occupations(Request $request): JsonResponse
    {
        return response()->json($this->search->occupationOptions(mb_substr((string) $request->query('q', ''), 0, 60), app()->getLocale()));
    }

    private function filters(Request $request): array
    {
        $occ = (string) $request->query('occ', '');

        return [
            'q'           => mb_substr(trim((string) $request->query('q', '')), 0, 120),
            'occ'         => preg_match('/^(isco|enoc|esco):[\d.]{1,40}$/', $occ) ? $occ : '',
            'governorate' => in_array($request->query('governorate'), config('beneficiaries.governorates'), true) ? $request->query('governorate') : '',
            'min_years'   => max(0, min(40, (int) $request->query('min_years', 0))),
            'cv_lang'     => in_array($request->query('cv_lang'), ['ar', 'en'], true) ? $request->query('cv_lang') : '',
            'sector'      => preg_match('/^(IND|TRD|SRV|[ITS]\d{2})$/', (string) $request->query('sector')) ? (string) $request->query('sector') : '',
            'stage'       => in_array($request->query('stage'), CvBankSearch::STAGES, true) ? $request->query('stage') : '',
            'report'      => CvBankSearch::reportOf($request->query('report')) ? (string) $request->query('report') : '',
        ];
    }
}
