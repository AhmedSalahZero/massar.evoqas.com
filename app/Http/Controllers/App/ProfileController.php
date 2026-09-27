<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\UpdatePasswordRequest;
use App\Http\Requests\App\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — ProfileController (own account)
//  Location: app/Http/Controllers/App/ProfileController.php
//
//  Serves BOTH areas — /app/profile (partner staff) and
//  /admin/profile (super admin) — with the same page; the page picks
//  its layout from the user's role.
//
//    index()          → name, email, phone, job title, preferences
//    update()         → save those details
//    updatePassword() → change password, then sign out every OTHER
//                       device (Auth::logoutOtherDevices + the
//                       'auth.session' middleware), because changing
//                       the password is what someone does when they
//                       think their account is compromised.
// ══════════════════════════════════════════════════════════════════

class ProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Index', [
            'profile' => $user->only(['name', 'email', 'phone', 'job_title', 'language', 'theme', 'occupation_standard']),
            'updateRoute'   => $user->isSuperAdmin() ? 'admin.profile.update' : 'app.profile.update',
            'passwordRoute' => $user->isSuperAdmin() ? 'admin.profile.password' : 'app.profile.password',
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', __('common.saved'));
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $password = $request->validated('password');

        $request->user()->update(['password' => $password]);

        Auth::logoutOtherDevices($password);

        return back()->with('success', __('profile.password_saved'));
    }
}
