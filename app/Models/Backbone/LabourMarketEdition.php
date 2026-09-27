<?php

namespace App\Models\Backbone;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════════
//  Massar — LabourMarketEdition
//  Location: app/Models/Backbone/LabourMarketEdition.php
//
//  One loaded Egypt Occupational Outlook file. Exactly one completed
//  edition is current (is_current) — that is the one every screen and
//  report uses. Switching is deliberate: the "Use this edition" button
//  on the admin backbone page, or `php artisan market:use <id>`.
// ══════════════════════════════════════════════════════════════════

class LabourMarketEdition extends Model
{
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'is_current'      => 'boolean',
        'reference_estimated' => 'boolean',
        'counts'          => 'array',
        'quality'         => 'array',
        'comparison'      => 'array',
        'made_current_at' => 'datetime',
    ];

    public function profiles(): HasMany
    {
        return $this->hasMany(EnocMarketProfile::class, 'edition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Who put this edition in use (null: command line or automatic). */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'made_current_by');
    }

    public static function current(): ?self
    {
        return self::query()->where('is_current', true)->where('status', self::COMPLETED)->first();
    }
}
