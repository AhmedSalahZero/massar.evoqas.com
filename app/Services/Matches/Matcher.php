<?php

namespace App\Services\Matches;

use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\EligibilityAssessment;
use App\Models\Opportunity;
use App\Models\OpportunityMatch as M;
use App\Models\OpportunityMatchEvent;
use App\Models\User;
use App\Services\Eligibility\Assessor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// ══════════════════════════════════════════════════════════════════
//  Massar — Matcher (Step 13 · Matches)
//  Location: app/Services/Matches/Matcher.php
//  Scope: docs/SCOPE_MATCHES.md
//
//  The ONLY place matches are written. Every change is a new line in the
//  match timeline AND in the person's profile history: nothing is ever
//  silently changed, corrections are new lines, never edits.
//
//    refer()     one person to one OPEN job or training. Only a person
//                whose result is Eligible (automatic, or decided by the
//                case worker). A person not checked yet is checked first.
//                One match per person and job: a stopped one is restarted.
//    move()      forward: the next stage, or a later one (the stages in
//                between are shown as skipped). Hired / Completed is final.
//    correct()   back ONE stage, with a reason (also undoes Hired / Completed)
//    stop()      at any stage before the last, with a reason from the list
//                (and words when the reason is "Other")
//    restart()   a stopped match, with a reason: back at the stage it was
//                stopped at. Like a referral, only while the job or
//                training is open and the person is Eligible.
//
//  Seats: a seat is taken from Accepted onwards (and not stopped).
//  Referring never takes one. seats() says how many are taken.
// ══════════════════════════════════════════════════════════════════

class Matcher
{
    public function __construct(private readonly Assessor $assessor) {}

    // ── Refer ────────────────────────────────────────────────────────

    /**
     * @return array{outcome: string, match: ?M, result: ?string, score: ?int}
     *   outcome: referred | already | stopped | not_eligible
     */
    public function refer(Opportunity $o, Beneficiary $b, User $by, ?string $on = null, ?string $note = null): array
    {
        abort_unless((int) $o->company_id === (int) $b->company_id, 404);     // never across workspaces
        if (! $o->isOpen()) {
            throw ValidationException::withMessages(['opportunity_id' => __('matches.v.closed')]);
        }
        $day = $this->day($on);

        $existing = M::query()->withoutGlobalScopes()->where('opportunity_id', $o->id)->where('beneficiary_id', $b->id)->first();
        if ($existing) {
            return ['outcome' => $existing->isActive() ? 'already' : 'stopped', 'match' => $existing, 'result' => null, 'score' => null];
        }

        // Only Eligible people. Someone not checked yet is checked first.
        $a = EligibilityAssessment::query()->withoutGlobalScopes()->where('opportunity_id', $o->id)->where('beneficiary_id', $b->id)->first()
            ?? $this->assessor->assess($o, $b->loadMissing('escoOccupation:id,code'), $by->name);
        if ($a->result !== EligibilityAssessment::ELIGIBLE) {
            return ['outcome' => 'not_eligible', 'match' => null, 'result' => $a->result, 'score' => $a->score];
        }

        $m = DB::transaction(function () use ($o, $b, $by, $day, $note) {
            $m = new M;
            $m->forceFill([
                'company_id'       => $o->company_id,
                'opportunity_id'   => $o->id,
                'beneficiary_id'   => $b->id,
                'kind'             => $o->kind,
                'stage'            => M::REFERRED,
                'status'           => M::ACTIVE,
                'referred_on'      => $day,
                'stage_on'         => $day,
                'changed_at'       => now(),
                'referred_by'      => $by->id,
                'referred_by_name' => mb_substr($by->name, 0, 100),
            ])->save();
            $this->event($m, $o, $by, 'referred', null, M::REFERRED, $day, $this->clean($note));

            return $m;
        });

        return ['outcome' => 'referred', 'match' => $m, 'result' => $a->result, 'score' => $a->score];
    }

    // ── Move forward ─────────────────────────────────────────────────

    public function move(M $m, string $to, User $by, ?string $on = null, ?string $note = null): M
    {
        if (! $m->isActive()) {
            throw ValidationException::withMessages(['to' => __('matches.v.stopped')]);
        }
        if ($m->isDone()) {
            throw ValidationException::withMessages(['to' => __('matches.v.final')]);
        }
        $from = M::rank($m->stage);
        $target = array_search($to, M::STAGES, true);
        if ($target === false || $target <= $from) {
            throw ValidationException::withMessages(['to' => __('matches.v.forward')]);
        }
        $day = $this->day($on, $m->stage_on);
        $skipped = array_slice(M::STAGES, $from + 1, $target - $from - 1);

        return DB::transaction(function () use ($m, $to, $by, $day, $note, $skipped) {
            $before = $m->stage;
            $m->forceFill(['stage' => $to, 'stage_on' => $day, 'changed_at' => now()])->save();
            $this->event($m, $this->opportunityOf($m), $by, 'moved', $before, $to, $day, $this->clean($note), $skipped);

            return $m;
        });
    }

