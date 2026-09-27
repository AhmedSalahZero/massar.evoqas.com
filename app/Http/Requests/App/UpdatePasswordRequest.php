<?php

namespace App\Http\Requests\App;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

// ══════════════════════════════════════════════════════════════════
//  Massar — UpdatePasswordRequest
//  Location: app/Http/Requests/App/UpdatePasswordRequest.php
//
//  Validates changing your own password: the current one must be
//  right, and the new one must meet App\Support\PasswordRules.
// ══════════════════════════════════════════════════════════════════

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', PasswordRules::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => __('profile.password_incorrect'),
        ];
    }
}
