<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// ══════════════════════════════════════════════════════════════════
//  Massar — SubscriptionEndingNotification
//  Location: app/Notifications/SubscriptionEndingNotification.php
//
//  Emailed to a partner's Company Admins when their subscription is
//  inside the warning window (config/subscription.php
//  notify_days_before). Sent by `subscriptions:notify-expiring`.
//  Written in the admin's own language. Sent at once, not queued: a
//  queued email waits for a queue worker (`php artisan queue:work`),
//  and without one it is never sent; the command already runs in the
//  background and carries on past a failed email.
//  Views: emails/subscription-ending(.blade.php) + text twin.
// ══════════════════════════════════════════════════════════════════

class SubscriptionEndingNotification extends Notification
{
    public function __construct(
        private readonly Company $company,
        private readonly int $daysLeft,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->language ?? app()->getLocale();

        return (new MailMessage)
            ->subject(trans_choice('emails.subscription_ending.subject', $this->daysLeft, ['days' => $this->daysLeft], $locale))
            ->view(['emails.subscription-ending', 'emails.text.subscription-ending'], [
                'user'     => $notifiable,
                'company'  => $this->company,
                'daysLeft' => $this->daysLeft,
                'endsOn'   => $this->company->subscription_ends_at?->toDateString(),
                'locale'   => $locale,
            ]);
    }
}
