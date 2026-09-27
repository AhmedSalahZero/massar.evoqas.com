<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — Subscription & Seats
//  Location: config/subscription.php
//
//  Partner organisations are onboarded by the Super Admin (there is
//  no self-service billing — renewal is handled by a person). These
//  values drive App\Models\Company and the daily reminder command
//  `subscriptions:notify-expiring` (routes/console.php).
// ══════════════════════════════════════════════════════════════════

return [

    // Length of the first subscription window for a new partner.
    'initial_months' => (int) env('SUBSCRIPTION_INITIAL_MONTHS', 12),

    // Seats (user accounts, company admin included) a new partner gets
    // unless the Super Admin sets another number.
    'default_seats' => (int) env('SUBSCRIPTION_DEFAULT_SEATS', 5),

    // Days before expiry the company admin is emailed and the in-app
    // banner starts showing.
    'notify_days_before' => (int) env('SUBSCRIPTION_NOTIFY_DAYS_BEFORE', 14),

    // Minimum days between two reminder emails to the same partner.
    'notify_again_after_days' => (int) env('SUBSCRIPTION_NOTIFY_AGAIN_AFTER_DAYS', 3),

    // Who a partner contacts to renew. Shown on the expiry banner.
    'support_email' => env('SUPPORT_EMAIL'),
    'support_phone' => env('SUPPORT_WHATSAPP'),

];
