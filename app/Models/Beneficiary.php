<?php

namespace App\Models;

use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════════
//  Massar — Beneficiary
//  Location: app/Models/Beneficiary.php
//
//  One person a partner works with (Scope v2 §3 Module B). Personal
//  data: it belongs to exactly one partner and is never visible to
//  another (Scope v2 §4).
//
//  How the separation is enforced (three layers, on purpose):
//    1. BelongsToCompany — every query is limited to the signed-in
//       person's partner, and new records get that partner's id.
//    2. BeneficiaryController looks every record up through
//       Beneficiary::inWorkspace($companyId) — an explicit filter
//       that does not depend on who is signed in.
//    3. The screens live under /app only, which the Super Admin
//       cannot open (Scope v2 §5: the platform sees aggregates, never
//       individual beneficiary records).
//
//  Never write to this model directly from a controller: use
//  App\Services\Beneficiaries\BeneficiaryRecorder, which also writes
//  the change history (who changed what, when).
// ══════════════════════════════════════════════════════════════════

class Beneficiary extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id', 'number'];

    protected $casts = [
        'date_of_birth'     => 'date',
        'education'         => 'array',
        'work_history'      => 'array',
        'skills'            => 'array',
        'languages'         => 'array',
        'experience_months' => 'integer',
        'expected_salary'   => 'integer',
        'occupation_set_at' => 'datetime',
    ];

    /** Records of one partner — the explicit filter every screen uses. */
    protected static function booted(): void
    {
        // The Searchable CV Bank index follows every change of the profile.
        static::saved(fn (Beneficiary $b) => app(\App\Services\Cv\CvBankIndex::class)->refresh($b));
        // Step 11/12: its eligibility results in open jobs and trainings follow the profile.
        static::saved(fn (Beneficiary $b) => app(\App\Services\Eligibility\Assessor::class)->refreshFor($b));
    }

    public function scopeInWorkspace(Builder $query, int $companyId): Builder
    {
        return $query->where($this->getTable().'.company_id', $companyId);
    }

    // ── Display ────────────────────────────────────────────────────

    /** The name to show first on a screen in this language, falling back to the other. */
    public function displayName(?string $locale = null): string
    {
        $ar = ($locale ?? app()->getLocale()) === 'ar';

        return ($ar ? ($this->name_ar ?: $this->name_en) : ($this->name_en ?: $this->name_ar)) ?? '';
    }

    public function initials(): string
    {
        $name = trim($this->name_en ?: $this->name_ar ?: '?');
        $parts = preg_split('/\s+/u', $name) ?: [$name];
        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    // ── Relations ──────────────────────────────────────────────────

    public function unit(): BelongsTo
    {
        return $this->belongsTo(IscoGroup::class, 'isco_group_id');
    }

    public function escoOccupation(): BelongsTo
    {
        return $this->belongsTo(EscoOccupation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function occupationSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'occupation_set_by');
    }

    /** Named history(), not changes(): Eloquent already uses $changes internally. */
    public function history(): HasMany
    {
        return $this->hasMany(BeneficiaryChange::class)->orderByDesc('id');
    }
}
