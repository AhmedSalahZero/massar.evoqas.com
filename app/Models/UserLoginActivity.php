<?php

namespace App\Models;

use App\Enums\LoginActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ══════════════════════════════════════════════════════════════════
//  Massar — UserLoginActivity
//  Location: app/Models/UserLoginActivity.php
//
//  One row of the append-only activity log (user_login_activities).
//  Written only by UserLoginFrequencyService; read by the Super Admin
//  Activity screen. No updated_at — rows never change.
// ══════════════════════════════════════════════════════════════════

class UserLoginActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'login_at',
        'activity_date',
        'type',
        'source',
    ];

    protected $casts = [
        'login_at'      => 'datetime',
        'activity_date' => 'date',
        'type'          => LoginActivityType::class,
        'created_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
