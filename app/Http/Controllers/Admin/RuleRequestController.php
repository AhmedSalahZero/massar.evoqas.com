<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearnedRule;
use App\Services\Cv\LearnedRuleBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — Admin\RuleRequestController (Scope v2 §3 Rule promotion, layer 2)
//  Location: app/Http/Controllers/Admin/RuleRequestController.php
//  Permission: platform.rules (Super Admin)
//
//  GET    /admin/rule-requests                   index    proposals waiting / decided, and the Massar rules
//  POST   /admin/rule-requests/{rule}/promote    promote  the rule becomes a Massar rule, for every partner
//  POST   /admin/rule-requests/{rule}/decline    decline  with an optional note the partner sees
//  DELETE /admin/massar-rules/{rule}             destroy  remove a Massar rule
//
//  Only the words and their meaning travel to Massar — a proposal
//  carries no CV and no beneficiary data. Promoting words Massar
//  already has changes that Massar rule to the new meaning (the
//  screen shows the current meaning first).
// ══════════════════════════════════════════════════════════════════

class RuleRequestController extends Controller
{
    public function __construct(private readonly LearnedRuleBook $book) {}

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), ['pending', 'decided', 'massar'], true) ? $request->query('tab') : 'pending';

        $query = match ($tab) {
            'pending' => LearnedRule::query()->whereNotNull('company_id')->where('proposal_status', LearnedRule::PENDING)->orderBy('proposed_at'),
            'decided' => LearnedRule::query()->whereNotNull('company_id')->whereIn('proposal_status', [LearnedRule::PROMOTED, LearnedRule::DECLINED])->orderByDesc('decided_at'),
            default   => LearnedRule::query()->massar()->orderByDesc('id'),
        };
        $list = $query->with('company:id,name')->paginate(25)->withQueryString()
            ->through(function (LearnedRule $r) use ($tab) {
                $row = $this->book->present($r, true);
                if ($tab === 'pending' && ($twin = $this->book->massarTwin($r))) {
                    $row['massar_now'] = $this->book->present($twin);
                }

                return $row;
            });

        return Inertia::render('Admin/RuleRequests/Index', [
            'list'   => $list,
            'tab'    => $tab,
            'counts' => [
                'pending' => LearnedRule::query()->whereNotNull('company_id')->where('proposal_status', LearnedRule::PENDING)->count(),
                'massar'  => LearnedRule::query()->massar()->count(),
            ],
        ]);
    }

    public function promote(Request $request, int $rule): RedirectResponse
    {
        $note = $this->note($request);
        $admin = $request->user();

        DB::transaction(function () use ($rule, $note, $admin) {
            $r = LearnedRule::query()->whereNotNull('company_id')->where('proposal_status', LearnedRule::PENDING)->lockForUpdate()->findOrFail($rule);
            $meaning = $r->only(['phrase', 'section', 'esco_occupation_id', 'occupation_unit', 'skill_name']);
            $twin = $this->book->massarTwin($r);
            if ($twin) {
                $twin->fill($meaning + ['promoted_from_id' => $r->id, 'promoted_from_company' => $r->company?->name])->save();
            } else {
                LearnedRule::query()->create($meaning + [
                    'company_id' => null, 'kind' => $r->kind, 'normalized' => $r->normalized, 'source' => 'promoted',
                    'created_by' => $admin->id, 'created_by_name' => mb_substr($admin->name, 0, 100),
                    'promoted_from_id' => $r->id, 'promoted_from_company' => $r->company?->name,
                ]);
            }
            $r->forceFill(['proposal_status' => LearnedRule::PROMOTED, 'decided_by_name' => mb_substr($admin->name, 0, 100),
                'decided_at' => now(), 'decision_note' => $note])->save();
        });

        return back()->with('success', __('rules.promoted'));
    }

    public function decline(Request $request, int $rule): RedirectResponse
    {
        $note = $this->note($request);
        $r = LearnedRule::query()->whereNotNull('company_id')->where('proposal_status', LearnedRule::PENDING)->findOrFail($rule);
        $r->forceFill(['proposal_status' => LearnedRule::DECLINED, 'decided_by_name' => mb_substr($request->user()->name, 0, 100),
            'decided_at' => now(), 'decision_note' => $note])->save();

        return back()->with('success', __('rules.declined'));
    }

    public function destroy(int $rule): RedirectResponse
    {
        LearnedRule::query()->massar()->whereKey($rule)->firstOrFail()->delete();

        return back()->with('success', __('rules.deleted'));
    }

    private function note(Request $request): ?string
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);

        return trim((string) ($data['note'] ?? '')) ?: null;
    }
}
