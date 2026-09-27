<?php

namespace App\Services\Beneficiaries;

use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\Company;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// ══════════════════════════════════════════════════════════════════
//  Massar — BeneficiaryRecorder
//  Location: app/Services/Beneficiaries/BeneficiaryRecorder.php
//
//  The ONLY way a beneficiary profile is registered or changed. Each
//  save is one database transaction that:
//    1. writes the profile (with the partner's company_id — never
//       taken from the form),
//    2. gives a new person the partner's next running number,
//    3. links the occupation at the most detailed level (ESCO) and
//       records how, by whom and when it was chosen,
//    4. works out years of experience from the work history
//       (overlapping jobs counted once),
//    5. refreshes the search text (names, mobile, email — normalised
//       like occupation search, so "أحمد" finds "احمد"),
//    6. writes the history row: who, when, and what changed.
//  If any step fails, nothing is saved — there is never a change
//  without its history, or a history row without its change.
//
//  duplicates() finds people in the SAME workspace with the same
//  mobile or email (Scope v2 §3: checks never cross workspaces).
//
//  $method says how the occupation was chosen, and is shown on the
//  profile: 'manual' (picked on the form), 'cv_exact' (the CV reading
//  engine found one exact match), 'cv_review' (a reviewer chose it on
//  the review screen), 'self' (the job seeker chose it on the public
//  site, Step 10).
// ══════════════════════════════════════════════════════════════════

class BeneficiaryRecorder
{
    /** Profile fields written from the form, in the order the history lists them. */
    public const FIELDS = [
        'name_ar', 'name_en', 'gender', 'date_of_birth', 'military_status', 'governorate', 'city',
        'phone', 'email', 'education_level', 'education', 'work_history', 'skills', 'languages',
        'expected_salary', 'job_type',
    ];

    /** Lists: the history says "changed" without copying them. */
    private const LIST_FIELDS = ['education', 'work_history', 'skills', 'languages'];

    public const METHODS = ['manual', 'cv_exact', 'cv_review', 'cv_rule', 'self'];

    /** How a person joined the workspace (Step 15, the dashboard): see beneficiaries.source. */
    public const SOURCES = ['manual', 'intake', 'cv_upload', 'pool', 'self'];

    /**
     * @param  string  $source  how the person joined: manual (the form) | intake (Guided Intake)
     *                          | cv_upload (Upload CVs) | pool (added from the Public Talent Pool)
     *                          | self (the job seeker's own profile in the public pool)
     */
    public function create(Company $company, array $data, User $by, string $method = 'manual', string $source = 'manual'): Beneficiary
    {
        return DB::transaction(function () use ($company, $data, $by, $method, $source) {
            // One registration at a time per partner, so two case workers
            // saving at the same moment never get the same number.
            Company::query()->withoutGlobalScopes()->whereKey($company->id)->lockForUpdate()->first();
            $number = (int) Beneficiary::query()->withoutGlobalScopes()->inWorkspace($company->id)->max('number') + 1;

            $b = new Beneficiary;
            $b->forceFill(['company_id' => $company->id, 'number' => $number, 'source' => in_array($source, self::SOURCES, true) ? $source : 'manual']);
            $this->fill($b, $data, $by, $method);
            $b->created_by = $by->id;
            $b->updated_by = $by->id;
            $b->save();

            $this->log($b, BeneficiaryChange::CREATED, null, $by);
            $this->learnEmployers($b, $by);
            \App\Models\SectorProposal::count([], $b->work_history ?? []);

            return $b;
        });
    }

    /**
     * @param  string  $method  how a NEW occupation was chosen (see METHODS)
     * @param  ?string  $fromCv  the CV file the changes came from ("Update from this CV"),
     *                           shown in the history as "updated from CV …"
     */
    public function update(Beneficiary $b, array $data, User $by, string $method = 'manual', ?string $fromCv = null): Beneficiary
    {
        return DB::transaction(function () use ($b, $data, $by, $method, $fromCv) {
            $before = $this->snapshot($b);
            $oldJobs = $b->work_history ?? [];
            $this->fill($b, $data, $by, $method);
            $changes = $this->diff($before, $this->snapshot($b));

            if ($changes === []) {
                return $b;   // nothing changed — no save, no history row
            }
            if ($fromCv !== null) {
                $changes['_from_cv'] = mb_substr($fromCv, 0, 150);
            }

            $b->updated_by = $by->id;
            $b->save();
            $this->log($b, BeneficiaryChange::UPDATED, $changes, $by);
            $this->learnEmployers($b, $by);
            \App\Models\SectorProposal::count($oldJobs, $b->work_history ?? []);

            return $b;
        });
    }

