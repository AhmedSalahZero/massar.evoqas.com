<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — the public home page (Step 10)
//  Location: app/Http/Controllers/Public/HomeController.php
//  Route: GET / (home)
//
//  Visitors see the public site: what Massar is, the two ways to
//  register, how it works, their data, questions. A small "Staff
//  sign-in" link at the bottom leads to the partners' sign-in (/login).
//  Signed-in STAFF are sent to their workspace, as before.
// ══════════════════════════════════════════════════════════════════

class HomeController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($user = $request->user()) {
            if (! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            return $user->isSuperAdmin() ? redirect()->route('admin.dashboard') : redirect()->route('app.dashboard');
        }

        return Inertia::render('Public/Home');
    }
}
