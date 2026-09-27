<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\SaveOpportunityRequest;
use App\Models\Beneficiary;
use App\Models\EligibilityAssessment;
use App\Models\EligibilityRun;
use App\Models\Opportunity;
use App\Models\OpportunityMatch;
use App\Services\Beneficiaries\OccupationDisplay;
use App\Services\Cv\CvBankSearch;
use App\Services\Eligibility\RunStepper;
use App\Services\Employers\EmployerBook;
use App\Services\Matches\MatchBook;
use App\Services\Matches\MatchFit;
use App\Services\Matches\Matcher;
use App\Services\Opportunities\OpportunityBook;
use App\Support\TextNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\OpportunityController (Step 12 · Jobs & Training)
//  Location: app/Http/Controllers/App/OpportunityController.php
//  Scope: docs/SCOPE_JOBS_AND_TRAINING.md
//
//  The same screens for both kinds. {section} is jobs or training; the
//  route gives the kind (->defaults('kind', 'job' | 'training')), read with
//  kind() — Laravel hands route values over by position, not by name:
//
//  GET    /app/{section}                 index    the list, open / closed       opportunities.view
//  GET    /app/{section}/create          create   the form: details + eligibility opportunities.manage
//  POST   /app/{section}                 store    saved once, together          opportunities.manage
//  GET    /app/{section}/{id}            show     details, rules, Check everyone, opportunities.view
//                                                 the shortlist (with the fit and
//                                                 Refer, Step 13), and its Matches
//  GET    /app/{section}/{id}/edit       edit                                   opportunities.manage
//  PATCH  /app/{section}/{id}            update   new rules → rules_version + 1  opportunities.manage
//  POST   /app/{section}/{id}/copy       copy     same details and rules         opportunities.manage
//  POST   /app/{section}/{id}/close      close    with a reason; results kept    opportunities.manage
//  POST   /app/{section}/{id}/reopen     reopen                                 opportunities.manage
//  DELETE /app/{section}/{id}            destroy  only with no results           opportunities.manage
//
//  An opportunity is looked up in the signed-in person's workspace and
//  of the address's kind only: anything else is simply "not found".
// ══════════════════════════════════════════════════════════════════

