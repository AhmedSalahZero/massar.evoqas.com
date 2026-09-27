<?php

namespace App\Models\Backbone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════════
//  Massar — EscoOccupation (ESCO detailed occupation)
//  Location: app/Models/Backbone/EscoOccupation.php
//
//  ~3,000 detailed occupations (ESCO v1.2.1), English + Arabic. Each
//  sits in one ISCO-08 unit group (isco_group_id) and may sit under a
//  broader ESCO occupation (parent_id: 2654.1 is the parent of
//  2654.1.7). The Arabic title is kept as published and split into
//  masculine / feminine forms so both are searchable.
//  Read-only in the app — written only by BackboneImporter.
// ══════════════════════════════════════════════════════════════════

class EscoOccupation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active'        => 'boolean',
        'is_regulated'     => 'boolean',
        'esco_modified_at' => 'datetime',
    ];

    public function iscoGroup(): BelongsTo
    {
        return $this->belongsTo(IscoGroup::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(OccupationLabel::class);
    }

    /** Broader ESCO occupations above this one, top first. */
    public function ancestors(): array
    {
        $chain = [];
        $node = $this->parent;
        while ($node) {
            array_unshift($chain, $node);
            $node = $node->parent;
        }

        return $chain;
    }
}
