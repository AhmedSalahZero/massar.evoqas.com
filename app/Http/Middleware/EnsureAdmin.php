<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — EnsureAdmin Middleware  (alias: 'admin')
//  Location: app/Http/Middleware/EnsureAdmin.php
//
//  Protects every /admin/* route — the Massar platform area. Only a
//  super_admin (the Massar team who onboards partner organisations)
//  may enter. Applied AFTER 'auth'.
//
//  Not a super_admin → 403. There is no separate admin login:
//  everyone signs in on the same page and is routed by role.
// ══════════════════════════════════════════════════════════════════

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! auth()->user()->isSuperAdmin()) {
            abort(403, __('errors.forbidden'));
        }

        return $next($request);
    }
}
