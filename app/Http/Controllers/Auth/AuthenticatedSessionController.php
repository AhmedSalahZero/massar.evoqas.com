<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — Auth\AuthenticatedSessionController
//  Location: app/Http/Controllers/Auth/AuthenticatedSessionController.php
//
//  Login screen, sign-in and sign-out. After sign-in a super_admin
//  goes to /admin, partner staff to /app. All the checks (password,
//  verification, account/partner status) live in LoginRequest.
// ══════════════════════════════════════════════════════════════════

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return redirect()->intended(route('app.dashboard', absolute: false));
    }


    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Full page load of the sign-in screen (not an in-app screen
        // swap), so nothing from the signed-in session stays on screen.
        // For normal browser requests Inertia::location() is a plain redirect.
        return Inertia::location(route('login'));
    }
}