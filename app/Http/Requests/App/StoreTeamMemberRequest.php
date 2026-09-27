<?php

namespace App\Http\Requests\App;

use App\Enums\UserRole;
use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ══════════════════════════════════════════════════════════════════
//  Massar — StoreTeamMemberRequest
//  Location: app/Http/Requests/App/StoreTeamMemberRequest.php
//
//  Validates a Company Admin adding a staff account
//  (App\TeamController::store). Role is company_admin or employee —
//  a partner can never create a super_admin.
// ══════════════════════════════════════════════════════════════════

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('team.manage');
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'min:2', 'max:100'],
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role'      => ['required', Rule::in([UserRole::CompanyAdmin->value, UserRole::Employee->value])],
            'password'  => ['required', 'confirmed', PasswordRules::defaults()],
        ];
    }
}
