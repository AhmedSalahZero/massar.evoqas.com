<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — PoolAddition (a partner added a job seeker from the Talent Pool)
//  Location: app/Models/PoolAddition.php
//
//  The partner's own copy of the profile is beneficiary_id (in the
//  partner's workspace). The job seeker sees the partner's name in
//  "My profile"; they are not notified. Written only by
//  App\Services\Pool\TalentPool::add().
// ══════════════════════════════════════════════════════════════════

class PoolAddition extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(JobSeeker::class, 'job_seeker_id');
    }
}
