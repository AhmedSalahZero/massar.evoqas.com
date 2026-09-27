<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailCodeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

// ══════════════════════════════════════════════════════════════════
//  Massar — Auth Routes
//  Location: routes/auth.php
//
//  Sign-in and account security only. There is deliberately NO
//  public registration here: partner organisations are onboarded by
//  the Super Admin, and partner staff are added by their Company
//  Admin (Scope v2 §4). Beneficiary self-registration belongs to the
//  public portal (Module A) and will get its own routes.
//
//    locale            → guest language switch (stored in session)
//    login / logout
//    forgot-password / reset-password/{token}
//    verify-email      → 6-digit code by email (when enabled in
//                        config/auth_verification.php)
//    confirm-password  → re-enter password before a sensitive action
//
//  Every POST a guest can repeat is throttled; a throttled request
//  comes back as a visible form error (see bootstrap/app.php).
// ══════════════════════════════════════════════════════════════════

Route::middleware('guest')->group(function () {
    Route::post('locale', function (Request $request) {
        $request->validate(['locale' => ['required', 'string', 'in:en,ar']]);
        Session::put('locale', $request->locale);

        return back();
    })->name('guest.locale');

    // throttle:tries,minutes,NAME — the name gives each route its own count.
    // Without it Laravel keeps ONE count per IP address for every throttled
    // route, so a few sign-ins from an office or a mobile network (many
    // people, one address) used up the password reset's allowance.
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1,login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1,password-email')->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1,password-reset')->name('password.store');
});

// Email verification by code — open to guests too, because a person
// who has not verified yet is signed out and sent here from login.
Route::get('verify-email', [VerifyEmailCodeController::class, 'create'])->name('verification.notice');
Route::post('verify-email', [VerifyEmailCodeController::class, 'store'])
    ->middleware('throttle:10,1,verify-code')->name('verification.verify-code');
Route::post('verify-email/resend', [VerifyEmailCodeController::class, 'resend'])
    ->middleware('throttle:6,1,verify-resend')->name('verification.resend');

Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
