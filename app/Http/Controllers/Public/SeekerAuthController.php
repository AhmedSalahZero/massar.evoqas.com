<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\JobSeeker;
use App\Services\Pool\SeekerEmailCode;
use App\Support\PasswordRules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — job seekers' sign-in (Step 10)
//  Location: app/Http/Controllers/Public/SeekerAuthController.php
//  Routes:  GET/POST /sign-in                  (seeker.login)
//           POST     /sign-out                 (seeker.logout)
//           GET/POST /sign-in/forgot           (seeker.password.request / .email)
//           GET      /sign-in/reset/{token}    (seeker.password.reset)
//           POST     /sign-in/reset            (seeker.password.store)
//           GET/POST /join/verify              (seeker.verify / .verify.store)
//           POST     /join/verify/resend       (seeker.verify.resend)
//
//  Completely separate from the staff sign-in (/login): the 'seeker'
//  guard and the job_seekers table. A staff email and password never
//  work here, and a job seeker's never work there.
//  Five wrong passwords in a row: wait a minute (per email and address).
// ══════════════════════════════════════════════════════════════════

class SeekerAuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Public/SignIn', ['status' => session('status')]);
    }

    public function store(Request $request, SeekerEmailCode $codes): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email'], 'password' => ['required', 'string']]);
        $key = 'seeker-login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        if (! Auth::guard('seeker')->attempt(['email' => Str::lower($data['email']), 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        /** @var JobSeeker $seeker */
        $seeker = Auth::guard('seeker')->user();
        $seeker->forceFill(['last_login_at' => now()])->save();
        if (! $seeker->hasVerifiedEmail()) {
            $this->trySend($codes, $seeker);

            return redirect()->route('seeker.verify');
        }

        return redirect()->intended(route('seeker.profile'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('seeker')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    // ── The email code ───────────────────────────────────────────────

    public function verifyShow(Request $request): Response|RedirectResponse
    {
        $seeker = $request->user('seeker');
        if ($seeker->hasVerifiedEmail()) {
            return redirect()->route('seeker.profile');
        }

        return Inertia::render('Public/Verify', ['email' => $seeker->email]);
    }

    public function verifyStore(Request $request, SeekerEmailCode $codes): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{4,8}$/']], ['code.regex' => __('auth.verification_code_invalid')]);
        $codes->verify($request->user('seeker'), $data['code']);

        return redirect()->route('seeker.profile')->with('success', __('pool.welcome'));
    }

    public function verifyResend(Request $request, SeekerEmailCode $codes): RedirectResponse
    {
        $sent = $this->trySend($codes, $request->user('seeker'));

        return back()->with($sent ? 'success' : 'warning', __($sent ? 'pool.code_sent' : 'pool.code_wait'));
    }

    private function trySend(SeekerEmailCode $codes, JobSeeker $seeker): bool
    {
        try {
            return $codes->send($seeker);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    // ── Forgot your password? ────────────────────────────────────────

    public function forgot(): Response
    {
        return Inertia::render('Public/Forgot', ['status' => session('status')]);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker('seekers')->sendResetLink(['email' => Str::lower($request->input('email'))]);

        // The same answer whether or not the email has an account: nobody can use this page to find out who registered.
        return back()->with('status', __('pool.reset_sent'));
    }

    public function resetShow(Request $request, string $token): Response
    {
        return Inertia::render('Public/Reset', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRules::defaults()],
        ]);

        $status = Password::broker('seekers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (JobSeeker $seeker) use ($request) {
                $seeker->forceFill(['password' => $request->input('password'), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($seeker));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status === Password::RESET_THROTTLED ? 'pool.reset_wait' : 'pool.reset_invalid')]);
        }

        return redirect()->route('seeker.login')->with('status', __('auth.password_reset_done'));
    }
}
