<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ══════════════════════════════════════════════════════════════════
//  Massar — UpdateCompanyRequest
//  Location: app/Http/Requests/Admin/UpdateCompanyRequest.php
//
//  Validates editing a partner organisation's details, seats and
//  subscription end date (Admin\CompanyController::update).
//  The seat limit can never go below the accounts the partner
//  already has — lower it only after deactivating/removing users.
// ══════════════════════════════════════════════════════════════════

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('platform.companies');
    }

    public function rules(): array
    {
        /** @var Company $company */
        $company = $this->route('company');

        return [
            'name'                 => ['required', 'string', 'max:150'],
            'name_ar'              => ['nullable', 'string', 'max:150'],
            'type'                 => ['required', 'string', Rule::in(Company::TYPES)],
            'governorate'          => ['nullable', 'string', 'max:10'],
            'contact_email'        => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone'        => ['nullable', 'string', 'max:30'],
            'seat_limit'           => ['required', 'integer', 'min:'.max(1, $company->users()->count()), 'max:1000'],
            'subscription_ends_at' => ['nullable', 'date'],
        ];
    }
}
