<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\App\SaveBeneficiaryRequest;
use App\Models\JobSeeker;
use Illuminate\Validation\Rule;

// ══════════════════════════════════════════════════════════════════
//  Massar — SeekerProfileRequest (Step 10 · "Edit my profile")
//  Location: app/Http/Requests/Public/SeekerProfileRequest.php
//
//  The registration form's own rules (SaveBeneficiaryRequest), plus:
//  the mobile is needed and must not be another job seeker's; the
//  email is the sign-in and is always the account's (it cannot be
//  changed here).
// ══════════════════════════════════════════════════════════════════

class SeekerProfileRequest extends SaveBeneficiaryRequest
{
    public function authorize(): bool
    {
        return auth('seeker')->check();
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge([
            'email'             => auth('seeker')->user()?->email,
            'notice_period'     => $this->input('notice_period') ?: null,
            'confirm_duplicate' => false,
        ]);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['phone'] = ['required', ...array_values(array_filter($rules['phone'], fn ($r) => ! in_array($r, ['nullable', 'required_without:email'], true))),
            Rule::unique('job_seekers', 'phone')->ignore(auth('seeker')->id())];

        return $rules + [
            'notice_period' => ['nullable', Rule::in(JobSeeker::NOTICE_PERIODS)],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'phone.required' => __('pool.v_phone'),
            'phone.unique'   => __('pool.phone_taken'),
        ];
    }
}
