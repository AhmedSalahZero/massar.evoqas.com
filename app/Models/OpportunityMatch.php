<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════════
//  Massar — OpportunityMatch (Step 13 · Matches)
//  Location: app/Models/OpportunityMatch.php
//  Scope: docs/SCOPE_MATCHES.md
//
//  One person referred to one Job or Training of ONE workspace, with its
//  current stage and its timeline (OpportunityMatchEvent). ("Match" alone
//  is a reserved word in PHP, hence the longer name.)
//
//      referred → accepted → in_progress → done
//      Job:       Referred · Accepted by the employer · Interviewing · Hired
//      Training:  Referred · Accepted · In training · Completed
//
//  At any stage before "done" it can be STOPPED, with a reason. A seat is
//  taken from "accepted" onwards, while the match is active.
//  Written only by App\Services\Matches\Matcher.
// ══════════════════════════════════════════════════════════════════

class OpportunityMatch extends Model
{
    use BelongsToCompany;

    protected $table = 'opportunity_matches';

    public const REFERRED = 'referred';
    public const ACCEPTED = 'accepted';
    public const IN_PROGRESS = 'in_progress';
    public const DONE = 'done';

    /** In order. */
    public const STAGES = [self::REFERRED, self::ACCEPTED, self::IN_PROGRESS, self::DONE];

    /** The stages that take a seat. */
    public const SEATED = [self::ACCEPTED, self::IN_PROGRESS, self::DONE];

    public const ACTIVE = 'active';
    public const STOPPED = 'stopped';

    /** Why it stopped, for each kind. */
    public const STOP_REASONS = [
        Opportunity::JOB      => ['not_accepted', 'not_hired', 'withdrew', 'other'],
        Opportunity::TRAINING => ['not_accepted', 'dropped_out', 'withdrew', 'other'],
    ];

    /** "Needs follow-up": no change recorded for this many days (agreed: 14, the same for everyone). */
    public const FOLLOW_UP_DAYS = 14;

    protected $guarded = ['id', 'company_id'];

    protected $casts = [
        'referred_on' => 'date',
        'stage_on'    => 'date',
        'changed_at'  => 'datetime',
    ];

    public function scopeInWorkspace(Builder $q, int $companyId): Builder
    {
        return $q->where('opportunity_matches.company_id', $companyId);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('opportunity_matches.status', self::ACTIVE);
    }

    /** Taking a seat: accepted onwards, and not stopped. */
    public function scopeSeated(Builder $q): Builder
    {
        return $q->where('opportunity_matches.status', self::ACTIVE)->whereIn('opportunity_matches.stage', self::SEATED);
    }

    /** Hired or completed. */
    public function scopePlaced(Builder $q): Builder
    {
        return $q->where('opportunity_matches.status', self::ACTIVE)->where('opportunity_matches.stage', self::DONE);
    }

    /** Active, not done yet, and nothing recorded for 14 days. */
    public function scopeNeedsFollowUp(Builder $q): Builder
    {
        return $q->where('opportunity_matches.status', self::ACTIVE)->where('opportunity_matches.stage', '!=', self::DONE)
            ->where('opportunity_matches.changed_at', '<=', now()->subDays(self::FOLLOW_UP_DAYS));
    }

    public static function rank(string $stage): int
    {
        return (int) array_search($stage, self::STAGES, true);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isDone(): bool
    {
        return $this->stage === self::DONE;
    }

    public function takesSeat(): bool
    {
        return $this->isActive() && in_array($this->stage, self::SEATED, true);
    }

    public function needsFollowUp(): bool
    {
        return $this->isActive() && ! $this->isDone() && $this->changed_at && $this->changed_at->lte(now()->subDays(self::FOLLOW_UP_DAYS));
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OpportunityMatchEvent::class, 'match_id')->orderByDesc('id');
    }
}
