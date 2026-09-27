<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — Admin\CompanyController (Partner Organisations)
//  Location: app/Http/Controllers/Admin/CompanyController.php
//
//  Super Admin screen: /admin/companies  (permission: platform.companies)
//
//    index()        → list partners with seats used / limit and
//                     subscription status; search + status filter
//    store()        → onboard a partner AND its first Company Admin in
//                     one transaction (Scope v2 §4 "Company Onboarding").
//                     There is no self-service sign-up, so this is the
//                     only way a partner comes into existence.
//    update()       → edit details, seat limit, subscription end date
//    toggleActive() → suspend / reactivate — everyone in the partner is
//                     locked out on their next click (EnsureMember)
//    destroy()      → permanent delete of the partner and its users.
//                     A snapshot is written to the log first, because
//                     partner data is personal data about beneficiaries.
//                     (As modules are built, their tables cascade on
//                     companies.id or are cleared here.)
// ══════════════════════════════════════════════════════════════════

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q'      => trim((string) $request->query('q', '')),
            'status' => in_array($request->query('status'), ['active', 'suspended', 'expiring'], true)
                ? $request->query('status') : '',
        ];

        $companies = Company::query()
            ->withCount('users')
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('name_ar', 'like', $term)
                    ->orWhere('contact_email', 'like', $term));
            })
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'suspended', fn ($q) => $q->where('is_active', false))
            ->when($filters['status'] === 'expiring', fn ($q) => $q
                ->whereNotNull('subscription_ends_at')
                ->whereBetween('subscription_ends_at', [now(), now()->addDays((int) config('subscription.notify_days_before', 14))]))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Company $c) => [
                'id'                   => $c->id,
                'name'                 => $c->name,
                'name_ar'              => $c->name_ar,
                'type'                 => $c->type,
                'governorate'          => $c->governorate,
                'contact_email'        => $c->contact_email,
                'contact_phone'        => $c->contact_phone,
                'is_active'            => $c->is_active,
                'seat_limit'           => $c->seat_limit,
                'users_count'          => $c->users_count,
                'subscription_ends_at' => $c->subscription_ends_at?->toDateString(),
                'days_left'            => $c->daysUntilExpiry(),
                'expiring_soon'        => $c->isExpiringSoon(),
                'lapsed'               => $c->hasLapsed(),
                'created_at'           => $c->created_at?->toDateString(),
            ]);

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'filters'   => $filters,
            'types'     => Company::TYPES,
            'defaults'  => [
                'seat_limit' => (int) config('subscription.default_seats', 5),
                'months'     => (int) config('subscription.initial_months', 12),
            ],
        ]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $company = Company::create([
                'name'                 => $data['name'],
                'name_ar'              => $data['name_ar'] ?? null,
                'type'                 => $data['type'],
                'governorate'          => $data['governorate'] ?? null,
                'contact_email'        => $data['contact_email'] ?? null,
                'contact_phone'        => $data['contact_phone'] ?? null,
                'seat_limit'           => $data['seat_limit'],
                'subscription_ends_at' => $data['subscription_ends_at'] ?? now()->addMonthsNoOverflow((int) config('subscription.initial_months', 12)),
                'created_by'           => auth()->id(),
            ]);

            User::create([
                'name'       => $data['admin_name'],
                'email'      => $data['admin_email'],
                'password'   => $data['admin_password'],
                'role'       => UserRole::CompanyAdmin->value,
                'job_title'  => $data['admin_job_title'] ?? null,
                'company_id' => $company->id,
                'created_by' => auth()->id(),
                'language'   => $data['admin_language'] ?? 'ar',
            ]);
        });

        return back()->with('success', __('common.partner_created'));
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return back()->with('success', __('common.saved'));
    }

    public function toggleActive(Company $company): RedirectResponse
    {
        $company->update(['is_active' => ! $company->is_active]);

        return back()->with('success', $company->is_active ? __('common.partner_reactivated') : __('common.partner_suspended'));
    }

    public function destroy(Company $company): RedirectResponse
    {
        $snapshot = [
            'company_id'    => $company->id,
            'company_name'  => $company->name,
            'users_count'   => $company->users()->count(),
            'created_at'    => $company->created_at?->toDateTimeString(),
            'deleted_by_id' => auth()->id(),
            'deleted_by'    => auth()->user()?->email,
            'deleted_at'    => now()->toDateTimeString(),
        ];

        $companyId = $company->id;
        DB::transaction(function () use ($company) {
            $company->users()->delete();
            $company->delete();
        });
        // The partner's original CV files (the database rows went with the partner).
        app(\App\Services\Cv\CvStorage::class)->deleteWorkspace($companyId);

        Log::warning('admin.company_permanently_deleted', $snapshot);

        return redirect()
            ->route('admin.companies.index')
            ->with('success', __('common.partner_deleted', ['name' => $snapshot['company_name']]));
    }
}