    // ── Back one stage (a correction) ───────────────────────────────

    public function correct(M $m, string $reason, User $by): M
    {
        $reason = $this->clean($reason);
        if (! $m->isActive()) {
            throw ValidationException::withMessages(['reason' => __('matches.v.stopped')]);
        }
        if ($m->stage === M::REFERRED) {
            throw ValidationException::withMessages(['reason' => __('matches.v.first_stage')]);
        }
        if ($reason === null) {
            throw ValidationException::withMessages(['reason' => __('matches.v.reason')]);
        }
        $to = M::STAGES[M::rank($m->stage) - 1];

        return DB::transaction(function () use ($m, $to, $by, $reason) {
            $before = $m->stage;
            // The date the earlier stage had, as the timeline recorded it.
            $since = OpportunityMatchEvent::query()->withoutGlobalScopes()->where('match_id', $m->id)
                ->where('to_stage', $to)->whereIn('action', ['referred', 'moved'])->orderByDesc('id')->value('happened_on');
            $m->forceFill(['stage' => $to, 'stage_on' => $since ?? $m->referred_on, 'changed_at' => now()])->save();
            $this->event($m, $this->opportunityOf($m), $by, 'corrected', $before, $to, today(), $reason);

            return $m;
        });
    }

    // ── Stop ─────────────────────────────────────────────────────────

    public function stop(M $m, string $reason, User $by, ?string $on = null, ?string $note = null): M
    {
        $note = $this->clean($note);
        if (! $m->isActive()) {
            throw ValidationException::withMessages(['reason' => __('matches.v.stopped')]);
        }
        if ($m->isDone()) {
            throw ValidationException::withMessages(['reason' => __('matches.v.final')]);
        }
        if (! in_array($reason, M::STOP_REASONS[$m->kind] ?? [], true)) {
            throw ValidationException::withMessages(['reason' => __('matches.v.stop_reason')]);
        }
        if ($reason === 'other' && $note === null) {
            throw ValidationException::withMessages(['note' => __('matches.v.other')]);
        }
        $day = $this->day($on, $m->stage_on);

        return DB::transaction(function () use ($m, $reason, $by, $day, $note) {
            $m->forceFill([
                'status' => M::STOPPED, 'stop_reason' => $reason, 'stop_note' => $note,
                'stage_on' => $day, 'changed_at' => now(),
            ])->save();
            $this->event($m, $this->opportunityOf($m), $by, 'stopped', $m->stage, null, $day, $note, null, $reason);

            return $m;
        });
    }

    // ── Restart ──────────────────────────────────────────────────────

    public function restart(M $m, string $reason, User $by): M
    {
        $reason = $this->clean($reason);
        if ($m->isActive()) {
            throw ValidationException::withMessages(['reason' => __('matches.v.not_stopped')]);
        }
        if ($reason === null) {
            throw ValidationException::withMessages(['reason' => __('matches.v.reason')]);
        }
        $o = $this->opportunityOf($m);
        if (! $o || ! $o->isOpen()) {
            throw ValidationException::withMessages(['reason' => __('matches.v.closed')]);
        }
        $result = EligibilityAssessment::query()->withoutGlobalScopes()->where('opportunity_id', $m->opportunity_id)
            ->where('beneficiary_id', $m->beneficiary_id)->value('result');
        if ($result !== EligibilityAssessment::ELIGIBLE) {
            throw ValidationException::withMessages(['reason' => __('matches.v.restart_not_eligible', ['result' => __('assessments.result.'.($result ?: 'check'))])]);
        }

        return DB::transaction(function () use ($m, $o, $by, $reason) {
            $m->forceFill(['status' => M::ACTIVE, 'stop_reason' => null, 'stop_note' => null, 'stage_on' => today(), 'changed_at' => now()])->save();
            $this->event($m, $o, $by, 'restarted', null, $m->stage, today(), $reason);

            return $m;
        });
    }

    // ── Seats ────────────────────────────────────────────────────────

    /** @return array{taken: int, seats: int, full: bool} */
    public function seats(Opportunity $o): array
    {
        $taken = M::query()->withoutGlobalScopes()->where('opportunity_id', $o->id)->seated()->count();

        return ['taken' => $taken, 'seats' => (int) $o->seats, 'full' => $taken >= (int) $o->seats];
    }

