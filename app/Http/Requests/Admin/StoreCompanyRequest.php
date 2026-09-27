<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ══════════════════════════════════════════════════════════════════
//  Massar — StoreCompanyRequest
//  Location: app/Http/Requests/Admin/StoreCompanyRequest.php
//
//  Validates onboarding a partner organisation together with its
//  first Company Admin (Admin\CompanyController::store). Super Admin
//  only (permission: platform.companies).
// ══════════════════════════════════════════════════════════════════

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('platform.companies');
    }

    public function rules(): array
    {
        return [
            // ── Partner ─────────────────────────────────────────
            'name'                 => ['required', 'string', 'max:150'],
            'name_ar'              => ['nullable', 'string', 'max:150'],
            'type'                 => ['required', 'string', Rule::in(Company::TYPES)],
            'governorate'          => ['nullable', 'string', 'max:10'],
            'contact_email'        => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone'        => ['nullable', 'string', 'max:30'],
            'seat_limit'           => ['required', 'integer', 'min:1', 'max:1000'],
            'subscription_ends_at' => ['nullable', 'date', 'after:today'],

            // ── First Company Admin ─────────────────────────────
            'admin_name'      => ['required', 'string', 'min:2', 'max:100'],
            'admin_email'     => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_job_title' => ['nullable', 'string', 'max:100'],
            'admin_language'  => ['nullable', 'string', 'in:en,ar'],
            'admin_password'  => ['required', 'confirmed', PasswordRules::defaults()],
        ];
    }
}
