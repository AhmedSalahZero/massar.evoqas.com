<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — a signed-in job seeker is needed (Step 10). Alias: seeker.auth
//  Location: app/Http/Middleware/AuthenticateSeeker.php
//
//  Used instead of Laravel's `auth:seeker` on purpose: that one also
//  makes 'seeker' the request's default sign-in, and the rest of the
//  app reads "the signed-in person" as STAFF (workspace filters, the
//  language, the daily sign-in record). This keeps staff and job
//  seekers apart: job seekers' pages always ask for them by name
//  ($request->user('seeker')).
// ══════════════════════════════════════════════════════════════════

class AuthenticateSeeker
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('seeker')->check()) {
            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->guest(route('seeker.login'));
        }

        return $next($request);
    }
}
