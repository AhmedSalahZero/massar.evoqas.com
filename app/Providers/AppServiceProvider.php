<?php

namespace App\Providers;

use App\Listeners\RecordSuccessfulLogin;
use App\Services\UserLoginFrequencyService;
use App\Support\EmailStyles;
use App\Support\PasswordRules;
use App\Support\Permissions;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

// ══════════════════════════════════════════════════════════════════
//  Massar — AppServiceProvider
//  Location: app/Providers/AppServiceProvider.php
//
//  App-wide wiring, in one place:
//    · Permission backbone — Gate::before() hands every check for a
//      key in config/permissions.php to App\Support\Permissions, so
//      $user->can('cv.upload') and route middleware `can:cv.upload`
//      work everywhere.
//    · Login tracking — every successful login is recorded
//      (RecordSuccessfulLogin → UserLoginFrequencyService).
//    · Password rules — one definition (App\Support\PasswordRules)
//      used by Password::defaults() across the app.
//    · Email styles — every emails.* view gets $s, the inline style
//      set for its language (LTR/RTL), from App\Support\EmailStyles.
//    · Safety in development — lazy loading and silently discarded
//      attributes throw locally, so N+1 queries and typos in
//      $fillable show up while building, not in production.
//    · HTTPS is forced in production.
// ══════════════════════════════════════════════════════════════════

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserLoginFrequencyService::class);
    }

    public function boot(): void
    {
        // ── Permission backbone ────────────────────────────────────
        Gate::before(fn ($user, string $ability) => Permissions::check($user, $ability));

        // ── Events ─────────────────────────────────────────────────
        Event::listen(Login::class, RecordSuccessfulLogin::class);

        // ── Frontend ───────────────────────────────────────────────
        Vite::prefetch(concurrency: 3);

        // ── Models ─────────────────────────────────────────────────
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // ── Security ───────────────────────────────────────────────
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => PasswordRules::defaults());

        // ── Emails ─────────────────────────────────────────────────
        View::composer('emails.*', function ($view) {
            $locale = $view->getData()['locale'] ?? app()->getLocale();

            $view->with('s', EmailStyles::for($locale));
        });
    }
}
