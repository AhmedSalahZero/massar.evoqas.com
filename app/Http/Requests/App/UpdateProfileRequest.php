<?php

namespace App\Http\Requests\App;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ══════════════════════════════════════════════════════════════════
//  Massar — UpdateProfileRequest
//  Location: app/Http/Requests/App/UpdateProfileRequest.php
//
//  Validates a user editing their own profile (ProfileController).
//  Role, company and permissions are NOT here on purpose — nobody
//  edits their own access.
// ══════════════════════════════════════════════════════════════════

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'min:2', 'max:100'],
            'email'               => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone'               => ['nullable', 'string', 'max:30'],
            'job_title'           => ['nullable', 'string', 'max:100'],
            'language'            => ['required', 'in:en,ar'],
            'theme'               => ['required', 'in:dark,light'],
            'occupation_standard' => ['required', Rule::in(User::STANDARDS)],
        ];
    }
}
