<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════════════
//  Massar — SavedReport (Step 14 · Reports)
//  Location: app/Models/SavedReport.php
//
//  A report's QUESTION (filters, count or average, split, date range),
//  saved under a name for the team of one workspace. It never keeps
//  numbers: they are worked out again each time it is opened.
//  The person who saved it, or a Company Admin, can rename or delete it.
// ══════════════════════════════════════════════════════════════════

class SavedReport extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['params' => 'array'];

    public function scopeInWorkspace(Builder $q, int $companyId): Builder
    {
        return $q->where('saved_reports.company_id', $companyId);
    }

    public function canChange(User $user): bool
    {
        return (int) $this->created_by === (int) $user->id || $user->isCompanyAdmin();
    }
}
