<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — Email Strings (English)
//  Location: lang/en/emails.php
//
//  Every key here is referenced from resources/views/emails/*.blade.php
//  or from app/Notifications/*.php. A missing key is not a silent
//  failure — Laravel renders the raw key ("emails.verify_code.heading")
//  straight into the delivered email, so keep this file in sync with
//  lang/ar/emails.php key-for-key.
// ══════════════════════════════════════════════════════════════════

return [

    // ── Email verification code ───────────────────────────────────
    'verify_code' => [
        'subject'     => 'Your Massar verification code',
        'heading'     => 'Confirm your email address',
        'greeting'    => 'Hi :name,',
        'intro'       => 'Use the code below to finish setting up your Massar account.',
        'expire'      => 'This code expires in :count minutes.',
        'instruction' => 'Enter it on the verification screen to activate your account.',
        'ignore'      => "If you were not expecting a Massar account, you can safely ignore this email.",
        'closing'     => 'Welcome aboard.',
    ],

    // ── Password reset ────────────────────────────────────────────
    'reset_password' => [
        'subject'  => 'Reset your Massar password',
        'heading'  => 'Reset your password',
        'greeting' => 'Hi :name,',
        'intro'    => 'We received a request to reset the password for your Massar account.',
        'button'   => 'Reset password',
        'expire'   => 'This link expires in :count minutes.',
        'fallback' => "If the button doesn't work, copy and paste this link into your browser:",
        'ignore'   => 'If you did not request a password reset, no action is needed — your password stays unchanged.',
        'closing'  => 'Thanks.',
    ],

    // ── Subscription ending ───────────────────────────────────────
    'subscription_ending' => [
        'subject'       => 'Your Massar subscription ends in :days days',
        'heading'       => 'Your subscription is ending soon',
        'greeting'      => 'Hi :name,',
        'intro'         => 'The Massar subscription for :company ends in :days days.',
        'ends_on_label' => 'Access stops on this date',
        'what_happens'  => 'After that date nobody on your team will be able to sign in, but none of your records are deleted — everything is waiting for you the moment the subscription is renewed.',
        'how_to_renew'  => 'To keep working without a break, contact the Massar team before that date to renew.',
        'closing'       => 'Thank you for working with Massar.',
    ],

    // ── Shared layout ─────────────────────────────────────────────
    'footer_tagline' => 'Connecting people with training and jobs across Egypt.',

];
