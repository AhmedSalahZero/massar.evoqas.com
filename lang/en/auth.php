<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — Auth Strings (English)
//  Location: lang/en/auth.php
//
//  Server-side messages for sign-in, password reset and email codes
//  (LoginRequest, the Auth controllers, EmailVerificationService).
//  Also shared to the auth pages as props.translations.auth.
//  Keep key-for-key in sync with lang/ar/auth.php.
// ══════════════════════════════════════════════════════════════════

return [
    // ── Sign-in ────────────────────────────────────────────────────
    'failed'            => 'These credentials do not match our records.',
    'password'          => 'The provided password is incorrect.',
    'throttle'          => 'Too many sign-in attempts. Please try again in :seconds seconds.',
    'throttle_requests' => 'Too many attempts. Please wait :seconds seconds and try again.',

    // ── Password reset ─────────────────────────────────────────────
    'reset_link_sent'       => 'We have sent a password reset link to your email.',
    'password_reset_done'   => 'Your password has been reset successfully.',
    'reset_email_not_found' => 'We could not find an account with that email address.',

    // ── Verification codes (OTP) ───────────────────────────────────
    'verification_code_invalid'     => 'That code is not correct. Please check it and try again.',
    'verification_code_expired'     => 'That code has expired. Request a new one to continue.',
    'verification_code_locked'      => 'Too many incorrect attempts. Request a new code to continue.',
    'verification_already_verified' => 'This email address is already verified. You can sign in normally.',
    'verification_email_not_found'  => 'We could not find an account awaiting verification for that email address.',
];
