<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — OpportunityMatchEvent (Step 13 · the match timeline)
//  Location: app/Models/OpportunityMatchEvent.php
//
//  One line of a match's timeline: referred, moved (with the stages it
//  skipped), corrected (back one stage, with a reason), stopped (with a
//  reason), restarted (with a reason). The date it happened
//  (happened_on) and the date it was recorded (created_at), who, and the
//  note. Corrections are NEW lines: nothing here is ever edited.
// ══════════════════════════════════════════════════════════════════

class OpportunityMatchEvent extends Model
{
    use BelongsToCompany;

    protected $table = 'opportunity_match_events';

    public const UPDATED_AT = null;

    public const ACTIONS = ['referred', 'moved', 'corrected', 'stopped', 'restarted'];

    protected $guarded = ['id'];

    protected $casts = [
        'skipped'     => 'array',
        'happened_on' => 'date',
        'created_at'  => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(OpportunityMatch::class, 'match_id');
    }

    /** As the screens show it. */
    public function present(): array
    {
        return [
            'id'      => $this->id,
            'action'  => $this->action,
            'from'    => $this->from_stage,
            'to'      => $this->to_stage,
            'skipped' => $this->skipped ?? [],
            'reason'  => $this->reason,
            'note'    => $this->note,
            'on'      => $this->happened_on?->toDateString(),
            'at'      => $this->created_at?->toIso8601String(),
            'by'      => $this->user_name,
        ];
    }
}
