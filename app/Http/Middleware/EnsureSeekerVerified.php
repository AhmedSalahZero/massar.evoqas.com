<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — a job seeker must confirm their email (the 6-digit code)
//  before "My profile" opens (Step 10). Alias: seeker.verified
//  Location: app/Http/Middleware/EnsureSeekerVerified.php
// ══════════════════════════════════════════════════════════════════

class EnsureSeekerVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $seeker = $request->user('seeker');
        if ($seeker && ! $seeker->hasVerifiedEmail()) {
            return redirect()->route('seeker.verify');
        }
        if ($seeker && ! $seeker->beneficiary_id) {
            abort(404);
        }

        return $next($request);
    }
}
