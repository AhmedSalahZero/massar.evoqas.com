<?php

namespace App\Services\Matches;

use App\Models\Backbone\EscoOccupation;
use App\Models\Beneficiary;
use App\Models\EligibilityAssessment;
use App\Models\Opportunity;
use App\Models\OpportunityMatch as M;
use App\Models\OpportunityMatchEvent;
use App\Services\Beneficiaries\OccupationDisplay;
use Illuminate\Database\Eloquent\Builder;

// ══════════════════════════════════════════════════════════════════
//  Massar — MatchBook (Step 13 · Matches)
//  Location: app/Services/Matches/MatchBook.php
//
//  What the screens show about matches (the writing is in Matcher):
//
//    query()        the workspace's matches, each with the person's
//                   CURRENT eligibility result for that job or training
//                   (elig_result), so a match can say "no longer eligible"
//    filter()       stage, kind, one job or training, the person's
//                   governorate and occupation (any level), a search by
//                   name / mobile / number, "Needs follow-up"
//    stageCounts()  Referred · Accepted · In progress · Done · Stopped,
//                   and how many need a follow-up
//    present()      one match for a screen (with the person and / or the
//                   job or training), and its timeline when asked for
// ══════════════════════════════════════════════════════════════════

class MatchBook
{
    public const FILTER_STAGES = [M::REFERRED, M::ACCEPTED, M::IN_PROGRESS, M::DONE, 'stopped'];

    public function query(int $companyId): Builder
    {
        return M::query()->inWorkspace($companyId)
            ->leftJoin('eligibility_assessments as ea', fn ($j) => $j->on('ea.opportunity_id', '=', 'opportunity_matches.opportunity_id')
                ->on('ea.beneficiary_id', '=', 'opportunity_matches.beneficiary_id'))
            ->select('opportunity_matches.*', 'ea.result as elig_result');
    }

    /** @param  array{stage?: string, kind?: string, opportunity?: int, governorate?: string, occ?: string, q?: string, follow?: bool}  $f */
    public function filter(Builder $query, array $f, bool $withStage = true): Builder
    {
        $stage = $f['stage'] ?? '';
        if ($withStage && $stage !== '') {
            $stage === 'stopped'
                ? $query->where('opportunity_matches.status', M::STOPPED)
                : $query->where('opportunity_matches.status', M::ACTIVE)->where('opportunity_matches.stage', $stage);
        }
        if (! empty($f['kind'])) {
            $query->where('opportunity_matches.kind', $f['kind']);
        }
        if (! empty($f['opportunity'])) {
            $query->where('opportunity_matches.opportunity_id', (int) $f['opportunity']);
        }
        if (! empty($f['follow'])) {
            $query->needsFollowUp();
        }

        $gov = $f['governorate'] ?? '';
        $occ = $f['occ'] ?? '';
        $tokens = \App\Support\TextNormalizer::tokens($f['q'] ?? '');
        if ($gov !== '' || $occ !== '' || $tokens !== []) {
            $query->whereExists(function ($q) use ($gov, $occ, $tokens) {
                $q->selectRaw('1')->from('beneficiaries as b')->whereColumn('b.id', 'opportunity_matches.beneficiary_id')
                    ->when($gov !== '', fn ($w) => $w->where('b.governorate', $gov));
                if (preg_match('/^(isco|enoc|esco):([\d.]{1,40})$/', $occ, $m)) {
                    if ($m[1] === 'esco') {
                        $ids = EscoOccupation::query()->where(fn ($w) => $w->where('code', $m[2])->orWhere('code', 'like', $m[2].'.%'))->pluck('id');
                        $q->whereIn('b.esco_occupation_id', $ids->all() ?: [0]);
                    } else {
                        $q->where('b.isco_code', 'like', substr(preg_replace('/\D/', '', $m[2]), 0, 4).'%');
                    }
                }
                foreach ($tokens as $token) {
                    ctype_digit($token) && strlen($token) < 4
                        ? $q->where('b.number', (int) $token)
                        : $q->where(fn ($w) => $w->where('b.search_text', 'like', '%'.$token.'%')
                            ->when(ctype_digit($token) && strlen($token) <= 7, fn ($x) => $x->orWhere('b.number', (int) $token)));
                }
            });
        }

        return $query;
    }

    /** @return array{referred: int, accepted: int, in_progress: int, done: int, stopped: int, total: int, follow: int} */
    public function stageCounts(Builder $base): array
    {
        $k = "CASE WHEN opportunity_matches.status = 'stopped' THEN 'stopped' ELSE opportunity_matches.stage END";
        $rows = (clone $base)->reorder()->select(\Illuminate\Support\Facades\DB::raw("$k as k"), \Illuminate\Support\Facades\DB::raw('count(*) as n'))
            ->groupBy(\Illuminate\Support\Facades\DB::raw($k))->pluck('n', 'k');
        $out = [];
        foreach (self::FILTER_STAGES as $s) {
            $out[$s] = (int) ($rows[$s] ?? 0);
        }
        $out['total'] = array_sum($out);
        $out['follow'] = (clone $base)->reorder()->needsFollowUp()->count();

        return $out;
    }

