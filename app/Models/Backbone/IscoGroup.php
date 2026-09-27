<?php

namespace App\Models\Backbone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// ══════════════════════════════════════════════════════════════════
//  Massar — IscoGroup (ISCO-08, all four levels)
//  Location: app/Models/Backbone/IscoGroup.php
//
//  One row per ISCO-08 group: level 1 major ('2'), 2 sub-major ('26'),
//  3 minor ('265'), 4 unit ('2654'). Unit groups are the hinge of the
//  backbone: an ENOC occupation IS a unit group (same code), and every
//  ESCO occupation belongs to exactly one.
//  Read-only in the app — written only by BackboneImporter.
// ══════════════════════════════════════════════════════════════════

class IscoGroup extends Model
{
    public const LEVEL_MAJOR = 1;
    public const LEVEL_SUB_MAJOR = 2;
    public const LEVEL_MINOR = 3;
    public const LEVEL_UNIT = 4;

    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function enoc(): HasOne
    {
        return $this->hasOne(EnocOccupation::class);
    }

    public function escoOccupations(): HasMany
    {
        return $this->hasMany(EscoOccupation::class);
    }

    public function scopeUnits($query)
    {
        return $query->where('level', self::LEVEL_UNIT);
    }

    /** Major → sub-major → minor → this group, top first. */
    public function lineage(): array
    {
        $codes = [];
        for ($len = 1; $len <= strlen($this->code); $len++) {
            $codes[] = substr($this->code, 0, $len);
        }

        return self::query()->whereIn('code', $codes)->orderBy('level')->get()->all();
    }
}
