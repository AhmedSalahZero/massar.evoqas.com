<?php

namespace App\Services\Eligibility;

use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\EligibilityAssessment as A;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — Assessor (Step 11 · Eligibility Assessment)
//  Location: app/Services/Eligibility/Assessor.php
//
//  The ONLY place results and decisions are written.
//
//    assess()      check one person against one job or training (new, or again)
//    refreshFor()  the profile was saved: work out its results again in
//                  every OPEN job or training it was checked against
//                  (called by Beneficiary::saved). A closed one
//                  keeps its results as they were.
//    decide()      the case worker's decision, with its required reason,
//                  written into the profile history as well
//    saveNotes()   the case worker's notes on one result
//
//  A decision is never changed by a new check. When the profile or the
//  opportunity's rules changed after it, the decision stays and is marked
//  "profile_changed" / "rules_changed", so someone looks again.
// ══════════════════════════════════════════════════════════════════

class Assessor
{
    public function __construct(private readonly Evaluator $evaluator) {}

    public function assess(Opportunity $opportunity, Beneficiary $b, ?string $byName = null, ?A $existing = null): A
    {
        abort_unless((int) $opportunity->company_id === (int) $b->company_id, 404);   // never across workspaces

        $e = $this->evaluator->evaluate($opportunity, $b);
        $a = $existing ?? A::query()->withoutGlobalScopes()
            ->where('opportunity_id', $opportunity->id)->where('beneficiary_id', $b->id)->first()
            ?? new A;

        if ($a->exists && $a->decision) {
            if ($a->inputs_hash !== $e['hash']) {
                $a->decision_flag = A::PROFILE_CHANGED;
            } elseif ((int) $a->rules_version !== (int) $opportunity->rules_version && $a->decision_flag !== A::PROFILE_CHANGED) {
                $a->decision_flag = A::RULES_CHANGED;
            }
        }

        $a->forceFill([
            'company_id'      => $opportunity->company_id,
            'opportunity_id'  => $opportunity->id,
            'beneficiary_id'  => $b->id,
            'score'           => $e['score'],
            'auto_result'     => $e['result'],
            'reasons'         => $e['reasons'],
            'inputs_hash'     => $e['hash'],
            'rules_version'   => $opportunity->rules_version,
            'checked_at'      => now(),
            'checked_by_name' => $byName !== null ? mb_substr($byName, 0, 100) : $a->checked_by_name,
        ]);
        $a->result = $a->decision ?: $a->auto_result;
        $a->save();

        return $a;
    }

    /** The profile was saved: its results in open jobs and trainings follow it. */
    public function refreshFor(Beneficiary $b): void
    {
        $hash = null;
        $rows = A::query()->withoutGlobalScopes()->where('company_id', $b->company_id)->where('beneficiary_id', $b->id)
            ->with(['opportunity' => fn ($q) => $q->withoutGlobalScopes()])->get();

        foreach ($rows as $a) {
            if (! $a->opportunity || ! $a->opportunity->isOpen()) {
                continue;
            }
            $hash ??= Evaluator::inputsHash($b);
            if ($a->inputs_hash === $hash) {
                continue;   // nothing the rules read has changed
            }
            $this->assess($a->opportunity, $b, $a->checked_by_name, $a);
        }
    }

    /**
     * @param  string  $decision  eligible | not_eligible | on_hold | auto (go back to the automatic result)
     */
    public function decide(A $a, string $decision, string $reason, User $by): A
    {
        return DB::transaction(function () use ($a, $decision, $reason, $by) {
            $before = $a->result;
            $new = $decision === 'auto' ? null : $decision;
            $a->forceFill([
                'decision'        => $new,
                'decision_reason' => mb_substr($reason, 0, 500),
                'decided_by'      => $by->id,
                'decided_by_name' => mb_substr($by->name, 0, 100),
                'decided_at'      => now(),
                'decision_flag'   => null,
            ]);
            $a->result = $new ?: $a->auto_result;
            $a->save();

            $opportunity = $a->opportunity()->withoutGlobalScopes()->first();
            BeneficiaryChange::query()->create([
                'company_id'     => $a->company_id,
                'beneficiary_id' => $a->beneficiary_id,
                'user_id'        => $by->id,
                'user_name'      => mb_substr($by->name, 0, 100),
                'action'         => 'eligibility',
                'changes'        => ['_eligibility' => [
                    'opportunity_id' => $opportunity?->id,
                    'kind'       => $opportunity?->kind,
                    'title'      => $opportunity?->title,
                    'from'       => $before,
                    'to'         => $a->result,
                    'decision'   => $decision,
                    'reason'     => mb_substr($reason, 0, 500),
                ]],
            ]);

            return $a;
        });
    }

    public function saveNotes(A $a, ?string $notes, User $by): A
    {
        $notes = trim((string) $notes);
        $a->forceFill([
            'notes'         => $notes === '' ? null : mb_substr($notes, 0, 2000),
            'notes_by_name' => mb_substr($by->name, 0, 100),
            'notes_at'      => now(),
        ])->save();

        return $a;
    }

    /** One result, as the screens show it. */
    public static function present(A $a, ?Opportunity $opportunity = null): array
    {
        $opportunity ??= $a->opportunity;

        return [
            'id'          => $a->id,
            'opportunity' => $opportunity ? $opportunity->brief() + [
                'eligible_from' => $opportunity->eligible_from, 'check_from' => $opportunity->check_from,
                'rules_version' => $opportunity->rules_version,
            ] : null,
            'score'       => $a->score,
            'auto_result' => $a->auto_result,
            'result'      => $a->result,
            'reasons'     => $a->reasons ?? [],
            'outdated'    => $opportunity && (int) $a->rules_version !== (int) $opportunity->rules_version,
            'checked_at'  => $a->checked_at?->toIso8601String(),
            'checked_by'  => $a->checked_by_name,
            'decision'    => $a->decision,
            'decision_reason' => $a->decision_reason,
            'decided_by'  => $a->decided_by_name,
            'decided_at'  => $a->decided_at?->toIso8601String(),
            'flag'        => $a->decision_flag,
            'notes'       => $a->notes,
            'notes_by'    => $a->notes_by_name,
            'notes_at'    => $a->notes_at?->toIso8601String(),
        ];
    }
}
