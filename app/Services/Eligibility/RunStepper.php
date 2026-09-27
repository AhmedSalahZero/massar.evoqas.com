<?php

namespace App\Services\Eligibility;

use App\Models\Beneficiary;
use App\Models\EligibilityAssessment;
use App\Models\Opportunity;
use App\Models\EligibilityRun;
use App\Models\User;
use App\Services\Cv\CvBankSearch;

// ══════════════════════════════════════════════════════════════════
//  Massar — RunStepper (Step 11 · "Check everyone")
//  Location: app/Services/Eligibility/RunStepper.php
//
//  start()  opens a run of one job or training over:
//             all       every person in the workspace
//             search    the people a CV Bank search found
//             outdated  the people whose result was made with older rules
//  step()   checks the next STEP people and says how far it is. The
//           browser calls it again until the run is done, like the bulk
//           CV upload, so no background worker is needed and thousands
//           of profiles never block the screen. Leaving the page stops
//           nothing that matters: opening the page again continues
//           the run where it was.
// ══════════════════════════════════════════════════════════════════

class RunStepper
{
    public const STEP = 200;

    public function __construct(
        private readonly Assessor $assessor,
        private readonly CvBankSearch $search,
    ) {}

    public function start(Opportunity $opportunity, string $scope, array $filters, User $by): EligibilityRun
    {
        $companyId = (int) $opportunity->company_id;
        $ids = null;
        $total = match ($scope) {
            'all'      => Beneficiary::query()->withoutGlobalScopes()->inWorkspace($companyId)->count(),
            'search'   => count($ids = $this->search->ids($companyId, $filters)),
            'outdated' => $this->outdatedQuery($opportunity)->count(),
        };

        // One run at a time per opportunity: an older one still open is stopped.
        EligibilityRun::query()->withoutGlobalScopes()->where('opportunity_id', $opportunity->id)->where('status', 'running')
            ->update(['status' => 'done', 'finished_at' => now()]);

        $run = new EligibilityRun;
        $run->forceFill([
            'company_id'      => $companyId,
            'opportunity_id'  => $opportunity->id,
            'scope'           => $scope,
            'filters'         => $scope === 'search' ? $filters : null,
            'ids'             => $ids !== null ? json_encode($ids) : null,
            'total'           => $total,
            'done'            => 0,
            'cursor'          => 0,
            'counts'          => ['eligible' => 0, 'check' => 0, 'not_eligible' => 0, 'on_hold' => 0],
            'status'          => $total > 0 ? 'running' : 'done',
            'started_by'      => $by->id,
            'started_by_name' => mb_substr($by->name, 0, 100),
            'finished_at'     => $total > 0 ? null : now(),
        ])->save();

        return $run;
    }

    /** @param  int  $at  how many the browser saw done — a resent step does nothing twice */
    public function step(EligibilityRun $run, int $at, ?string $byName): EligibilityRun
    {
        if ($run->status !== 'running' || $at !== $run->done) {
            return $run;
        }
        $opportunity = Opportunity::query()->withoutGlobalScopes()->findOrFail($run->opportunity_id);
        if (! $opportunity->isOpen()) {
            $run->forceFill(['status' => 'done', 'finished_at' => now()])->save();

            return $run;
        }

        [$people, $advanced] = $this->next($run, $opportunity);
        $existing = EligibilityAssessment::query()->withoutGlobalScopes()->where('opportunity_id', $opportunity->id)
            ->whereIn('beneficiary_id', $people->pluck('id'))->get()->keyBy('beneficiary_id');

        $counts = $run->counts ?? [];
        foreach ($people as $b) {
            $a = $this->assessor->assess($opportunity, $b, $byName, $existing[$b->id] ?? null);
            $counts[$a->result] = ($counts[$a->result] ?? 0) + 1;
        }

        $done = $run->done + $advanced;
        $finished = $run->scope === 'search' ? $done >= $run->total : $advanced < self::STEP;
        $run->forceFill([
            'done'        => $done,
            'total'       => max($run->total, $done),
            'cursor'      => $people->last()?->id ?? $run->cursor,
            'counts'      => $counts,
            'status'      => $finished ? 'done' : 'running',
            'finished_at' => $finished ? now() : null,
        ])->save();
        if ($finished && $run->total !== $done) {
            $run->forceFill(['total' => $done])->save();   // people were added or removed during the run
        }

        return $run;
    }

    /** The results made with older rules. */
    public function outdatedQuery(Opportunity $opportunity)
    {
        return EligibilityAssessment::query()->withoutGlobalScopes()->where('opportunity_id', $opportunity->id)
            ->where('rules_version', '<', $opportunity->rules_version);
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: int} the people, and how far the run moves */
    private function next(EligibilityRun $run, Opportunity $opportunity): array
    {
        $base = Beneficiary::query()->withoutGlobalScopes()->inWorkspace((int) $run->company_id)->with('escoOccupation:id,code');

        if ($run->scope === 'search') {
            // The people found when the run started; someone deleted since is simply skipped.
            $slice = array_slice(json_decode((string) $run->ids, true) ?: [], $run->done, self::STEP);

            return [$slice ? $base->whereIn('beneficiaries.id', $slice)->orderBy('beneficiaries.id')->get() : collect(), count($slice)];
        }
        if ($run->scope === 'outdated') {
            $base->whereIn('beneficiaries.id', $this->outdatedQuery($opportunity)->select('beneficiary_id'));
        }
        $people = $base->where('beneficiaries.id', '>', (int) $run->cursor)->orderBy('beneficiaries.id')->limit(self::STEP)->get();

        return [$people, $people->count()];
    }
}
