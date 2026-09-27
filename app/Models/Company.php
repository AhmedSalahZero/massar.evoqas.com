<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════════
//  Massar — Company (Partner Organisation)
//  Location: app/Models/Company.php
//
//  The tenant. A partner organisation (NGO, training provider,
//  employer, government programme) and everything it owns. Created
//  only by the Super Admin (Scope v2 §4 "Company Onboarding" — there
//  is deliberately no self-service company sign-up).
//
//  Subscription & seats:
//    seat_limit           → max user accounts, company admin included
//    subscription_ends_at → access stops after this; NULL = no expiry
//    expiry_notified_at   → last reminder email (see
//                           subscriptions:notify-expiring)
//
//  New companies start a subscription window automatically (length
//  from config/subscription.php) unless one is passed explicitly —
//  see booted(). No path can create a company with unlimited free
//  access by forgetting to set it.
// ══════════════════════════════════════════════════════════════════

class Company extends Model
{
    use HasFactory;

    /** Partner organisation types (Scope v2 §3 — who posts and works with beneficiaries). */
    public const TYPES = ['ngo', 'training_provider', 'employer', 'government', 'other'];

    protected $fillable = [
        'name',
        'name_ar',
        'type',
        'governorate',
        'contact_email',
        'contact_phone',
        'seat_limit',
        'subscription_ends_at',
        'expiry_notified_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'is_pool'              => 'boolean',
        'seat_limit'           => 'integer',
        'subscription_ends_at' => 'datetime',
        'expiry_notified_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        // The Talent Pool workspace (Step 10) is not a partner: it is left
        // out of every list, count and reminder. Only Company::pool() and
        // code that asks for it on purpose (withoutGlobalScope) sees it.
        static::addGlobalScope('partners', fn (Builder $q) => $q->where($q->getModel()->getTable().'.is_pool', false));

        static::creating(function (self $company) {
            if (! array_key_exists('subscription_ends_at', $company->getAttributes())) {
                $company->subscription_ends_at = now()->addMonthsNoOverflow(
                    (int) config('subscription.initial_months', 12)
                );
            }

            if (! array_key_exists('seat_limit', $company->getAttributes())) {
                $company->seat_limit = (int) config('subscription.default_seats', 5);
            }
        });
    }

    /** The one Talent Pool workspace: the profiles job seekers made themselves (made by its migration). */
    public static function pool(): self
    {
        return static::query()->withoutGlobalScope('partners')->where('is_pool', true)->orderBy('id')->firstOrFail();
    }

    public static function poolId(): int
    {
        return (int) static::query()->withoutGlobalScope('partners')->where('is_pool', true)->orderBy('id')->value('id');
    }

    // ── Subscription ───────────────────────────────────────────────

    /** Extend (or restart) the subscription from now. */
    public function extendSubscription(int $months): static
    {
        $this->forceFill([
            'subscription_ends_at' => now()->addMonthsNoOverflow($months),
            'expiry_notified_at'   => null,
        ])->save();

        return $this;
    }

    /** The subscription window has closed — nobody in the company can sign in. */
    public function hasLapsed(): bool
    {
        return $this->subscription_ends_at !== null && $this->subscription_ends_at->isPast();
    }

    /** Whole days left, or null when there is no expiry. Never negative. */
    public function daysUntilExpiry(): ?int
    {
        if ($this->subscription_ends_at === null) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->subscription_ends_at->startOfDay(), false));
    }

    /** Inside the warning window — drives the in-app banner and the reminder email. */
    public function isExpiringSoon(): bool
    {
        $days = $this->daysUntilExpiry();

        return $days !== null
            && ! $this->hasLapsed()
            && $days <= (int) config('subscription.notify_days_before', 14);
    }

    // ── Seats ──────────────────────────────────────────────────────

    public function seatsUsed(): int
    {
        return $this->users()->count();
    }

    public function seatsLeft(): int
    {
        return max(0, $this->seat_limit - $this->seatsUsed());
    }

    public function hasFreeSeat(): bool
    {
        return $this->seatsLeft() > 0;
    }

    // ── Display ────────────────────────────────────────────────────

    public function displayName(?string $locale = null): string
    {
        $ar = ($locale ?? app()->getLocale()) === 'ar';

        return $ar && $this->name_ar ? $this->name_ar : $this->name;
    }

    // ── Relations ──────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function admins(): HasMany
    {
        return $this->users()->where('role', \App\Enums\UserRole::CompanyAdmin->value);
    }
}
