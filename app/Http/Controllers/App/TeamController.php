<?php

namespace App\Http\Controllers\App;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\StoreTeamMemberRequest;
use App\Http\Requests\App\UpdateTeamMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\TeamController
//  Location: app/Http/Controllers/App/TeamController.php
//
//  /app/team — a Company Admin manages the partner's staff accounts
//  (permission: team.manage).
//
//    index()        → everyone in the partner, with seat usage
//    store()        → add a member — refused when every seat is taken
//                     (Scope v2 §5 "Seat-Limited User Creation"; the
//                     seat limit is set by the Super Admin)
//    update()       → name, email, job title, role, optional new password
//    toggleActive() → deactivate / reactivate. A deactivated member
//                     still uses a seat until removed — seats count
//                     accounts, not active sessions.
//
//  Guard rails: a member of ANOTHER partner can never be touched
//  (route-model binding is re-checked against company_id), and an
//  admin cannot demote or deactivate themselves — a partner must
//  never be left without an admin.
// ══════════════════════════════════════════════════════════════════

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $request->user()->company;

        $members = User::query()
            ->where('company_id', $company->id)
            ->orderByRaw("role = 'company_admin' desc")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'job_title', 'is_active', 'last_login_at', 'created_at'])
            ->map(fn (User $u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'initials'      => $u->initials(),
                'email'         => $u->email,
                'role'          => $u->role,
                'role_label'    => $u->roleLabel(),
                'job_title'     => $u->job_title,
                'is_active'     => $u->is_active,
                'is_me'         => $u->id === $request->user()->id,
                'last_login_at' => $u->last_login_at?->toDateTimeString(),
            ]);

        return Inertia::render('App/Team/Index', [
            'members' => $members,
            'seats'   => [
                'limit' => $company->seat_limit,
                'used'  => $members->count(),
            ],
        ]);
    }

    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        $company = $request->user()->company;

        if (! $company->hasFreeSeat()) {
            return back()->with('error', __('errors.no_free_seat', ['limit' => $company->seat_limit]));
        }

        User::create([
            'name'       => $request->validated('name'),
            'email'      => $request->validated('email'),
            'password'   => $request->validated('password'),
            'job_title'  => $request->validated('job_title'),
            'role'       => $request->validated('role'),
            'company_id' => $company->id,
            'created_by' => $request->user()->id,
            // Falls back to the app default if the admin's language is ever empty.
            'language'   => $request->user()->language ?: config('app.locale', 'en'),
        ]);

        return back()->with('success', __('common.member_added'));
    }

    public function update(UpdateTeamMemberRequest $request, User $user): RedirectResponse
    {
        $this->ensureSameCompany($request, $user);

        $data = $request->validated();

        if ($user->id === $request->user()->id && $data['role'] !== UserRole::CompanyAdmin->value) {
            return back()->with('error', __('errors.cannot_demote_self'));
        }

        DB::transaction(function () use ($user, $data) {
            $user->update([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'job_title' => $data['job_title'] ?? null,
                'role'      => $data['role'],
            ]);

            if (! empty($data['password'])) {
                $user->update(['password' => $data['password']]);
            }
        });

        \App\Support\Permissions::flush($user->id);

        return back()->with('success', __('common.saved'));
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->ensureSameCompany($request, $user);

        if ($user->id === $request->user()->id) {
            return back()->with('error', __('errors.cannot_deactivate_self'));
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? __('common.member_reactivated') : __('common.member_deactivated'));
    }

    private function ensureSameCompany(Request $request, User $user): void
    {
        abort_unless($user->company_id === $request->user()->company_id, 404);
    }
}
