<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// ══════════════════════════════════════════════════════════════════
//  Massar — ResetPasswordNotification
//  Location: app/Notifications/ResetPasswordNotification.php
//
//  The password reset link email, in the user's own language.
//  Sent at once, not queued: the person is waiting for it, and a
//  queued email is never sent without a queue worker running.
//  Views: emails/reset-password + text twin.
// ══════════════════════════════════════════════════════════════════

class ResetPasswordNotification extends Notification
{
    /**
     * @param  string  $route   where the link goes: staff 'password.reset', job seekers 'seeker.password.reset'
     * @param  string  $broker  whose expiry time the email states (config/auth.php passwords.*)
     */
    public function __construct(
        private readonly string $token,
        private readonly string $route = 'password.reset',
        private readonly string $broker = 'users',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->language ?? app()->getLocale();
        // On APP_URL, never on the address the request came in on: that
        // comes from the request's Host header, which a sender can set to
        // their own site and so receive the reset token.
        $url = rtrim((string) config('app.url'), '/').route($this->route, [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);

        $expireMinutes = config('auth.passwords.'.$this->broker.'.expire');

        return (new MailMessage)
            ->subject(__('emails.reset_password.subject', [], $locale))
            ->view(['emails.reset-password', 'emails.text.reset-password'], [
                'user'           => $notifiable,
                'url'            => $url,
                'expireMinutes'  => $expireMinutes,
                'locale'         => $locale,
            ]);
    }
}
