<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\Opportunity;
use App\Models\OpportunityMatch as M;
use App\Services\Cv\CvBankSearch;
use App\Services\Matches\MatchBook;
use App\Services\Matches\Matcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\MatchController (Step 13 · Matches)
//  Location: app/Http/Controllers/App/MatchController.php
//  Scope: docs/SCOPE_MATCHES.md
//
//  GET  /app/matches                  index     every match of the workspace,  matches.view
//                                               counts per stage, filters,
//                                               "Needs follow-up"
//  GET  /app/matches/{id}/timeline    timeline  one match's timeline (JSON)    matches.view
//  POST /app/matches                  store     refer one or several people    matches.manage
//                                               to one open job or training
//  POST /app/matches/{id}/move        move      forward (skipping allowed)     matches.manage
//  POST /app/matches/{id}/back        back      back one stage, with a reason  matches.manage
//  POST /app/matches/{id}/stop        stop      with a reason                  matches.manage
//  POST /app/matches/{id}/restart     restart   a stopped match, with a reason matches.manage
//
//  Everything is looked up in the signed-in person's workspace only:
//  another partner's match is simply "not found".
// ══════════════════════════════════════════════════════════════════

class MatchController extends Controller
{
    public const PER_PAGE = 25;

    /** People referred in one go, at most. */
    public const MAX_AT_ONCE = 100;

    public function __construct(
        private readonly Matcher $matcher,
        private readonly MatchBook $book,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = $this->companyId($request);
        $occ = (string) $request->query('occ', '');
        $f = [
            'stage'       => in_array($request->query('stage'), MatchBook::FILTER_STAGES, true) ? $request->query('stage') : '',
            'kind'        => in_array($request->query('kind'), Opportunity::KINDS, true) ? $request->query('kind') : '',
            'opportunity' => (int) $request->query('opportunity', 0) ?: '',
            'governorate' => in_array($request->query('governorate'), config('beneficiaries.governorates'), true) ? $request->query('governorate') : '',
            'occ'         => preg_match('/^(isco|enoc|esco):[\d.]{1,40}$/', $occ) ? $occ : '',
            'q'           => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'follow'      => $request->boolean('follow'),
        ];

        $base = $this->book->filter($this->book->query($companyId), $f, withStage: false);
        $counts = $this->book->stageCounts($base);
        $list = $this->book->filter($this->book->query($companyId), $f)
            ->with(MatchBook::with())
            ->orderByDesc('opportunity_matches.changed_at')->orderByDesc('opportunity_matches.id')
            ->paginate(self::PER_PAGE)->withQueryString()
            ->through(fn (M $m) => $this->book->present($m));

        $opportunities = Opportunity::query()->inWorkspace($companyId)
            ->whereIn('id', M::query()->inWorkspace($companyId)->select('opportunity_id'))
            ->orderBy('kind')->orderBy('title')->get(['id', 'kind', 'title', 'status'])
            ->map(fn (Opportunity $o) => $o->brief())->values();

        return Inertia::render('App/Matches/Index', [
            'list'          => $list,
            'counts'        => $counts,
            'filters'       => $f,
            'occupation'    => $f['occ'] ? app(CvBankSearch::class)->occupationLabel($f['occ'], app()->getLocale()) : null,
            'opportunities' => $opportunities,
            'governorates'  => config('beneficiaries.governorates'),
            'stop_reasons'  => M::STOP_REASONS,
            'follow_days'   => M::FOLLOW_UP_DAYS,
        ]);
    }

    public function timeline(Request $request, int $id): JsonResponse
    {
        $m = $this->find($request, $id);

        return response()->json(['events' => $this->book->events($m)]);
    }

    /** Refer one or several people (by their numbers) to one open job or training. */
    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate([
            'opportunity_id' => ['required', 'integer'],
            'numbers'        => ['required', 'array', 'min:1', 'max:'.self::MAX_AT_ONCE],
            'numbers.*'      => ['integer'],
            'on'             => ['nullable', 'string', 'max:10'],
            'note'           => ['nullable', 'string', 'max:500'],
        ], [
            'opportunity_id.*' => __('matches.v.opportunity'),
            'numbers.max'      => __('matches.v.too_many', ['n' => self::MAX_AT_ONCE]),
            'numbers.*'        => __('matches.v.people'),
            'note.*'           => __('matches.v.note'),
        ]);
        $o = Opportunity::query()->inWorkspace($companyId)->whereKey((int) $data['opportunity_id'])->first();
        if (! $o) {
            throw ValidationException::withMessages(['opportunity_id' => __('matches.v.opportunity')]);
        }
        if (! $o->isOpen()) {
            throw ValidationException::withMessages(['opportunity_id' => __('matches.v.closed')]);
        }
        $people = Beneficiary::query()->inWorkspace($companyId)->whereIn('number', array_unique(array_map('intval', $data['numbers'])))->get();
        if ($people->isEmpty()) {
            throw ValidationException::withMessages(['numbers' => __('matches.v.people')]);
        }