    /** People in this workspace with the same mobile or email (never another partner's). */
    public function duplicates(int $companyId, ?string $phone, ?string $email, ?int $exceptId = null): Collection
    {
        if (! $phone && ! $email) {
            return collect();
        }

        return Beneficiary::query()->withoutGlobalScopes()->inWorkspace($companyId)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where(fn ($q) => $q
                ->when($phone, fn ($q) => $q->orWhere('phone', $phone))
                ->when($email, fn ($q) => $q->orWhere('email', $email)))
            ->orderBy('number')->limit(5)
            ->get(['id', 'number', 'name_ar', 'name_en', 'phone', 'email']);
    }

    // ── Filling ──────────────────────────────────────────────────────

    private function fill(Beneficiary $b, array $data, User $by, string $method = 'manual'): void
    {
        foreach (self::FIELDS as $field) {
            $b->{$field} = $data[$field] ?? null;
        }
        foreach (self::LIST_FIELDS as $field) {
            $b->{$field} = array_values($data[$field] ?? []);
        }

        $this->fillOccupation($b, $data, $by, $method);

        $b->experience_months = self::experienceMonths($b->work_history ?? []);
        $b->industry = self::industryOf($b->work_history ?? []);
        $b->search_text = trim(implode(' ', array_filter([
            TextNormalizer::normalize($b->name_ar),
            TextNormalizer::normalize($b->name_en),
            TextNormalizer::normalize($b->email),
            $b->phone,
        ])));
    }

    private function fillOccupation(Beneficiary $b, array $data, User $by, string $method = 'manual'): void
    {
        $method = in_array($method, self::METHODS, true) ? $method : 'manual';
        [$unitId, $escoId, $code] = $this->resolveOccupation($data);

        if ((int) $b->isco_group_id === (int) $unitId && (int) $b->esco_occupation_id === (int) $escoId) {
            return;   // same occupation — keep who chose it and when
        }

        $b->forceFill([
            'isco_group_id'      => $unitId,
            'esco_occupation_id' => $escoId,
            'isco_code'          => $code,
            'occupation_method'  => $unitId ? $method : null,
            'occupation_set_by'  => $unitId ? $by->id : null,
            'occupation_set_at'  => $unitId ? now() : null,
        ]);
    }

    /** @return array{0: ?int, 1: ?int, 2: ?string} unit id, ESCO id, 4-digit code */
    private function resolveOccupation(array $data): array
    {
        if (! empty($data['esco_occupation_id'])) {
            $esco = EscoOccupation::query()->where('is_active', true)->findOrFail($data['esco_occupation_id']);

            return [$esco->isco_group_id, $esco->id, $esco->isco_code];
        }

        if (! empty($data['occupation_unit'])) {
            $unit = IscoGroup::query()->units()->where('code', $data['occupation_unit'])->firstOrFail();

            // Stored at the most detailed level: a group that ESCO details
            // must be chosen as one of its detailed jobs.
            if (EscoOccupation::query()->where('isco_group_id', $unit->id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['occupation' => __('beneficiaries.v.occupation_detail')]);
            }

            return [$unit->id, null, $unit->code];
        }

        return [null, null, null];
    }

    /**
     * The sub-sector (I01, T02, S05 …) of the person's CURRENT job, or of the
     * most recent one when none is current (Step 14: "Industry" in Reports).
     * Null when no job has a sector.
     */
    public static function industryOf(array $jobs): ?string
    {
        $best = null;
        $bestKey = '';
        foreach ($jobs as $j) {
            $code = is_array($j) ? ($j['sub_sector'] ?? null) : null;
            if (! is_string($code) || ! preg_match('/^[ITS]\d{2}$/', $code)) {
                continue;
            }
            // Current jobs first, then the latest end, then the latest start.
            $key = (! empty($j['current']) ? '1' : '0').'|'.(! empty($j['current']) ? '9999-99' : (string) ($j['to'] ?? '')).'|'.(string) ($j['from'] ?? '');
            if ($best === null || strcmp($key, $bestKey) > 0) {
                [$best, $bestKey] = [$code, $key];
            }
        }

        return $best;
    }

