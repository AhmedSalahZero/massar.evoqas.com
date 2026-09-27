<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvBatch (one upload of up to 50 CVs)
//  Location: app/Models/CvBatch.php
//  Created by App\CvUploadController@batch; its files are counted as
//  they arrive, so a batch can never receive more files than it said.
// ══════════════════════════════════════════════════════════════════

class CvBatch extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    public function scopeInWorkspace(Builder $query, int $companyId): Builder
    {
        return $query->where($this->getTable().'.company_id', $companyId);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CvDocument::class, 'batch_id');
    }
}
