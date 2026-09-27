<?php

namespace App\Listeners;

use App\Services\UserLoginFrequencyService;
use Illuminate\Auth\Events\Login;

// ══════════════════════════════════════════════════════════════════
//  Massar — RecordSuccessfulLogin
//  Location: app/Listeners/RecordSuccessfulLogin.php
//
//  Listens for Laravel's Login event and records it in the activity
//  log (UserLoginFrequencyService). Registered in AppServiceProvider.
// ══════════════════════════════════════════════════════════════════

class RecordSuccessfulLogin
{
    public function __construct(
        private readonly UserLoginFrequencyService $loginFrequency,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof \App\Models\User) {
            return;
        }

        $source = $event->guard === 'web' ? 'web' : (string) $event->guard;

        $this->loginFrequency->recordExplicitLogin($event->user, $source);
    }
}
