<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

// ══════════════════════════════════════════════════════════════════
//  Massar — PreferenceController
//  Location: app/Http/Controllers/App/PreferenceController.php
//
//  Saves the three top-bar switches for the signed-in user, so they
//  follow them to any device:
//    theme()    → dark | light
//    locale()   → en | ar            (also flips the page to RTL)
//    standard() → enoc | isco | esco (the occupation standard switch,
//                 Scope v2 §1 "Three-Standard Switch")
//
//  The frontend applies the change instantly and calls these in the
//  background (preserveState), so there is no page reload. Each call
//  simply sets a value, so repeating it is harmless — which is why
//  these routes sit outside the 'no-duplicate' guard.
// ══════════════════════════════════════════════════════════════════

class PreferenceController extends Controller
{
    public function theme(Request $request): RedirectResponse
    {
        $request->validate(['theme' => ['required', 'in:dark,light']]);
        $request->user()->update(['theme' => $request->input('theme')]);

        return back();
    }

    public function locale(Request $request): RedirectResponse
    {
        $request->validate(['locale' => ['required', 'in:en,ar']]);
        $request->user()->update(['language' => $request->input('locale')]);
        $request->session()->put('locale', $request->input('locale'));

        return back();
    }

    /**
     * Saved by a background request from the Standard switch (not an
     * Inertia visit), so it answers "done" with no page — see
     * usePreferences.setStandard(). A normal form post still goes back.
     */
    public function standard(Request $request): RedirectResponse|Response
    {
        $request->validate(['standard' => ['required', Rule::in(User::STANDARDS)]]);
        $request->user()->update(['occupation_standard' => $request->input('standard')]);

        return $request->expectsJson() || ($request->ajax() && ! $request->header('X-Inertia'))
            ? response()->noContent()
            : back();
    }
}
