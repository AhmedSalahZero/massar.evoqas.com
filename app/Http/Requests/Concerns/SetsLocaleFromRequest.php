<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Facades\App;

// ══════════════════════════════════════════════════════════════════
//  Massar — SetsLocaleFromRequest
//  Location: app/Http/Requests/Concerns/SetsLocaleFromRequest.php
//
//  Used by the guest auth form requests (login, forgot password,
//  verification code). A guest has no user row to read a language
//  from, so the page sends the language it is showing as `locale`.
//  Applying it BEFORE validation means error messages come back in
//  the language the person is reading — an Arabic login page never
//  answers with an English "These credentials do not match".
//
//  Call $this->applyRequestLocale() from prepareForValidation(), or
//  pass an explicit locale (e.g. the user's saved language once we
//  know who they are).
// ══════════════════════════════════════════════════════════════════

trait SetsLocaleFromRequest
{
    protected function applyRequestLocale(?string $locale = null): void
    {
        $locale ??= $this->input('locale') ?: $this->session()?->get('locale');

        if (! in_array($locale, ['en', 'ar'], true)) {
            return;
        }

        App::setLocale($locale);
        $this->session()?->put('locale', $locale);
    }
}
