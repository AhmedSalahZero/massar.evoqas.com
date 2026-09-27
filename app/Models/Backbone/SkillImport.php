<?php

namespace App\Models\Backbone;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — SkillImport (version history of the ESCO skills)
//  Location: app/Models/Backbone/SkillImport.php
//
//  One row per `php artisan skills:import` run: which files and
//  edition were used (with checksums), which occupation import the
//  skills were linked to, what was imported and found, and whether it
//  succeeded. The Backbone screen shows it.
// ══════════════════════════════════════════════════════════════════

class SkillImport extends Model
{
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const UNCHANGED = 'unchanged';

    protected $guarded = [];

    protected $casts = [
        'files'              => 'array',
        'counts'             => 'array',
        'backbone_import_id' => 'integer',
        'started_at'         => 'datetime',
        'finished_at'        => 'datetime',
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
