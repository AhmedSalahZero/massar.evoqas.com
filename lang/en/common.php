<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — Common Strings (English)
//  Location: lang/en/common.php
//
//  Server-side flash messages (->with('success', __('common.saved'))).
//  Interface labels live in the frontend (resources/js/lang/
//  translations.js); this file is only for what PHP says.
//  Keep key-for-key in sync with lang/ar/common.php.
// ══════════════════════════════════════════════════════════════════

return [
    'saved'   => 'Changes saved.',
    'deleted' => 'Deleted.',

    // ── Partners (Super Admin) ─────────────────────────────────────
    'partner_created'     => 'Partner organisation created, with its first administrator.',
    'partner_reactivated' => 'Partner organisation reactivated.',
    'partner_suspended'   => 'Partner organisation suspended. Its team can no longer sign in.',
    'partner_deleted'     => '":name" and all its data were permanently deleted.',

    // ── Labour market editions (Super Admin) ───────────────────────
    'edition_in_use'     => 'Edition #:id is now shown everywhere.',
    'edition_not_usable' => 'Edition #:id did not import completely and cannot be used.',

    // ── Team (Company Admin) ───────────────────────────────────────
    'member_added'       => 'Team member added.',
    'member_reactivated' => 'Team member reactivated.',
    'member_deactivated' => 'Team member deactivated.',
];
