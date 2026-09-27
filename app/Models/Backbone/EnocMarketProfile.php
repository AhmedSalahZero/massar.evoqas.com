<?php

namespace App\Models\Backbone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — EnocMarketProfile
//  Location: app/Models/Backbone/EnocMarketProfile.php
//
//  The Egypt labour market figures of one ENOC occupation in one
//  edition. NULL = the source has no figure ("no data" on screen).
// ══════════════════════════════════════════════════════════════════

class EnocMarketProfile extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'sectors'      => 'array',
        'regions'      => 'array',
        'knowledge'    => 'array',
        'abilities'    => 'array',
        'skills'       => 'array',
        'skill_groups' => 'array',
        'flags'        => 'array',
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(LabourMarketEdition::class, 'edition_id');
    }

    public function enoc(): BelongsTo
    {
        return $this->belongsTo(EnocOccupation::class, 'enoc_occupation_id');
    }
}
