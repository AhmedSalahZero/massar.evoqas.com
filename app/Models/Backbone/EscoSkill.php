<?php

namespace App\Models\Backbone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// ══════════════════════════════════════════════════════════════════
//  Massar — EscoSkill (one ESCO skill or knowledge item)
//  Location: app/Models/Backbone/EscoSkill.php
//
//  ~14,000 skills and knowledge items (ESCO v1.2.1), English + Arabic.
//    type         'skill' (skill/competence) | 'knowledge' | null
//    reuse_level  transversal · cross-sector · sector-specific ·
//                 occupation-specific (how widely it applies)
//  Linked to ESCO occupations as essential or optional
//  (esco_occupation_skills), placed in the skills tree
//  (esco_skill_parents) and searchable through skill_labels.
//  Read-only in the app — written only by SkillImporter.
// ══════════════════════════════════════════════════════════════════

class EscoSkill extends Model
{
    public const SKILL = 'skill';
    public const KNOWLEDGE = 'knowledge';

    protected $guarded = [];

    protected $casts = [
        'is_active'        => 'boolean',
        'esco_modified_at' => 'datetime',
    ];

    public function occupations(): BelongsToMany
    {
        return $this->belongsToMany(EscoOccupation::class, 'esco_occupation_skills', 'skill_id', 'esco_occupation_id')
            ->withPivot('is_essential');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(EscoSkillGroup::class, 'esco_skill_parents', 'skill_id', 'group_id');
    }
}
