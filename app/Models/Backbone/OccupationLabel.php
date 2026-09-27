<?php

namespace App\Models\Backbone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — OccupationLabel (search index of all occupation titles)
//  Location: app/Models/Backbone/OccupationLabel.php
//
//  One row per title a person might type: ISCO-08 and ENOC titles,
//  ESCO preferred titles, the Arabic masculine and feminine forms,
//  and ESCO's alternative titles. `normalized` is the
//  TextNormalizer form, indexed. Rebuilt on every import (nothing
//  else points at these rows).
// ══════════════════════════════════════════════════════════════════

class OccupationLabel extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function iscoGroup(): BelongsTo
    {
        return $this->belongsTo(IscoGroup::class);
    }

    public function escoOccupation(): BelongsTo
    {
        return $this->belongsTo(EscoOccupation::class);
    }
}
