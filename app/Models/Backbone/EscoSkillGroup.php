<?php

namespace App\Models\Backbone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — EscoSkillGroup (the ESCO skills tree)
//  Location: app/Models/Backbone/EscoSkillGroup.php
//
//  640 groups in four pillars (level 0): S skills, K knowledge (the
//  UNESCO ISCED-F fields of education, e.g. 0411 accounting and
//  taxation), L language, T transversal; then up to three levels
//  below. ESCO has no Arabic names for the ISCED knowledge fields.
//  Read-only in the app — written only by SkillImporter.
// ══════════════════════════════════════════════════════════════════

class EscoSkillGroup extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Pillar → … → this group, top first. */
    public function lineage(): array
    {
        $chain = [$this];
        $node = $this;
        while ($node->parent_id && count($chain) < 10) {
            $node = $node->parent;
            array_unshift($chain, $node);
        }

        return $chain;
    }
}
