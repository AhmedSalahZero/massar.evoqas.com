<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// ══════════════════════════════════════════════════════════════════
//  Massar — JobSeeker (a job seeker's own sign-in, Step 10)
//  Location: app/Models/JobSeeker.php
//  Table: see database/migrations/…_create_talent_pool_tables.php
//
//  Separate from staff accounts (User): its own table, its own sign-in
//  guard ('seeker', config/auth.php), its own pages under the public
//  site. A job seeker can never open the partner workspace, and staff
//  are never signed in as a job seeker.
//
//  The profile itself is a Beneficiary in the Talent Pool workspace
//  (Company::pool()), so it is read, checked and searched by the same
//  code as every partner's profiles. Changes the job seeker makes are
//  recorded by BeneficiaryRecorder like any other, "by" actor().
// ══════════════════════════════════════════════════════════════════

class JobSeeker extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword;
    use Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'code_hash'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'consented_at'      => 'datetime',
        'code_expires_at'   => 'datetime',
        'last_login_at'     => 'datetime',
        'visible'           => 'boolean',
        'code_attempts'     => 'integer',
        'password'          => 'hashed',
    ];

    public const NOTICE_PERIODS = ['now', '2_weeks', '1_month', '2_months', '3_months'];

    /** In the Talent Pool: confirmed email, consent given, a profile. */
    public function scopeInPool(Builder $q): Builder
    {
        return $q->where('visible', true)->whereNotNull('email_verified_at')->whereNotNull('beneficiary_id');
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /** "Name" for the emails (the greeting line). */
    public function getNameAttribute(): string
    {
        $b = $this->profile;

        return $b ? $b->displayName($this->language) : '';
    }

    /**
     * Who the profile history names when the job seeker changes their own
     * profile. Not a staff account: nothing is saved for it.
     */
    public static function actor(): User
    {
        return (new User)->forceFill(['name' => 'Job seeker · الباحث عن عمل']);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notifyNow(new ResetPasswordNotification($token, 'seeker.password.reset', 'seekers'));
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class, 'beneficiary_id')->withoutGlobalScopes();
    }

    public function additions(): HasMany
    {
        return $this->hasMany(PoolAddition::class)->orderByDesc('id');
    }
}
