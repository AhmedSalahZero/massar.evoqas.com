<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — Error Strings (English)
//  Location: lang/en/errors.php
//
//  Shown directly to the person, so each one says what happened and
//  what to do next. Keep key-for-key in sync with lang/ar/errors.php.
// ══════════════════════════════════════════════════════════════════

return [
    // ── Access (User::accessDenialReason) ──────────────────────────
    'account_suspended'    => 'This account has been deactivated. Please contact your organisation\'s administrator.',
    'account_orphaned'     => 'This account is no longer linked to a partner organisation. Please contact Massar support.',
    'company_suspended'    => 'Your organisation\'s account is not active. Please contact Massar support to reactivate it.',
    'subscription_expired' => 'Your organisation\'s subscription has ended. Please contact Massar to renew access.',
    'forbidden'            => 'You do not have permission to access this page.',

    // ── Forms ──────────────────────────────────────────────────────
    'duplicate_submission' => 'That looks like the same entry you just saved, so it was not recorded again. If you really meant to submit it twice, try again in a moment.',

    // ── Team ───────────────────────────────────────────────────────
    'no_free_seat'           => 'All :limit seats are in use. Deactivate someone, or ask Massar to add seats to your subscription.',
    'cannot_demote_self'     => 'You cannot remove your own administrator role. Ask another administrator to do it.',
    'cannot_deactivate_self' => 'You cannot deactivate your own account.',
];
