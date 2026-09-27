<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════════════
//  Massar — PoolAccessLog: partner staff opened a Talent Pool profile,
//  downloaded its CV, or added it (Scope v2 §7 access logs).
//  Location: app/Models/PoolAccessLog.php
// ══════════════════════════════════════════════════════════════════

class PoolAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public static function write(JobSeeker $seeker, User $user, string $action): void
    {
        static::query()->create([
            'job_seeker_id' => $seeker->id,
            'company_id'    => $user->company_id,
            'user_id'       => $user->id,
            'user_name'     => mb_substr($user->name, 0, 100),
            'action'        => $action,
        ]);
    }
}
