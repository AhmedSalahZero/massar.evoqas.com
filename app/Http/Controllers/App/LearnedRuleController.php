<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\SaveLearnedRuleRequest;
use App\Models\LearnedRule;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Cv\CvIntake;
use App\Services\Cv\LearnedRuleBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\LearnedRuleController (Scope v2 §3 Learned Rules, layer 1)
//  Location: app/Http/Controllers/App/LearnedRuleController.php
//
//  GET    /app/rules                   index     this workspace's rules, or the Massar rules   rules.view
//  POST   /app/rules                   store     teach a rule by hand                          rules.manage
//  PATCH  /app/rules/{rule}            update    change a rule                                 rules.manage
//  DELETE /app/rules/{rule}            destroy   delete a rule                                 rules.manage
//  POST   /app/rules/{rule}/propose    propose   ask Massar to use it for every partner        rules.propose
//  POST   /app/rules/{rule}/withdraw   withdraw  take back a proposal still waiting            rules.propose
//
//  A rule is looked up in the signed-in person's workspace only; the
//  Massar rules are read-only here. Adding, changing or deleting a rule
//  reads the waiting CVs with those words again at once (as teaching on
//  the review screen does).
// ══════════════════════════════════════════════════════════════════

class LearnedRuleController extends Controller
{
    public function __construct(
        private readonly LearnedRuleBook $book,
        private readonly OccupationCatalog $catalog,
        private readonly CvIntake $intake,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;
        $tab = $request->query('tab') === 'massar' ? 'massar' : 'ours';
        $kind = in_array($request->query('kind'), LearnedRule::KINDS, true) ? $request->query('kind') : '';
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $base = fn () => $tab === 'massar' ? LearnedRule::query()->massar() : LearnedRule::query()->inWorkspace($companyId);
        $list = $base()
            ->when($kind, fn ($w) => $w->where('kind', $kind))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('phrase', 'like', '%'.$q.'%')->orWhere('skill_name', 'like', '%'.$q.'%')))
            ->orderByDesc('id')
            ->paginate(25)->withQueryString()
            ->through(fn (LearnedRule $r) => $this->book->present($r));

        return Inertia::render('App/Rules/Index', [
            'list'     => $list,
            'tab'      => $tab,
            'kind'     => $kind,
            'q'        => $q,
            'counts'   => [
                'ours'   => LearnedRule::query()->inWorkspace($companyId)->count(),
                'massar' => LearnedRule::query()->massar()->count(),
            ],
            'sections' => LearnedRule::SECTIONS,
            'backbone' => $this->catalog->loaded(),
        ]);
    }

    public function store(SaveLearnedRuleRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->book->teach((int) $user->company_id, $user, $request->input('kind'), $request->input('phrase'), $request->target(), 'page');

        return back()->with('success', $this->readAgain((int) $user->company_id, [$request->input('phrase')], 'rules.saved'));
    }

    public function update(SaveLearnedRuleRequest $request, int $rule): RedirectResponse
    {
        $r = $this->find($request, $rule);
        $key = $this->book->key($request->input('kind'), $request->input('phrase'));
        $clash = LearnedRule::query()->inWorkspace($r->company_id)->where('kind', $request->input('kind'))
            ->where('normalized', $key)->whereKeyNot($r->id)->exists();
        if ($clash) {
            throw ValidationException::withMessages(['phrase' => __('rules.v.exists')]);
        }
        $before = clone $r;
        $t = $request->target();
        $kind = $request->input('kind');
        $r->fill([
            'kind'               => $kind,
            'phrase'             => mb_substr($request->input('phrase'), 0, 120),
            'normalized'         => $key,
            'section'            => $kind === 'heading' ? $t['section'] : null,
            'esco_occupation_id' => $kind === 'title' ? $t['esco_occupation_id'] : null,
            'occupation_unit'    => $kind === 'title' && ! $t['esco_occupation_id'] ? $t['occupation_unit'] : null,
            'skill_name'         => $kind === 'skill' ? $t['skill_name'] : null,
        ]);
        // A proposal waiting at Massar was about the old rule.
        if ($r->proposal_status === LearnedRule::PENDING && ($r->isDirty('normalized') || ! LearnedRuleBook::sameMeaning($r, $before))) {
            $r->proposal_status = null;
        }
        $r->save();

        return back()->with('success', $this->readAgain((int) $r->company_id, [$before->phrase, $r->phrase], 'rules.saved'));
    }

    public function destroy(Request $request, int $rule): RedirectResponse
    {
        $r = $this->find($request, $rule);
        $r->delete();

        return back()->with('success', $this->readAgain((int) $r->company_id, [$r->phrase], 'rules.deleted'));
    }

    public function propose(Request $request, int $rule): RedirectResponse
    {
        $r = $this->find($request, $rule);
        if ($r->proposal_status === LearnedRule::PENDING) {
            return back()->with('success', __('rules.proposed'));
        }
        $twin = $this->book->massarTwin($r);
        if ($twin && LearnedRuleBook::sameMeaning($twin, $r)) {
            throw ValidationException::withMessages(['rule' => __('rules.v.already_massar')]);
        }
        $r->forceFill([
            'proposal_status' => LearnedRule::PENDING, 'proposed_by_name' => mb_substr($request->user()->name, 0, 100), 'proposed_at' => now(),
            'decided_by_name' => null, 'decided_at' => null, 'decision_note' => null,
        ])->save();

        return back()->with('success', __('rules.proposed'));
    }

    public function withdraw(Request $request, int $rule): RedirectResponse
    {
        $r = $this->find($request, $rule);
        if ($r->proposal_status === LearnedRule::PENDING) {
            $r->forceFill(['proposal_status' => null, 'proposed_at' => null, 'proposed_by_name' => null])->save();
        }

        return back()->with('success', __('rules.withdrawn'));
    }

    /** Read the waiting CVs with these words again; the message says how many. */
    private function readAgain(int $companyId, array $phrases, string $message): string
    {
        $n = 0;
        foreach (array_unique(array_filter($phrases)) as $phrase) {
            $n += $this->intake->rereadContaining($companyId, $phrase, 0);
        }

        return $n ? __($message).' '.__('rules.reread', ['n' => $n]) : __($message);
    }

    private function find(Request $request, int $id): LearnedRule
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($companyId, 404);

        return LearnedRule::query()->inWorkspace($companyId)->whereKey($id)->firstOrFail();
    }
}
