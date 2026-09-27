<?php

namespace App\Models;

use App\Models\Backbone\EscoOccupation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — LearnedRule (Scope v2 §3 Learned Rules, two layers)
//  Location: app/Models/LearnedRule.php
//
//  See the migration for what one row means. Not scoped by the
//  BelongsToCompany trait on purpose: a partner must also READ the
//  Massar rules (company_id NULL). Every query says which layer it
//  wants: ->inWorkspace($id), ->massar(), or ->forReading($id) (both).
// ══════════════════════════════════════════════════════════════════

class LearnedRule extends Model
{
    public const KINDS = ['heading', 'title', 'skill', 'employer'];

    public const PENDING = 'pending';
    public const PROMOTED = 'promoted';
    public const DECLINED = 'declined';

    /** What a heading can be taught to mean. */
    public const SECTIONS = [
        // read into the form
        'experience', 'internships', 'responsibilities', 'education', 'skills', 'languages', 'skills_languages',
        'personal', 'contact', 'military',
        // recognised, so their text is not mixed into another section
        'summary', 'objective', 'courses', 'projects', 'volunteering', 'achievements', 'interests', 'references',
        'publications', 'research', 'memberships', 'availability', 'salary', 'preferences', 'additional',
        // "this line is not a heading"
        'none',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'uses'         => 'integer',
        'last_used_at' => 'datetime',
        'proposed_at'  => 'datetime',
        'decided_at'   => 'datetime',
    ];

    public function scopeInWorkspace(Builder $q, int $companyId): Builder
    {
        return $q->where('learned_rules.company_id', $companyId);
    }

    public function scopeMassar(Builder $q): Builder
    {
        return $q->whereNull('learned_rules.company_id');
    }

    /** The rules a CV of this workspace is read with: its own and Massar's. */
    public function scopeForReading(Builder $q, ?int $companyId): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('learned_rules.company_id')->when($companyId, fn ($x) => $x->orWhere('learned_rules.company_id', $companyId)));
    }

    public function isMassar(): bool
    {
        return $this->company_id === null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function escoOccupation(): BelongsTo
    {
        return $this->belongsTo(EscoOccupation::class);
    }
}
