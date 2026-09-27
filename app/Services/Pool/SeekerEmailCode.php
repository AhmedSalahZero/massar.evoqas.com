<?php

namespace App\Services\Pool;

use App\Models\JobSeeker;
use App\Notifications\VerifyEmailCodeNotification;
use App\Support\AuthVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

// ══════════════════════════════════════════════════════════════════
//  Massar — SeekerEmailCode (Step 10)
//  Location: app/Services/Pool/SeekerEmailCode.php
//
//  The 6-digit code that confirms a job seeker's email, the same way
//  staff accounts are confirmed (config/auth_verification.php: length,
//  minutes, attempts, resend wait; the same email). Only the hash of
//  the code is kept. A profile is shown to partners only after its
//  email is confirmed.
//  With AUTH_EMAIL_VERIFICATION_ENABLED=false (local testing) the
//  email is confirmed at once and nothing is sent.
// ══════════════════════════════════════════════════════════════════

class SeekerEmailCode
{
    /** Send a new code. Returns false if one was sent less than a minute ago. */
    public function send(JobSeeker $seeker, bool $force = false): bool
    {
        if (! AuthVerification::enabled()) {
            $seeker->forceFill(['email_verified_at' => now()])->save();

            return true;
        }
        $key = 'seeker-code:'.$seeker->id;
        if (! $force && RateLimiter::tooManyAttempts($key, 1)) {
            return false;
        }
        RateLimiter::hit($key, (int) config('auth_verification.resend_throttle_seconds', 60));

        $length = (int) config('auth_verification.code_length', 6);
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
        $seeker->forceFill([
            'code_hash'       => Hash::make($code),
            'code_expires_at' => now()->addMinutes((int) config('auth_verification.expires_minutes', 15)),
            'code_attempts'   => 0,
        ])->save();

        $seeker->notifyNow(new VerifyEmailCodeNotification($code));

        return true;
    }

    public function verify(JobSeeker $seeker, string $code): void
    {
        $fail = fn (string $key) => throw ValidationException::withMessages(['code' => [__($key)]]);

        if (! $seeker->code_hash) {
            $fail('auth.verification_code_invalid');
        }
        if ($seeker->code_expires_at?->isPast()) {
            $fail('auth.verification_code_expired');
        }
        if ($seeker->code_attempts >= (int) config('auth_verification.max_attempts', 5)) {
            $fail('auth.verification_code_locked');
        }
        if (! Hash::check($code, $seeker->code_hash)) {
            $seeker->increment('code_attempts');
            $fail('auth.verification_code_invalid');
        }

        $seeker->forceFill(['email_verified_at' => now(), 'code_hash' => null, 'code_expires_at' => null, 'code_attempts' => 0])->save();
    }
}