        $n = ['referred' => 0, 'already' => 0, 'stopped' => 0, 'not_eligible' => 0];
        $warnings = [];
        $single = null;
        foreach ($people as $b) {
            $r = $this->matcher->refer($o, $b, $request->user(), $data['on'] ?? null, $data['note'] ?? null);
            $n[$r['outcome']]++;
            $single = $r + ['person' => $b];
            if ($r['outcome'] === 'referred') {
                foreach ($this->matcher->elsewhere($b, $o->id) as $e) {
                    $warnings[] = __('matches.already_'.($e['kind'] === 'job' ? 'hired' : 'training'), ['name' => $b->displayName(), 'title' => $e['title']]);
                }
            }
        }

        $back = back();
        if (count($people) === 1) {
            $name = $single['person']->displayName();
            $back = match ($single['outcome']) {
                'referred'     => $back->with('success', __('matches.referred_one', ['name' => $name, 'title' => $o->title])),
                'already'      => $back->with('error', __('matches.already_one', ['name' => $name])),
                'stopped'      => $back->with('error', __('matches.stopped_one', ['name' => $name])),
                'not_eligible' => $back->with('error', __('matches.not_eligible_one', [
                    'name' => $name, 'result' => __('assessments.result.'.$single['result']), 'score' => $single['score'],
                ])),
            };
        } else {
            $parts = [__('matches.referred_n', ['n' => $n['referred'], 'title' => $o->title])];
            foreach (['already', 'stopped', 'not_eligible'] as $k) {
                if ($n[$k]) {
                    $parts[] = __("matches.skipped_$k", ['n' => $n[$k]]);
                }
            }
            $back = $back->with($n['referred'] ? 'success' : 'error', implode(' ', $parts));
        }
        if ($warnings) {
            $back = $back->with('warning', implode(' ', array_slice($warnings, 0, 5)));
        }

        return $back;
    }

    public function move(Request $request, int $id): RedirectResponse
    {
        $m = $this->find($request, $id);
        $data = $request->validate([
            'to'   => ['required', Rule::in(M::STAGES)],
            'on'   => ['nullable', 'string', 'max:10'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['to.*' => __('matches.v.forward'), 'note.*' => __('matches.v.note')]);

        $m = $this->matcher->move($m, $data['to'], $request->user(), $data['on'] ?? null, $data['note'] ?? null);
        $back = back()->with('success', __('matches.moved', ['stage' => __("matches.stage.{$m->kind}.{$m->stage}")]));

        // All seats taken: warn, and offer "Close as Filled" (never blocked: an employer may take more).
        $o = Opportunity::query()->inWorkspace((int) $m->company_id)->find($m->opportunity_id);
        if ($o && $o->isOpen() && $m->takesSeat()) {
            $s = $this->matcher->seats($o);
            if ($s['full']) {
                $back = $back->with('warning', __('matches.seats_full', ['taken' => $s['taken'], 'seats' => $s['seats']]));
            }
        }

        return $back;
    }

    public function back(Request $request, int $id): RedirectResponse
    {
        $m = $this->find($request, $id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.*' => __('matches.v.reason')]);
        $m = $this->matcher->correct($m, $data['reason'], $request->user());

        return back()->with('success', __('matches.corrected', ['stage' => __("matches.stage.{$m->kind}.{$m->stage}")]));
    }

    public function stop(Request $request, int $id): RedirectResponse
    {
        $m = $this->find($request, $id);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:20'],
            'on'     => ['nullable', 'string', 'max:10'],
            'note'   => ['nullable', 'string', 'max:500'],
        ], ['reason.*' => __('matches.v.stop_reason'), 'note.*' => __('matches.v.note')]);
        $this->matcher->stop($m, $data['reason'], $request->user(), $data['on'] ?? null, $data['note'] ?? null);

        return back()->with('success', __('matches.stopped'));
    }

    public function restart(Request $request, int $id): RedirectResponse
    {
        $m = $this->find($request, $id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.*' => __('matches.v.reason')]);
        $m = $this->matcher->restart($m, $data['reason'], $request->user());

        return back()->with('success', __('matches.restarted', ['stage' => __("matches.stage.{$m->kind}.{$m->stage}")]));
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function companyId(Request $request): int
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($companyId, 404);

        return $companyId;
    }

    private function find(Request $request, int $id): M
    {
        return M::query()->inWorkspace($this->companyId($request))->whereKey($id)->firstOrFail();
    }
}
