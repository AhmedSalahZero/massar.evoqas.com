<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\EligibilityAssessment;
use App\Models\OpportunityMatch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\DashboardController (Partner workspace home)
//  Location: app/Http/Controllers/App/DashboardController.php
//
//  /app/dashboard?range=m1|m3|m12|all — Step 15: the partner dashboard,
//  exactly as the agreed demo (docs/dashboard-demo.html). The numbers are
//  worked out by App\Services\Dashboard\PartnerDashboard ('dash').
//  'pipeline' (all time) stays for the shortcuts that read it.
// ══════════════════════════════════════════════════════════════════

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user    = $request->user();
        $company = $user->company;
        $range   = in_array($request->query('range'), \App\Services\Dashboard\PartnerDashboard::RANGES, true) ? $request->query('range') : 'm12';

        return Inertia::render('App/Dashboard', [
            'team' => [
                'members'    => $company->users()->count(),
                'active'     => $company->users()->where('is_active', true)->count(),
                'seat_limit' => $company->seat_limit,
            ],
            // Only this partner's own beneficiaries (Beneficiary::inWorkspace).
            'beneficiaries' => $user->can('beneficiaries.view') ? [
                'total'      => Beneficiary::query()->inWorkspace($company->id)->count(),
                'this_month' => Beneficiary::query()->inWorkspace($company->id)->where('created_at', '>=', now()->startOfMonth())->count(),
            ] : null,
            // The placement pipeline (Registered → Assessed → Matched → Placed).
            // Step 13: Matched = referred at least once; Placed = hired, or completed a training.
            'pipeline' => $user->can('beneficiaries.view') ? [
                'registered' => Beneficiary::query()->inWorkspace($company->id)->count(),
                'assessed'   => EligibilityAssessment::query()->inWorkspace($company->id)->distinct()->count('beneficiary_id'),
                'eligible'   => EligibilityAssessment::query()->inWorkspace($company->id)->where('result', 'eligible')->distinct()->count('beneficiary_id'),
                'matched'    => OpportunityMatch::query()->inWorkspace($company->id)->distinct()->count('beneficiary_id'),
                'active'     => OpportunityMatch::query()->inWorkspace($company->id)->active()->where('stage', '!=', OpportunityMatch::DONE)->count(),
                'placed'     => OpportunityMatch::query()->inWorkspace($company->id)->placed()->distinct()->count('beneficiary_id'),
                'follow'     => $user->can('matches.view') ? OpportunityMatch::query()->inWorkspace($company->id)->needsFollowUp()->count() : 0,
            ] : null,
            'subscription' => [
                'ends_at'   => $company->subscription_ends_at?->toDateString(),
                'days_left' => $company->daysUntilExpiry(),
            ],
            // Step 15: the dashboard itself, exactly as the agreed demo, for the chosen date range.
            'range' => $range,
            'dash'  => app(\App\Services\Dashboard\PartnerDashboard::class)->build($user, $range),
        ]);
    }
}
