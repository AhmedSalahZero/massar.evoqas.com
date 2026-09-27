<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — EnsureMember Middleware  (alias: 'member')
//  Location: app/Http/Middleware/EnsureMember.php
//
//  Protects every /app/* route — a partner organisation's workspace.
//  "Member" = partner staff: company_admin or employee. Applied
//  AFTER 'auth' and 'verified'.
//
//  Checks, on EVERY request (not only at login), via
//  User::accessDenialReason():
//    1. the account is active,
//    2. the partner exists, is active, and its subscription has not
//       lapsed — so a partner suspended mid-session is locked out on
//       their next click, not at their next login.
//  A super_admin has no workspace and is sent to /admin instead.
// ══════════════════════════════════════════════════════════════════

class EnsureMember
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Checked on every request, not just at login, so revoking a
        // user or deactivating their company takes effect immediately
        // for anyone already holding a session. Same rule as
        // LoginRequest — see User::accessDenialReason().
        if ($denialReason = $user->accessDenialReason()) {
            auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors([
                    'email' => __($denialReason),
                ]);
        }

        // Super admins manage companies from /admin, not /app.
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // A company_admin/employee with no company_id is a broken
        // account state (shouldn't normally happen) — fail safe.
        if (! $user->company_id) {
            abort(403, __('errors.forbidden'));
        }

        return $next($request);
    }
}
