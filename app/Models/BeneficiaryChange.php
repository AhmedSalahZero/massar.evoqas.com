<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — BeneficiaryChange (the history of one profile)
//  Location: app/Models/BeneficiaryChange.php
//
//  One row each time a profile is registered or changed: who did it
//  (user_id, and their name as it was), when, and what changed.
//    changes = {"governorate": {"from": "cai", "to": "giz"},
//               "work_history": {"changed": true}, …}
//  Lists (work history, education …) are marked as changed without
//  copying them again; single values keep their before and after.
//
//  Written only by BeneficiaryRecorder, never edited or deleted from
//  the app. Same partner separation as Beneficiary.
// ══════════════════════════════════════════════════════════════════

class BeneficiaryChange extends Model
{
    use BelongsToCompany;

    public const CREATED = 'created';
    public const UPDATED = 'updated';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
