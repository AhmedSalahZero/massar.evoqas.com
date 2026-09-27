<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Notifications\SubscriptionEndingNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// ══════════════════════════════════════════════════════════════════
//  Massar — subscriptions:notify-expiring
//  Location: app/Console/Commands/NotifyExpiringSubscriptions.php
//
//  Daily (routes/console.php). Emails the Company Admins of every
//  active partner whose subscription ends within
//  subscription.notify_days_before days — at most once every
//  subscription.notify_again_after_days, tracked in
//  companies.expiry_notified_at, so the final two weeks are not a
//  daily email.
//
//  Only verified, active admins are emailed. A partner with none is
//  reported in the command output so the Massar team can follow up.
// ══════════════════════════════════════════════════════════════════

class NotifyExpiringSubscriptions extends Command
{
    protected $signature = 'subscriptions:notify-expiring';

    protected $description = 'Email partner admins whose Massar subscription is about to end';

    public function handle(): int
    {
        $windowDays  = (int) config('subscription.notify_days_before', 14);
        $repeatAfter = (int) config('subscription.notify_again_after_days', 3);

        $companies = Company::query()
            ->where('is_active', true)
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '>', now())
            ->where('subscription_ends_at', '<=', now()->addDays($windowDays))
            ->where(fn ($q) => $q->whereNull('expiry_notified_at')
                ->orWhere('expiry_notified_at', '<=', now()->subDays($repeatAfter)))
            ->get();

        $emailed = 0;

        foreach ($companies as $company) {
            $admins = User::query()
                ->where('company_id', $company->id)
                ->where('role', UserRole::CompanyAdmin->value)
                ->where('is_active', true)
                ->whereNotNull('email_verified_at')
                ->get();

            if ($admins->isEmpty()) {
                $this->warn("Partner #{$company->id} ({$company->name}) has no verified admin to notify.");

                continue;
            }

            $daysLeft = (int) $company->daysUntilExpiry();

            foreach ($admins as $admin) {
                try {
                    $admin->notify(new SubscriptionEndingNotification($company, $daysLeft));
                    $emailed++;
                } catch (\Throwable $e) {
                    Log::error('Subscription reminder failed', [
                        'company_id' => $company->id,
                        'user_id'    => $admin->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $company->forceFill(['expiry_notified_at' => now()])->save();
        }

        $this->info("Subscription reminders: emailed {$emailed} admin(s) across {$companies->count()} partner(s).");

        return self::SUCCESS;
    }
}
