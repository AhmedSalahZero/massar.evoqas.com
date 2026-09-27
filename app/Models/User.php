<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Services\Auth\EmailVerificationService;
use App\Support\Permissions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// ══════════════════════════════════════════════════════════════════
//  Massar — User
//  Location: app/Models/User.php
//
//  Every person who signs in: the Massar platform team (super_admin)
//  and partner staff (company_admin, employee). Beneficiaries who
//  self-register on the public portal are NOT users — they get
//  their own model when Module A is built.
//
//  Key helpers:
//    isSuperAdmin() / isCompanyAdmin() / isEmployee()
//    permissionKeys()        → the permission keys this user holds
//                              (see config/permissions.php)
//    accessDenialReason()    → why this user may not use the app
//                              right now, or null. Single source of
//                              truth for LoginRequest (at sign-in)
//                              and EnsureMember (on every request).
// ══════════════════════════════════════════════════════════════════

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const STANDARDS = ['enoc', 'isco', 'esco'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'job_title',
        'permissions',
        'company_id',
        'created_by',
        'language',
        'theme',
        'occupation_standard',
        'is_active',
        'last_login_at',
        'last_activity_at',
        'login_count',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'last_activity_at'  => 'datetime',
        'login_count'       => 'integer',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'permissions'       => 'array',
    ];

    // ── Roles ──────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin->value;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === UserRole::CompanyAdmin->value;
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::Employee->value;
    }

    public function roleLabel(?string $locale = null): string
    {
        return UserRole::tryFrom($this->role)?->label($locale) ?? $this->role;
    }

    // ── Permissions ────────────────────────────────────────────────

    /** Permission keys this user holds (role defaults while `permissions` is NULL). */
    public function permissionKeys(): array
    {
        return Permissions::for($this);
    }

    // ── Access ─────────────────────────────────────────────────────

    /**
     * Why this user may not use the app right now, as a translation
     * key — or null when access is allowed. The most specific reason
     * wins: a suspended user in a suspended company is told about
     * their own account.
     */
    public function accessDenialReason(): ?string
    {
        if (! $this->is_active) {
            return 'errors.account_suspended';
        }

        if ($this->isSuperAdmin()) {
            return null;
        }

        $company = Company::query()
            ->whereKey($this->company_id)
            ->first(['id', 'is_active', 'subscription_ends_at']);

        if (! $company) {
            return 'errors.account_orphaned';
        }

        if (! $company->is_active) {
            return 'errors.company_suspended';
        }

        if ($company->hasLapsed()) {
            return 'errors.subscription_expired';
        }

        return null;
    }

    // ── Email verification & password reset ────────────────────────

    /** With AUTH_EMAIL_VERIFICATION_ENABLED=false every user counts as verified. */
    public function hasVerifiedEmail(): bool
    {
        if (! config('auth_verification.enabled')) {
            return true;
        }

        return $this->email_verified_at !== null;
    }

    public function sendEmailVerificationNotification(): void
    {
        app(EmailVerificationService::class)->issueAndSend($this);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    // ── Display ────────────────────────────────────────────────────

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    // ── Relations ──────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function emailVerificationCodes(): HasMany
    {
        return $this->hasMany(EmailVerificationCode::class);
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(UserLoginActivity::class);
    }
}
