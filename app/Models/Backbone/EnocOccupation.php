<?php

namespace App\Models\Backbone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — EnocOccupation (Egyptian Unified Occupational Classification)
//  Location: app/Models/Backbone/EnocOccupation.php
//
//  The 426 official Egyptian occupations (4-digit), with their
//  Egyptian Arabic title and task description. The code is identical
//  to the ISCO-08 unit group code, so isco_group_id is a 1:1 link.
//  Read-only in the app — written only by BackboneImporter.
// ══════════════════════════════════════════════════════════════════

class EnocOccupation extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function iscoGroup(): BelongsTo
    {
        return $this->belongsTo(IscoGroup::class);
    }
}
