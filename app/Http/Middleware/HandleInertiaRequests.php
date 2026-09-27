<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

// ══════════════════════════════════════════════════════════════════
//  Massar — HandleInertiaRequests
//  Location: app/Http/Middleware/HandleInertiaRequests.php
//
//  Decides what every Vue page receives as shared props
//  (usePage().props in the frontend):
//
//    auth.user      → the signed-in user: identity, role, preferences
//                     (language, theme, occupation standard), their
//                     permission keys, and their partner organisation
//                     with subscription and seat status. null for guests.
//    flash          → success / error / warning / info messages set
//                     with ->with('success', …) on a redirect.
//    locale         → 'en' | 'ar' for this request.
//    translations   → server-side auth strings (validation messages
//                     the auth pages may need to show verbatim).
//    support        → who a partner contacts to renew (banner button).
//    app            → name + environment, for the dev banner.
//
//  Everything user-specific is a closure, so it is only computed when
//  a page actually renders — never for redirects.
// ══════════════════════════════════════════════════════════════════

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'csrf_token' => fn () => csrf_token(),

            'auth' => fn () => ['user' => $this->resolveUser($request->user())],

            // A job seeker signed in on the public site (Step 10) — never a staff account.
            'seeker' => fn () => ($s = $request->user('seeker')) ? [
                'name'     => $s->name,
                'email'    => $s->email,
                'verified' => $s->hasVerifiedEmail(),
            ] : null,

            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info'    => $request->session()->get('info'),
            ],

            'locale' => fn () => app()->getLocale(),

            'translations' => fn () => [
                'auth' => Lang::get('auth'),
            ],

            'support' => [
                'email' => config('subscription.support_email'),
                'phone' => config('subscription.support_phone'),
            ],

            'app' => [
                'name' => config('app.name'),
                'env'  => config('app.env'),
            ],
        ];
    }

    private function resolveUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id'                  => $user->id,
            'name'                => $user->name,
            'initials'            => $user->initials(),
            'email'               => $user->email,
            'role'                => $user->role,
            'role_label'          => $user->roleLabel(),
            'job_title'           => $user->job_title,
            'language'            => $user->language,
            'theme'               => $user->theme ?: 'dark',
            'occupation_standard' => $user->occupation_standard ?: 'enoc',
            'permissions'         => $user->permissionKeys(),
            'company'             => $user->company_id ? $this->resolveCompany($user) : null,
        ];
    }

    private function resolveCompany(User $user): ?array
    {
        $company = $user->company;

        if (! $company) {
            return null;
        }

        return [
            'id'                   => $company->id,
            'name'                 => $company->name,
            'name_ar'              => $company->name_ar,
            'display_name'         => $company->displayName(),
            'type'                 => $company->type,
            'seat_limit'           => $company->seat_limit,
            'subscription_ends_at' => $company->subscription_ends_at?->toDateString(),
            'days_left'            => $company->daysUntilExpiry(),
            'expiring_soon'        => $company->isExpiringSoon(),
        ];
    }
}