    /** Seats taken for several opportunities at once. @return array<int, int> */
    public function seatsTaken(int $companyId, array $opportunityIds): array
    {
        if ($opportunityIds === []) {
            return [];
        }

        return M::query()->withoutGlobalScopes()->where('company_id', $companyId)->whereIn('opportunity_id', $opportunityIds)->seated()
            ->selectRaw('opportunity_id, count(*) as n')->groupBy('opportunity_id')->pluck('n', 'opportunity_id')
            ->map(fn ($n) => (int) $n)->all();
    }

    /**
     * Where else the person is already Hired, or In training (the reminder
     * shown before referring again). @return list<array{kind: string, stage: string, title: string}>
     */
    public function elsewhere(Beneficiary $b, ?int $exceptOpportunityId = null): array
    {
        return M::query()->withoutGlobalScopes()->where('opportunity_matches.company_id', $b->company_id)->where('beneficiary_id', $b->id)->active()
            ->when($exceptOpportunityId, fn ($q) => $q->where('opportunity_id', '!=', $exceptOpportunityId))
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('opportunity_matches.kind', Opportunity::JOB)->where('stage', M::DONE))
                ->orWhere(fn ($w) => $w->where('opportunity_matches.kind', Opportunity::TRAINING)->where('stage', M::IN_PROGRESS)))
            ->join('opportunities as o', 'o.id', '=', 'opportunity_matches.opportunity_id')
            ->orderByDesc('opportunity_matches.changed_at')
            ->get(['opportunity_matches.kind', 'opportunity_matches.stage', 'o.title'])
            ->map(fn ($r) => ['kind' => $r->kind, 'stage' => $r->stage, 'title' => $r->title])->all();
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** The date it happened: today, or an earlier date (never in the future, never before $notBefore). */
    private function day(?string $on, ?Carbon $notBefore = null): Carbon
    {
        if ($on === null || trim($on) === '') {
            $day = today();
        } else {
            try {
                $day = Carbon::createFromFormat('!Y-m-d', trim($on));
            } catch (\Throwable) {
                $day = null;
            }
            if (! $day || $day->format('Y-m-d') !== trim($on)) {
                throw ValidationException::withMessages(['on' => __('matches.v.date')]);
            }
        }
        if ($day->gt(today())) {
            throw ValidationException::withMessages(['on' => __('matches.v.future')]);
        }
        if ($notBefore && $day->lt($notBefore->copy()->startOfDay())) {
            throw ValidationException::withMessages(['on' => __('matches.v.before', ['date' => $notBefore->format('Y-m-d')])]);
        }
        if ($day->lt(today()->subYears(5))) {
            throw ValidationException::withMessages(['on' => __('matches.v.date')]);
        }

        return $day;
    }

    private function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, 500);
    }

    private function opportunityOf(M $m): ?Opportunity
    {
        return $m->relationLoaded('opportunity') && $m->opportunity
            ? $m->opportunity
            : Opportunity::query()->withoutGlobalScopes()->find($m->opportunity_id);
    }

    /** One line in the match timeline, and the same in the person's profile history. */
    private function event(M $m, ?Opportunity $o, User $by, string $action, ?string $from, ?string $to, Carbon $day, ?string $note, ?array $skipped = null, ?string $reason = null): void
    {
        OpportunityMatchEvent::query()->create([
            'company_id'  => $m->company_id,
            'match_id'    => $m->id,
            'action'      => $action,
            'from_stage'  => $from,
            'to_stage'    => $to,
            'skipped'     => $skipped ?: null,
            'reason'      => $reason,
            'note'        => $note,
            'happened_on' => $day->toDateString(),
            'user_id'     => $by->id,
            'user_name'   => mb_substr($by->name, 0, 100),
            'created_at'  => now(),
        ]);

        BeneficiaryChange::query()->create([
            'company_id'     => $m->company_id,
            'beneficiary_id' => $m->beneficiary_id,
            'user_id'        => $by->id,
            'user_name'      => mb_substr($by->name, 0, 100),
            'action'         => 'match',
            'changes'        => ['_match' => array_filter([
                'opportunity_id' => $m->opportunity_id,
                'kind'    => $m->kind,
                'title'   => $o?->title,
                'action'  => $action,
                'from'    => $from,
                'to'      => $to,
                'skipped' => $skipped ?: null,
                'reason'  => $reason,
                'note'    => $note,
                'on'      => $day->toDateString(),
            ], fn ($v) => $v !== null)],
        ]);
    }
}
