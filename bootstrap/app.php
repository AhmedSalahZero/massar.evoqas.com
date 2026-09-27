<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureMember;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoStoreForAuthenticated;
use App\Http\Middleware\PreventDuplicateSubmission;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackDailyUserAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════════════
//  Massar — Application Bootstrap
//  Location: bootstrap/app.php
//
//  Routing, middleware and exception handling for the whole app.
//
//  Middleware on EVERY web request (in this order):
//    SetLocale              → en / ar from the user, or the guest session
//    HandleInertiaRequests  → shared props for every Vue page
//    TrackDailyUserAccess   → first page of the day → activity log
//    NoStoreForAuthenticated→ signed-in pages are never cached by a
//                             browser or proxy (they carry personal data)
//
//  Named aliases used in routes/web.php:
//    'admin'        → super_admin only        (/admin/*)
//    'member'       → partner staff only      (/app/*)
//    'no-duplicate' → refuses the same form submitted twice in a few
//                     seconds (double-click protection)
//    'auth.session' → signs out other sessions after a password change
//  Plus Laravel's own `can:permission.key` (see config/permissions.php).
// ══════════════════════════════════════════════════════════════════

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // SetLocale must run first so everything after it — including
        // the shared translations — uses the right language.
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            TrackDailyUserAccess::class,
            NoStoreForAuthenticated::class,
        ]);

        $middleware->alias([
            'admin'        => EnsureAdmin::class,
            'member'       => EnsureMember::class,
            'no-duplicate' => PreventDuplicateSubmission::class,
            'seeker.auth'     => \App\Http\Middleware\AuthenticateSeeker::class,
            'seeker.verified' => \App\Http\Middleware\EnsureSeekerVerified::class,
            'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        ]);

        // Guests hitting a signed-in page go to the login screen;
        // signed-in users hitting a guest page go home.
        // Sign-out must always work, even when the page has been open
        // so long that its security token expired ("419 Page Expired").
        // The only thing a forged sign-out request could do is sign
        // someone out, so skipping the token check here is safe.
        $middleware->validateCsrfTokens(except: ['logout']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        // A signed-in job seeker (Step 10) opening the job seekers' sign-in or
        // registration goes to "My profile"; staff go home, as before.
        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('seeker.*') ? route('seeker.profile') : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ── Rate limiting must be VISIBLE ──────────────────────────
        // A throttled request returns a bare 429 page, which is not an
        // Inertia response — the form would simply do nothing, the
        // person would retry, and every retry extends the lockout.
        // Send it back as a normal validation error the form already
        // knows how to show, including how long to wait.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->header('X-Inertia')) {
                return null;
            }

            $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);

            return back()->withErrors([
                'email' => __('auth.throttle_requests', ['seconds' => $seconds]),
            ]);
        });
    })
    ->create();
