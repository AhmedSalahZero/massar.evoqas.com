<?php

namespace App\Http\Requests\App;

use App\Enums\UserRole;
use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ══════════════════════════════════════════════════════════════════
//  Massar — UpdateTeamMemberRequest
//  Location: app/Http/Requests/App/UpdateTeamMemberRequest.php
//
//  Validates editing a staff account (App\TeamController::update).
//  The password is optional — leave it empty to keep the current one.
// ══════════════════════════════════════════════════════════════════

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('team.manage');
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'min:2', 'max:100'],
            'email'     => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role'      => ['required', Rule::in([UserRole::CompanyAdmin->value, UserRole::Employee->value])],
            'password'  => ['nullable', 'confirmed', PasswordRules::defaults()],
        ];
    }
}
