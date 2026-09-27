<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — EligibilityRun (Step 11 · "Check everyone", inside Jobs & Training since Step 12)
//  Location: app/Models/EligibilityRun.php
//
//  One "Check everyone" of a job or training over:
//    all       every person in the workspace
//    search    the people a CV Bank search found (ids kept at the start)
//    outdated  the people whose result was made with older rules
//  Moved forward by App\Services\Eligibility\RunStepper, a few hundred
//  profiles per step.
// ══════════════════════════════════════════════════════════════════

class EligibilityRun extends Model
{
    use BelongsToCompany;

    public const SCOPES = ['all', 'search', 'outdated'];

    protected $guarded = ['id', 'company_id'];

    protected $casts = [
        'filters'     => 'array',
        'counts'      => 'array',
        'cursor'      => 'integer',
        'total'       => 'integer',
        'done'        => 'integer',
        'finished_at' => 'datetime',
    ];

    public function scopeInWorkspace(Builder $q, int $companyId): Builder
    {
        return $q->where('eligibility_runs.company_id', $companyId);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function present(): array
    {
        return [
            'id'     => $this->id,
            'scope'  => $this->scope,
            'total'  => $this->total,
            'done'   => $this->done,
            'counts' => $this->counts ?? ['eligible' => 0, 'check' => 0, 'not_eligible' => 0],
            'status' => $this->status,
            'by'     => $this->started_by_name,
            'at'     => $this->created_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
