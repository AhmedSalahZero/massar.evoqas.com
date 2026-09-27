<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════════
//  Massar — Opportunity (Step 12 · Jobs & Training)
//  Location: app/Models/Opportunity.php
//  Scope: docs/SCOPE_JOBS_AND_TRAINING.md
//
//  A Job or a Training Program of ONE workspace (BelongsToCompany, plus
//  the explicit inWorkspace() filter every screen uses). It holds its
//  details AND its eligibility: the rules, the two result levels and
//  the rules version (Step 11's engine, see App\Services\Eligibility).
//  Eligibility is never created on its own: every result belongs to a
//  job or a training.
// ══════════════════════════════════════════════════════════════════

class Opportunity extends Model
{
    use BelongsToCompany;

    public const JOB = 'job';
    public const TRAINING = 'training';
    public const KINDS = [self::JOB, self::TRAINING];

    public const OPEN = 'open';
    public const CLOSED = 'closed';

    public const CLOSE_REASONS = ['filled', 'cancelled', 'finished'];
    public const JOB_TYPES = ['full_time', 'part_time', 'temporary'];
    public const DURATION_UNITS = ['hours', 'days', 'weeks', 'months'];
    public const FORMATS = ['in_person', 'online', 'mixed'];
    public const COSTS = ['free', 'paid'];

    /** The route section of each kind: /app/jobs/…, /app/training/… */
    public const SECTION = [self::JOB => 'jobs', self::TRAINING => 'training'];

    protected $guarded = ['id', 'company_id'];

    protected $casts = [
        'occupations'   => 'array',
        'governorates'  => 'array',
        'rules'         => 'array',
        'history'       => 'array',
        'seats'         => 'integer',
        'eligible_from' => 'integer',
        'check_from'    => 'integer',
        'rules_version' => 'integer',
        'salary_from'   => 'integer',
        'salary_to'     => 'integer',
        'duration_value' => 'integer',
        'cost_amount'   => 'integer',
        'deadline'      => 'date',
        'starts_on'     => 'date',
        'ends_on'       => 'date',
        'closed_at'     => 'datetime',
    ];

    public function scopeInWorkspace(Builder $q, int $companyId): Builder
    {
        return $q->where('opportunities.company_id', $companyId);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('opportunities.status', self::OPEN);
    }

    public function scopeOfKind(Builder $q, string $kind): Builder
    {
        return $q->where('opportunities.kind', $kind);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isJob(): bool
    {
        return $this->kind === self::JOB;
    }

    /** jobs | training — the route section. */
    public function section(): string
    {
        return self::SECTION[$this->kind] ?? 'jobs';
    }

    /** One line added to the opportunity's own history. */
    public function note(string $action, ?string $by, array $detail = []): void
    {
        $h = $this->history ?? [];
        $h[] = ['action' => $action, 'by' => $by !== null ? mb_substr($by, 0, 100) : null, 'at' => now()->toIso8601String()] + $detail;
        $this->history = array_slice($h, -100);
    }

    /** The short form the selectors show (profile panel, CV Bank). */
    public function brief(): array
    {
        return ['id' => $this->id, 'kind' => $this->kind, 'title' => $this->title, 'status' => $this->status];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(EligibilityAssessment::class, 'opportunity_id');
    }

    /** Step 13: the people referred to it. */
    public function matches(): HasMany
    {
        return $this->hasMany(OpportunityMatch::class, 'opportunity_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }
}