    /**
     * Whole months of experience from the work history. Overlapping jobs
     * are counted once; a current job counts up to this month.
     */
    public static function experienceMonths(array $jobs): int
    {
        $ranges = [];
        foreach ($jobs as $job) {
            if (empty($job['from']) || ! preg_match('/^\d{4}-\d{2}$/', $job['from'])) {
                continue;
            }
            $from = Carbon::createFromFormat('!Y-m', $job['from']);
            $to = ! empty($job['to']) && preg_match('/^\d{4}-\d{2}$/', $job['to'])
                ? Carbon::createFromFormat('!Y-m', $job['to'])
                : Carbon::now()->startOfMonth();
            if ($to->lt($from)) {
                continue;
            }
            // Month index, end inclusive: Jan–Jan is one month.
            $ranges[] = [$from->year * 12 + $from->month, $to->year * 12 + $to->month + 1];
        }

        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
        $total = 0;
        $end = PHP_INT_MIN;
        foreach ($ranges as [$s, $e]) {
            if ($s >= $end) {
                $total += $e - $s;
                $end = $e;
            } elseif ($e > $end) {
                $total += $e - $end;
                $end = $e;
            }
        }

        return min($total, 65535);
    }

    /**
     * Step 10.5: a job at a company not in the list, with a sector chosen by
     * STAFF, is remembered for this workspace (never from the public site).
     */
    private function learnEmployers(Beneficiary $b, User $by): void
    {
        if ($by->id && $b->company_id !== \App\Models\Company::poolId()) {
            app(\App\Services\Employers\EmployerBook::class)->learn((int) $b->company_id, $b->work_history ?? [], $by->id);
        }
    }

    // ── History ──────────────────────────────────────────────────────

    private function snapshot(Beneficiary $b): array
    {
        $out = [];
        foreach (self::FIELDS as $field) {
            $v = $b->{$field};
            $out[$field] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
        }
        // Jobs saved before responsibilities (or the location) existed have no such key: the same as none.
        // One order of keys, so the history does not see a change where there is none.
        $out['work_history'] = array_map(
            fn ($job) => is_array($job) ? [
                'title' => $job['title'] ?? null, 'employer' => $job['employer'] ?? null, 'location' => $job['location'] ?? null,
                'country' => $job['country'] ?? null, 'sub_sector' => $job['sub_sector'] ?? null, 'employer_id' => $job['employer_id'] ?? null,
                'governorate' => $job['governorate'] ?? null, 'sector' => $job['sector'] ?? null,
                'sub_sector_other' => $job['sub_sector_other'] ?? null, 'sector_unknown' => (bool) ($job['sector_unknown'] ?? false),
                'from' => $job['from'] ?? null, 'to' => $job['to'] ?? null, 'current' => (bool) ($job['current'] ?? false),
                'responsibilities' => $job['responsibilities'] ?? [],
            ] : $job,
            is_array($out['work_history'] ?? null) ? $out['work_history'] : [],
        );
        $out['occupation'] = $b->esco_occupation_id
            ? EscoOccupation::query()->whereKey($b->esco_occupation_id)->value('code')
            : $b->isco_code;

        return $out;
    }

    private function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $field => $new) {
            $old = $before[$field] ?? null;
            if (in_array($field, self::LIST_FIELDS, true)) {
                if (json_encode($old ?? []) !== json_encode($new ?? [])) {
                    $changes[$field] = ['changed' => true];
                }
            } elseif ((string) $old !== (string) $new) {
                $changes[$field] = ['from' => $old, 'to' => $new];
            }
        }

        return $changes;
    }

    private function log(Beneficiary $b, string $action, ?array $changes, User $by): void
    {
        BeneficiaryChange::query()->create([
            'company_id'     => $b->company_id,
            'beneficiary_id' => $b->id,
            'user_id'        => $by->id,
            'user_name'      => mb_substr($by->name, 0, 100),
            'action'         => $action,
            'changes'        => $changes,
        ]);
    }
}
