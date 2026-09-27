<?php

namespace App\Models\Backbone;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — BackboneImport (version history of the backbone)
//  Location: app/Models/Backbone/BackboneImport.php
//
//  One row per `php artisan backbone:import` run: which files and
//  editions were used (with checksums), what was imported and
//  checked, and whether it succeeded. The Backbone screen shows it.
// ══════════════════════════════════════════════════════════════════

class BackboneImport extends Model
{
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const UNCHANGED = 'unchanged';

    protected $guarded = [];

    protected $casts = [
        'editions'    => 'array',
        'files'       => 'array',
        'counts'      => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function lastCompleted(): ?self
    {
        return self::query()->where('status', self::COMPLETED)->latest('id')->first();
    }
}
