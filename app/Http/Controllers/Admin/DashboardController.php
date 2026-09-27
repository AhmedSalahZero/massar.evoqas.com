<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — Admin\DashboardController
//  Location: app/Http/Controllers/Admin/DashboardController.php
//
//  Super Admin home: /admin/dashboard. Platform-wide AGGREGATES only
//  — never individual beneficiary records (Scope v2 §4/§6 "Scoped
//  visibility"). Partner and user counts, partners whose
//  subscription is ending, and today's active users.
//
//  Beneficiary, CV and rule-request figures are added here as those
//  modules are built.
// ══════════════════════════════════════════════════════════════════

class DashboardController extends Controller
{
    public function index(\Illuminate\Http\Request $request): Response
    {
        $range = in_array($request->query('range'), \App\Services\Dashboard\PartnerDashboard::RANGES, true) ? $request->query('range') : 'm12';
        $window = (int) config('subscription.notify_days_before', 14);

        $expiring = Company::query()
            ->where('is_active', true)
            ->whereNotNull('subscription_ends_at')
            ->whereBetween('subscription_ends_at', [now(), now()->addDays($window)])
            ->orderBy('subscription_ends_at')
            ->limit(6)
            ->get(['id', 'name', 'name_ar', 'subscription_ends_at'])
            ->map(fn (Company $c) => [
                'id'        => $c->id,
                'name'      => $c->displayName(),
                'ends_at'   => $c->subscription_ends_at?->toDateString(),
                'days_left' => $c->daysUntilExpiry(),
            ]);

        $recent = Company::query()
            ->withCount('users')
            ->latest('id')
            ->limit(6)
            ->get(['id', 'name', 'name_ar', 'type', 'is_active', 'seat_limit', 'created_at'])
            ->map(fn (Company $c) => [
                'id'          => $c->id,
                'name'        => $c->displayName(),
                'type'        => $c->type,
                'is_active'   => $c->is_active,
                'users_count' => $c->users_count,
                'seat_limit'  => $c->seat_limit,
                'created_at'  => $c->created_at?->toDateString(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'companies'        => Company::query()->count(),
                'active_companies' => Company::query()->where('is_active', true)->count(),
                'users'            => User::query()->whereNotNull('company_id')->count(),
                'active_today'     => DB::table('user_login_activities')
                    ->where('activity_date', now()->toDateString())
                    ->distinct()->count('user_id'),
                // Step 11: counts only, across partners — never a person or a result.
                // Step 12: open jobs and trainings, and their seats.
                'eligibility' => [
                    'jobs'     => DB::table('opportunities')->where('kind', 'job')->where('status', 'open')->count(),
                    'trainings' => DB::table('opportunities')->where('kind', 'training')->where('status', 'open')->count(),
                    'seats'    => (int) DB::table('opportunities')->where('status', 'open')->sum('seats'),
                    'assessed' => DB::table('eligibility_assessments')->distinct()->count('beneficiary_id'),
                    'eligible' => DB::table('eligibility_assessments')->where('result', 'eligible')->distinct()->count('beneficiary_id'),
                ],
                // Step 13: counts only — matches, people matched, people placed.
                'matches' => [
                    'matches' => DB::table('opportunity_matches')->count(),
                    'matched' => DB::table('opportunity_matches')->distinct()->count('beneficiary_id'),
                    'placed'  => DB::table('opportunity_matches')->where('status', 'active')->where('stage', 'done')->distinct()->count('beneficiary_id'),
                ],
            ],
            'expiring' => $expiring,
            'recent'   => $recent,
            // Step 15: the Super Admin's dashboard, as the agreed demo (counts only).
            'range'    => $range,
            'dash'     => app(\App\Services\Dashboard\AdminDashboard::class)->build($range),
        ]);
    }
}