    /** One match, as the screens show it. */
    public function present(M $m, bool $person = true, bool $opportunity = true, bool $events = false): array
    {
        $result = $m->getAttribute('elig_result');
        $out = [
            'id'          => $m->id,
            'kind'        => $m->kind,
            'stage'       => $m->stage,
            'status'      => $m->status,
            'stop_reason' => $m->stop_reason,
            'stop_note'   => $m->stop_note,
            'referred_on' => $m->referred_on?->toDateString(),
            'stage_on'    => $m->stage_on?->toDateString(),
            'changed_at'  => $m->changed_at?->toIso8601String(),
            'referred_by' => $m->referred_by_name,
            'follow_up'   => $m->needsFollowUp(),
            'days'        => $m->changed_at ? (int) $m->changed_at->copy()->startOfDay()->diffInDays(today()) : 0,
            // The eligibility result now: when it is no longer Eligible, someone looks again.
            'result'      => $result,
            'no_longer_eligible' => $m->isActive() && $result !== null && $result !== EligibilityAssessment::ELIGIBLE,
        ];
        if ($person) {
            $b = $m->beneficiary;
            $out['person'] = $b ? [
                'number' => $b->number, 'name' => $b->displayName(), 'initials' => $b->initials(), 'age' => $b->age(),
                'governorate' => $b->governorate, 'phone' => $b->phone,
                'occupation' => OccupationDisplay::block($b->unit, $b->escoOccupation, $b->gender),
            ] : null;
        }
        if ($opportunity) {
            $o = $m->opportunity;
            $out['opportunity'] = $o ? $o->brief() + ['section' => $o->section(), 'employer' => $o->employer, 'provider' => $o->provider] : null;
        }
        if ($events) {
            $out['events'] = $this->events($m);
        }

        return $out;
    }

    /** The timeline, newest first. */
    public function events(M $m): array
    {
        return OpportunityMatchEvent::query()->withoutGlobalScopes()->where('company_id', $m->company_id)->where('match_id', $m->id)
            ->orderByDesc('id')->get()->map(fn (OpportunityMatchEvent $e) => $e->present())->values()->all();
    }

    /** The eager loads a list of matches needs. */
    public static function with(bool $person = true, bool $opportunity = true): array
    {
        return array_filter([
            $person ? 'beneficiary' : null,
            $person ? 'beneficiary.unit.enoc' : null,
            $person ? 'beneficiary.escoOccupation' : null,
            $opportunity ? 'opportunity' : null,
        ]);
    }

    /** The matches of these people in one job or training, keyed by person id (for the shortlist). @return array<int, M> */
    public function ofPeople(int $companyId, int $opportunityId, array $beneficiaryIds): array
    {
        if ($beneficiaryIds === []) {
            return [];
        }

        return M::query()->inWorkspace($companyId)->where('opportunity_id', $opportunityId)->whereIn('beneficiary_id', $beneficiaryIds)
            ->get()->keyBy('beneficiary_id')->all();
    }

    /** The short form a shortlist line or a suggestion shows. */
    public static function brief(?M $m): ?array
    {
        return $m ? ['id' => $m->id, 'stage' => $m->stage, 'status' => $m->status, 'kind' => $m->kind] : null;
    }

    /** Open jobs and trainings whose occupations fit this person (same, or the same 4-digit group). */
    public function suggested(Beneficiary $b, MatchFit $fit, int $limit = 8): array
    {
        if (! $b->isco_code) {
            return [];
        }
        $matched = M::query()->inWorkspace((int) $b->company_id)->where('beneficiary_id', $b->id)->pluck('opportunity_id')->all();
        $results = EligibilityAssessment::query()->inWorkspace((int) $b->company_id)->where('beneficiary_id', $b->id)
            ->get(['opportunity_id', 'result', 'score'])->keyBy('opportunity_id');

        $out = [];
        $open = Opportunity::query()->inWorkspace((int) $b->company_id)->open()
            ->where('occupation_keys', 'like', '%|isco:'.substr((string) $b->isco_code, 0, 1).'|%')   // a first, cheap cut
            ->whereNotIn('id', $matched ?: [0])->orderByDesc('id')->limit(300)->get();
        foreach ($open as $o) {
            $f = $fit->occupation($o, $b);
            if ($f['rank'] < MatchFit::LEVELS['unit']) {
                continue;
            }
            $a = $results[$o->id] ?? null;
            $out[] = $o->brief() + [
                'section'  => $o->section(),
                'employer' => $o->employer,
                'provider' => $o->provider,
                'deadline' => $o->deadline?->toDateString(),
                'fit'      => $f,
                'result'   => $a?->result,
                'score'    => $a?->score,
            ];
        }
        usort($out, fn ($x, $y) => [$y['fit']['rank'], $y['result'] === 'eligible', $y['id']] <=> [$x['fit']['rank'], $x['result'] === 'eligible', $x['id']]);

        return array_slice($out, 0, $limit);
    }
}
