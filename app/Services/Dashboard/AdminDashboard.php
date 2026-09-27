<?php

namespace App\Services\Dashboard;

use App\Models\Company;
use App\Models\CvDocument;
use App\Models\LearnedRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — AdminDashboard (Step 15 · the Super Admin's dashboard)
//  Location: app/Services/Dashboard/AdminDashboard.php
//  The agreed demo: docs/dashboard-demo.html ("Massar platform" tab)
//
//  Every partner together, COUNTS ONLY: never a person, a CV or a result
//  (Scope v2 §5). The public pool's own workspace is not a partner; its
//  job seekers are counted in their own card.
//
//    kpis      partners (active, ending within 30 days) · team members
//              (signed in today) · beneficiaries (+ new) · CVs (% read
//              automatically) · placed (hired · completed)
//    growth    new people by month, all partners
//    backbone  ENOC · ISCO-08 · ESCO occupations and skills
//    pool      job seekers registered · added by partners
//    rules     rule requests waiting
//    partners  one line per partner: type, seats, people, CVs, placed,
//              subscription end
// ══════════════════════════════════════════════════════════════════

class AdminDashboard
{
    public function build(string $range): array
    {
        [$from] = PartnerDashboard::window($range);
        $partners = Company::query()->orderBy('name')->get(['id', 'name', 'name_ar', 'type', 'is_active', 'seat_limit', 'subscription_ends_at']);
        $ids = $partners->pluck('id')->all();
        $people = fn () => DB::table('beneficiaries')->whereIn('company_id', $ids ?: [0]);
        $months = PartnerDashboard::months($range, ($first = $people()->min('created_at')) ? Carbon::parse($first) : null);

        $newByMonth = array_fill(0, count($months), 0);
        $at = array_flip($months);
        foreach ($people()->where('created_at', '>=', Carbon::parse($months[0].'-01')->startOfMonth())->pluck('created_at') as $d) {
            $k = Carbon::parse($d)->format('Y-m');
            isset($at[$k]) && $newByMonth[$at[$k]]++;
        }
        $cvs = fn () => DB::table('cv_documents')->whereIn('company_id', $ids ?: [0]);
        $uploaded = $cvs()->where('status', '!=', CvDocument::REJECTED)->when($from, fn ($q) => $q->where('created_at', '>=', $from));
        $uploadedN = (clone $uploaded)->count();
        $placed = DB::table('opportunity_matches')->whereIn('company_id', $ids ?: [0])->where('status', 'active')->where('stage', 'done')
            ->when($from, fn ($q) => $q->where('stage_on', '>=', $from));

        $perPartner = fn ($q, $col = 'company_id') => $q->selectRaw("$col as k, count(*) as n")->groupBy($col)->pluck('n', 'k');
        $pPeople = $perPartner($people());
        $pCvs = $perPartner($cvs()->whereIn('status', CvDocument::ON_PROFILE));
        $pPlaced = $perPartner(DB::table('opportunity_matches')->whereIn('company_id', $ids ?: [0])->where('status', 'active')->where('stage', 'done'));
        $pUsers = $perPartner(DB::table('users')->whereIn('company_id', $ids ?: [0])->where('is_active', true));

        return [
            'range'  => $range,
            'months' => $months,
            'kpis'   => [
                'partners'  => $partners->count(),
                'active'    => $partners->where('is_active', true)->count(),
                'ending'    => $partners->filter(fn ($c) => $c->subscription_ends_at && $c->subscription_ends_at->between(now(), now()->addDays(30)))->count(),
                'users'     => DB::table('users')->whereIn('company_id', $ids ?: [0])->count(),
                'today'     => DB::table('user_login_activities')->where('activity_date', now()->toDateString())->distinct()->count('user_id'),
                'people'    => $people()->count(),
                'people_new' => $from ? $people()->where('created_at', '>=', $from)->count() : $people()->count(),
                'cvs'       => $cvs()->whereIn('status', CvDocument::ON_PROFILE)->count(),
                'cvs_auto'  => $uploadedN ? (int) round((clone $uploaded)->where('status', CvDocument::ADDED)->count() / $uploadedN * 100) : null,
                'hired'     => (clone $placed)->where('kind', 'job')->count(),
                'completed' => (clone $placed)->where('kind', 'training')->count(),
            ],
            'growth'   => $newByMonth,
            'backbone' => [
                'enoc'   => DB::table('enoc_occupations')->count(),
                'isco'   => DB::table('isco_groups')->count(),
                'esco'   => DB::table('esco_occupations')->count(),
                'skills' => DB::table('esco_skills')->where('is_active', true)->count(),
            ],
            'pool' => [
                'seekers' => DB::table('job_seekers')->whereNotNull('email_verified_at')->count(),
                'added'   => DB::table('pool_additions')->count(),
            ],
            'rules' => LearnedRule::query()->whereNotNull('company_id')->where('proposal_status', LearnedRule::PENDING)->count(),
            'partners' => $partners->map(fn (Company $c) => [
                'id' => $c->id, 'name' => $c->name, 'name_ar' => $c->name_ar, 'type' => $c->type, 'is_active' => (bool) $c->is_active,
                'seats' => (int) $c->seat_limit, 'users' => (int) ($pUsers[$c->id] ?? 0),
                'people' => (int) ($pPeople[$c->id] ?? 0), 'cvs' => (int) ($pCvs[$c->id] ?? 0), 'placed' => (int) ($pPlaced[$c->id] ?? 0),
                'ends_at' => $c->subscription_ends_at?->toDateString(),
                'ending' => (bool) ($c->subscription_ends_at && $c->subscription_ends_at->between(now(), now()->addDays(30))),
            ])->values()->all(),
        ];
    }
}
