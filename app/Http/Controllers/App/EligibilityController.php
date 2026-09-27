<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\EligibilityAssessment;
use App\Models\Opportunity;
use App\Models\EligibilityRun;
use App\Services\Cv\CvBankSearch;
use App\Services\Eligibility\Assessor;
use App\Services\Eligibility\RunStepper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\EligibilityController (Step 11)
//  Location: app/Http/Controllers/App/EligibilityController.php
//
//  Since Step 12, every result belongs to a job or a training.
//
//  POST  /app/beneficiaries/{number}/eligibility   check    one person against one job or   eligibility.check
//                                                            training (opportunity_id)
//  POST  /app/eligibility/{id}/decide               decide   the case worker's decision,     eligibility.decide
//                                                            with a required reason
//  PATCH /app/eligibility/{id}/notes                notes    notes on one result             eligibility.decide
//  POST  /app/{jobs|training}/{id}/runs             start    "Check everyone": the workspace, eligibility.check
//                                                            a CV Bank search, or the
//                                                            results the new rules outdated
//  POST  /app/eligibility/runs/{run}/step           step     the next few hundred (JSON)     eligibility.check
//
//  Everything is looked up in the signed-in person's workspace only.
// ══════════════════════════════════════════════════════════════════

class EligibilityController extends Controller
{
    public function __construct(
        private readonly Assessor $assessor,
        private readonly RunStepper $runs,
    ) {}

    public function check(Request $request, int $number): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $b = Beneficiary::query()->inWorkspace($companyId)->where('number', $number)->firstOrFail();
        $o = Opportunity::query()->inWorkspace($companyId)->whereKey((int) $request->input('opportunity_id'))->first();
        if (! $o || ! $o->isOpen()) {
            throw ValidationException::withMessages(['opportunity_id' => __('opportunities.v.opportunity')]);
        }

        $a = $this->assessor->assess($o, $b->loadMissing('escoOccupation:id,code'), $request->user()->name);

        return back()->with('success', __('opportunities.checked', [
            'title' => $o->title, 'result' => __('assessments.result.'.$a->result), 'score' => $a->score,
        ]));
    }

    public function decide(Request $request, int $id): RedirectResponse
    {
        $a = $this->find($request, $id);
        $data = $request->validate([
            'decision' => ['required', Rule::in([...EligibilityAssessment::DECISIONS, 'auto'])],
            'reason'   => ['required', 'string', 'max:500'],
        ], [
            'decision.*' => __('assessments.v.decision'),
            'reason.*'   => __('assessments.v.reason'),
        ]);
        if (trim($data['reason']) === '') {
            throw ValidationException::withMessages(['reason' => __('assessments.v.reason')]);
        }
        if ($data['decision'] === 'auto' && ! $a->decision) {
            throw ValidationException::withMessages(['decision' => __('assessments.v.no_decision')]);
        }

        $this->assessor->decide($a, $data['decision'], trim($data['reason']), $request->user());

        return back()->with('success', __('assessments.decided'));
    }

    public function notes(Request $request, int $id): RedirectResponse
    {
        $a = $this->find($request, $id);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $this->assessor->saveNotes($a, $data['notes'] ?? null, $request->user());

        return back()->with('success', __('assessments.notes_saved'));
    }

    public function start(Request $request, int $opportunity): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $kind = (string) $request->route()?->parameter('kind');
        $p = Opportunity::query()->inWorkspace($companyId)->ofKind($kind)->whereKey($opportunity)->firstOrFail();
        if (! $p->isOpen()) {
            return back()->with('error', __('opportunities.closed_no_check'));
        }
        $scope = in_array($request->input('scope'), EligibilityRun::SCOPES, true) ? $request->input('scope') : 'all';

        // The CV Bank's own filters, checked the same way the CV Bank checks them.
        $occ = (string) $request->input('filters.occ', '');
        $filters = [
            'q'           => mb_substr(trim((string) $request->input('filters.q', '')), 0, 120),
            'occ'         => preg_match('/^(isco|enoc|esco):[\d.]{1,40}$/', $occ) ? $occ : '',
            'governorate' => in_array($request->input('filters.governorate'), config('beneficiaries.governorates'), true) ? $request->input('filters.governorate') : '',
            'min_years'   => max(0, min(40, (int) $request->input('filters.min_years', 0))),
            'cv_lang'     => in_array($request->input('filters.cv_lang'), ['ar', 'en'], true) ? $request->input('filters.cv_lang') : '',
            'sector'      => preg_match('/^(IND|TRD|SRV|[ITS]\d{2})$/', (string) $request->input('filters.sector')) ? (string) $request->input('filters.sector') : '',
            'stage'       => in_array($request->input('filters.stage'), CvBankSearch::STAGES, true) ? $request->input('filters.stage') : '',
            // Step 14: people opened from a report.
            'report'      => CvBankSearch::reportOf($request->input('filters.report')) ? (string) $request->input('filters.report') : '',
        ];

        $run = $this->runs->start($p, $scope, $filters, $request->user());
        $redirect = redirect()->route("app.{$p->section()}.show", $p->id);

        return $run->total ? $redirect : $redirect->with('error', __('opportunities.run_nobody'));
    }

    public function step(Request $request, int $run): JsonResponse
    {
        $r = EligibilityRun::query()->inWorkspace($this->companyId($request))->whereKey($run)->firstOrFail();
        $r = $this->runs->step($r, (int) $request->input('at', -1), $request->user()->name);

        return response()->json($r->present());
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function companyId(Request $request): int
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($companyId, 404);

        return $companyId;
    }

    private function find(Request $request, int $id): EligibilityAssessment
    {
        return EligibilityAssessment::query()->inWorkspace($this->companyId($request))->whereKey($id)->firstOrFail();
    }
}
