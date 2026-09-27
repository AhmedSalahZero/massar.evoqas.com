<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — EligibilityAssessment (Step 11, inside Jobs & Training since Step 12)
//  Location: app/Models/EligibilityAssessment.php
//
//  One person checked against one job or training: the automatic score and
//  result with every rule's reason, and the case worker's decision.
//  `result` is the one that counts everywhere (lists, filters, the
//  pipeline): the decision when there is one, otherwise the automatic
//  result. Written only by App\Services\Eligibility\Assessor.
// ══════════════════════════════════════════════════════════════════

class EligibilityAssessment extends Model
{
    use BelongsToCompany;

    public const ELIGIBLE = 'eligible';
    public const CHECK = 'check';
    public const NOT_ELIGIBLE = 'not_eligible';
    public const ON_HOLD = 'on_hold';

    /** What the automatic check can say. */
    public const AUTO_RESULTS = [self::ELIGIBLE, self::CHECK, self::NOT_ELIGIBLE];

    /** What a case worker can decide. */
    public const DECISIONS = [self::ELIGIBLE, self::NOT_ELIGIBLE, self::ON_HOLD];

    public const PROFILE_CHANGED = 'profile_changed';
    public const RULES_CHANGED = 'rules_changed';

    protected $guarded = ['id', 'company_id'];

    protected $casts = [
        'score'         => 'integer',
        'reasons'       => 'array',
        'rules_version' => 'integer',
        'checked_at'    => 'datetime',
        'decided_at'    => 'datetime',
        'notes_at'      => 'datetime',
    ];

    public function scopeInWorkspace(Builder $q, int $companyId): Builder
    {
        return $q->where('eligibility_assessments.company_id', $companyId);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }
}