class OpportunityController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(
        private readonly OpportunityBook $book,
        private readonly RunStepper $runs,
        private readonly Matcher $matcher,
        private readonly MatchBook $matches,
        private readonly MatchFit $fit,
    ) {}

    public function index(Request $request): Response
    {
        $kind = $this->kind($request);
        $companyId = $this->companyId($request);
        $occ = (string) $request->query('occ', '');
        $f = [
            'tab'         => $request->query('tab') === 'closed' ? 'closed' : 'open',
            'q'           => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'occ'         => OpportunityBook::validValue($occ) ? $occ : '',
            'governorate' => in_array($request->query('governorate'), config('beneficiaries.governorates'), true) ? $request->query('governorate') : '',
        ];

        $query = Opportunity::query()->inWorkspace($companyId)->ofKind($kind)->where('status', $f['tab'])
            ->when($f['occ'] !== '', fn ($q) => $q->where('occupation_keys', 'like', '%|'.$f['occ'].'|%'))
            ->when($f['governorate'] !== '', fn ($q) => $q->where('governorates', 'like', '%"'.$f['governorate'].'"%'));
        foreach (TextNormalizer::tokens($f['q']) as $token) {
            $query->where(fn ($w) => $w->where('title', 'like', '%'.$token.'%')
                ->orWhere('employer', 'like', '%'.$token.'%')->orWhere('provider', 'like', '%'.$token.'%'));
        }

        /** @var LengthAwarePaginator $page */
        $page = $query->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
        $counts = EligibilityAssessment::query()->inWorkspace($companyId)->whereIn('opportunity_id', $page->getCollection()->pluck('id'))
            ->selectRaw('opportunity_id, result, count(*) as n')->groupBy('opportunity_id', 'result')->get()
            ->groupBy('opportunity_id');
        $taken = $this->matcher->seatsTaken($companyId, $page->getCollection()->pluck('id')->all());
        $page->through(fn (Opportunity $o) => $this->book->summary($o) + [
            'counts'      => $this->countsFrom($counts[$o->id] ?? collect()),
            'seats_taken' => $taken[$o->id] ?? 0,          // Step 13: Accepted onwards
        ]);

        return Inertia::render('App/Opportunities/Index', [
            'kind'       => $kind,
            'section'    => Opportunity::SECTION[$kind],
            'list'       => $page,
            'filters'    => $f,
            'occupation' => $f['occ'] ? app(CvBankSearch::class)->occupationLabel($f['occ'], app()->getLocale()) : null,
            'tabs'       => [
                'open'   => Opportunity::query()->inWorkspace($companyId)->ofKind($kind)->open()->count(),
                'closed' => Opportunity::query()->inWorkspace($companyId)->ofKind($kind)->where('status', Opportunity::CLOSED)->count(),
            ],
            'governorates' => config('beneficiaries.governorates'),
        ]);
    }

    public function create(Request $request): Response
    {
        $kind = $this->kind($request);
        $company = $request->user()->company;

        return Inertia::render('App/Opportunities/Form', [
            'kind'        => $kind,
            'section'     => Opportunity::SECTION[$kind],
            'opportunity' => null,
            'workspace'   => $company?->name,
        ] + $this->book->formOptions($this->companyId($request)));
    }

    public function store(SaveOpportunityRequest $request, EmployerBook $employers): RedirectResponse
    {
        $kind = $this->kind($request);
        $user = $request->user();
        $details = $request->details();
        $o = new Opportunity;
        $o->forceFill($details + [
            'company_id'      => $this->companyId($request),
            'kind'            => $kind,
            'occupation_keys' => $this->book->keys($details['occupations']),
            'rules'           => $request->cleanRules(),
            'rules_version'   => 1,
            'status'          => Opportunity::OPEN,
            'created_by'      => $user->id,
            'created_by_name' => mb_substr($user->name, 0, 100),
            'updated_by_name' => mb_substr($user->name, 0, 100),
        ]);
        $o->note('created', $user->name);
        $o->save();
        $this->learnEmployer($o, $employers, $user->id);

        return redirect()->route("app.{$o->section()}.show", $o->id)
            ->with('success', __('opportunities.created', ['kind' => __("opportunities.kind.$kind")]));
    }

    public function show(Request $request, int $opportunity): Response
    {
        $kind = $this->kind($request);
        $o = $this->find($request, $kind, $opportunity);
        $companyId = (int) $o->company_id;
        $f = [
            'result' => in_array($request->query('result'), [...EligibilityAssessment::AUTO_RESULTS, EligibilityAssessment::ON_HOLD], true) ? $request->query('result') : '',
            'look'   => $request->boolean('look'),
            'q'      => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'sort'   => in_array($request->query('sort'), ['recent', 'fit'], true) ? $request->query('sort') : 'score',
        ];

        $query = EligibilityAssessment::query()->inWorkspace($companyId)->where('eligibility_assessments.opportunity_id', $o->id)
            ->join('beneficiaries as b', 'b.id', '=', 'eligibility_assessments.beneficiary_id')
            ->select('eligibility_assessments.*')
            ->when($f['result'] !== '', fn ($q) => $q->where('eligibility_assessments.result', $f['result']))
            ->when($f['look'], fn ($q) => $q->where(fn ($w) => $w->whereNotNull('decision_flag')
                ->orWhere('eligibility_assessments.rules_version', '<', $o->rules_version)));
        foreach (TextNormalizer::tokens($f['q']) as $token) {
            ctype_digit($token) && strlen($token) < 6
                ? $query->where('b.number', (int) $token)
                : $query->where('b.search_text', 'like', '%'.$token.'%');
        }
        $byResult = "CASE eligibility_assessments.result WHEN 'eligible' THEN 0 WHEN 'check' THEN 1 WHEN 'on_hold' THEN 2 ELSE 3 END";
        if ($f['sort'] === 'recent') {
            $query->orderByDesc('eligibility_assessments.checked_at');
        } elseif ($f['sort'] === 'fit') {
            // Step 13: Eligible first, then the best occupation fit (information only), then the score.
            [$fitSql, $fitBind] = $this->fit->occupationRankSql($o, 'b');
            $query->orderByRaw($byResult)->orderByRaw("($fitSql) DESC", $fitBind)
                ->orderByDesc('eligibility_assessments.score')->orderBy('b.number');
        } else {
            // The shortlist: Eligible first, then Check, On hold, Not eligible — each by score.
            $query->orderByRaw($byResult)->orderByDesc('eligibility_assessments.score')->orderBy('b.number');
        }
        $seeMatches = $request->user()->can('matches.view');

        $list = $query->with(['beneficiary' => fn ($q) => $q->withoutGlobalScopes()->with(['unit.enoc', 'escoOccupation'])])
            ->paginate(25)->withQueryString();
        // Step 13: each line's match (if any), its fit, and where the person is already hired.
        $lineMatches = $seeMatches ? $this->matches->ofPeople($companyId, $o->id, $list->getCollection()->pluck('beneficiary_id')->all()) : [];
        $list->through(function (EligibilityAssessment $a) use ($o, $lineMatches, $seeMatches) {
                $b = $a->beneficiary;

                return [
                    'id'       => $a->id,
                    'person'   => $b ? [
                        'number' => $b->number, 'name' => $b->displayName(), 'initials' => $b->initials(), 'age' => $b->age(),
                        'governorate' => $b->governorate, 'occupation' => OccupationDisplay::block($b->unit, $b->escoOccupation, $b->gender),
                    ] : null,
                    'score'    => $a->score,
                    'auto'     => $a->auto_result,
                    'result'   => $a->result,
                    'decision' => $a->decision,
                    'flag'     => $a->decision_flag,
                    'outdated' => (int) $a->rules_version !== (int) $o->rules_version,
                    'missing'  => count(array_filter($a->reasons ?? [], fn ($r) => ($r['status'] ?? '') === 'missing')),
                    'must_failed' => count(array_filter($a->reasons ?? [], fn ($r) => ($r['status'] ?? '') === 'fail' && ($r['rule']['mode'] ?? '') === 'must')),
                    'checked_at' => $a->checked_at?->toIso8601String(),
                    'match'      => MatchBook::brief($lineMatches[$a->beneficiary_id] ?? null),
                    'fit'        => $b && $seeMatches ? ['occupation' => $this->fit->occupation($o, $b), 'skills' => $this->fit->skills($o, $b)] : null,
                    'elsewhere'  => $b && $seeMatches && $a->result === EligibilityAssessment::ELIGIBLE ? $this->matcher->elsewhere($b, $o->id) : [],
                ];
            });

        $counts = EligibilityAssessment::query()->inWorkspace($companyId)->where('opportunity_id', $o->id)
            ->selectRaw('result, count(*) as n')->groupBy('result')->get();
        $run = EligibilityRun::query()->inWorkspace($companyId)->where('opportunity_id', $o->id)->orderByDesc('id')->first();

        return Inertia::render('App/Opportunities/Show', [
            'opportunity' => $this->book->detail($o),
            'market'      => $o->isJob() ? $this->book->market($o->occupations ?? []) : null,
            'counts'      => $this->countsFrom($counts),
            'outdated'    => $this->runs->outdatedQuery($o)->count(),
            'to_look'     => EligibilityAssessment::query()->inWorkspace($companyId)->where('opportunity_id', $o->id)->whereNotNull('decision_flag')->count(),
            'people'      => Beneficiary::query()->inWorkspace($companyId)->count(),
            'list'        => $list,
            'filters'     => $f,
            'run'         => $run?->present(),
            'options'     => $this->book->formOptions($companyId),
            // Step 13: its matches (every person referred, with the stage) and the seats taken.
            'matches'     => $seeMatches ? $this->matches->query($companyId)->where('opportunity_matches.opportunity_id', $o->id)
                ->with(MatchBook::with(opportunity: false))->orderByDesc('opportunity_matches.changed_at')->limit(500)->get()
                ->map(fn (OpportunityMatch $m) => $this->matches->present($m, opportunity: false))->values() : null,
            'seats'       => $this->matcher->seats($o),
            'skills_asked' => $seeMatches ? count($this->fit->neededSkills($o)) : 0,
            'stop_reasons' => OpportunityMatch::STOP_REASONS[$o->kind],
        ]);
    }

    public function edit(Request $request, int $opportunity): Response
    {
        $kind = $this->kind($request);
        $o = $this->find($request, $kind, $opportunity);

        return Inertia::render('App/Opportunities/Form', [
            'kind'        => $kind,
            'section'     => $o->section(),
            'opportunity' => $this->book->detail($o) + [
                'results' => EligibilityAssessment::query()->inWorkspace((int) $o->company_id)->where('opportunity_id', $o->id)->count(),
            ],
            'workspace'   => $request->user()->company?->name,
        ] + $this->book->formOptions((int) $o->company_id));
    }

    public function update(SaveOpportunityRequest $request, int $opportunity, EmployerBook $employers): RedirectResponse
    {
        $kind = $this->kind($request);
        $o = $this->find($request, $kind, $opportunity);
        $user = $request->user();
        $details = $request->details();
        $rules = $request->cleanRules();
        $changed = json_encode($rules) !== json_encode($o->rules)
            || $details['eligible_from'] !== $o->eligible_from
            || $details['check_from'] !== $o->check_from;

        $o->forceFill($details + [
            'occupation_keys' => $this->book->keys($details['occupations']),
            'rules'           => $rules,
            'rules_version'   => $changed ? $o->rules_version + 1 : $o->rules_version,
            'updated_by_name' => mb_substr($user->name, 0, 100),
        ]);
        $o->note($changed ? 'rules_changed' : 'edited', $user->name);
        $o->save();
        $this->learnEmployer($o, $employers, $user->id);

        $outdated = $changed ? $this->runs->outdatedQuery($o)->count() : 0;
        $what = __("opportunities.kind.$kind");

        return redirect()->route("app.{$o->section()}.show", $o->id)
            ->with('success', $outdated ? __('opportunities.saved_outdated', ['kind' => $what, 'n' => $outdated]) : __('opportunities.saved', ['kind' => $what]));
    }

    public function copy(Request $request, int $opportunity): RedirectResponse
    {
        $kind = $this->kind($request);
        $o = $this->find($request, $kind, $opportunity);
        $user = $request->user();
        $copy = $o->replicate(['status', 'close_reason', 'closed_at', 'history', 'rules_version', 'created_by', 'created_by_name', 'updated_by_name']);
        $copy->forceFill([
            'title'           => mb_substr($o->title.' '.__('opportunities.copy_suffix'), 0, 150),
            'status'          => Opportunity::OPEN,
            'close_reason'    => null,
            'closed_at'       => null,
            'history'         => null,
            'rules_version'   => 1,
            'created_by'      => $user->id,
            'created_by_name' => mb_substr($user->name, 0, 100),
            'updated_by_name' => mb_substr($user->name, 0, 100),
        ]);
        $copy->note('copied', $user->name, ['from' => $o->title]);
        $copy->save();

        return redirect()->route("app.{$copy->section()}.edit", $copy->id)->with('success', __('opportunities.copied'));
    }

    public function close(Request $request, int $opportunity): RedirectResponse
    {
        $kind = $this->kind($request);
        $o = $this->find($request, $kind, $opportunity);
        $data = $request->validate(['reason' => ['required', Rule::in(Opportunity::CLOSE_REASONS)]], ['reason.*' => __('opportunities.v.close_reason')]);

        $o->forceFill(['status' => Opportunity::CLOSED, 'close_reason' => $data['reason'], 'closed_at' => now()]);
        $o->note('closed', $request->user()->name, ['reason' => $data['reason']]);
        $o->save();
        EligibilityRun::query()->inWorkspace((int) $o->company_id)->where('opportunity_id', $o->id)->where('status', 'running')
            ->update(['status' => 'done', 'finished_at' => now()]);

        return back()->with('success', __('opportunities.closed', ['kind' => __("opportunities.kind.$kind")]));
    }

    public function reopen(Request $request, int $opportunity): RedirectResponse
    {
        $kind = $this->kind($request);
        $o = $this->find($request, $kind, $opportunity);
        $o->forceFill(['status' => Opportunity::OPEN, 'close_reason' => null, 'closed_at' => null]);
        $o->note('reopened', $request->user()->name);
        $o->save();

        return back()->with('success', __('opportunities.reopened', ['kind' => __("opportunities.kind.$kind")]));
    }

    public function destroy(Request $request, int $opportunity): RedirectResponse
    {
        $kind = $this->kind($request);
        $o = $this->find($request, $kind, $opportunity);
        if (EligibilityAssessment::query()->inWorkspace((int) $o->company_id)->where('opportunity_id', $o->id)->exists()) {
            return back()->with('error', __('opportunities.cannot_delete'));
        }
        $o->delete();

        return redirect()->route("app.{$o->section()}.index")->with('success', __('opportunities.deleted', ['kind' => __("opportunities.kind.$kind")]));
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** A company typed by hand, with its sector chosen, is remembered by this workspace (as in work histories). */
    private function learnEmployer(Opportunity $o, EmployerBook $employers, ?int $userId): void
    {
        if ($o->isJob() && $o->employer && ! $o->employer_id && $o->sub_sector) {
            $employers->learn((int) $o->company_id, [['employer' => $o->employer, 'sub_sector' => $o->sub_sector, 'country' => 'EG']], $userId);
        }
    }

    private function countsFrom($rows): array
    {
        $out = ['eligible' => 0, 'check' => 0, 'not_eligible' => 0, 'on_hold' => 0];
        foreach ($rows as $r) {
            $out[$r->result] = (int) $r->n;
        }
        $out['total'] = array_sum($out);

        return $out;
    }

    /** job | training — from the address (/app/jobs… or /app/training…). */
    private function kind(Request $request): string
    {
        $k = (string) $request->route()?->parameter('kind');
        abort_unless(in_array($k, Opportunity::KINDS, true), 404);

        return $k;
    }

    private function companyId(Request $request): int
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($companyId, 404);

        return $companyId;
    }

    /** One opportunity of the signed-in person's own workspace, of this kind, or 404. */
    private function find(Request $request, string $kind, int $id): Opportunity
    {
        return Opportunity::query()->inWorkspace($this->companyId($request))->ofKind($kind)->whereKey($id)->firstOrFail();
    }
}
