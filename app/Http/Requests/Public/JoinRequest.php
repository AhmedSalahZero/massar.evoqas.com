<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\App\SaveBeneficiaryRequest;
use App\Models\JobSeeker;
use App\Support\PasswordRules;
use Illuminate\Validation\Rule;

// ══════════════════════════════════════════════════════════════════
//  Massar — JoinRequest (Step 10 · a job seeker registers on the public site)
//  Location: app/Http/Requests/Public/JoinRequest.php
//
//  The profile is checked with EXACTLY the registration form's rules
//  and tidying (SaveBeneficiaryRequest). On the public site, also:
//    · a mobile AND an email are needed (the email is the sign-in;
//      partners call the mobile). One account per email and per mobile.
//    · a password (the same strength rules as staff passwords)
//    · the privacy notice must be accepted; "show my profile to
//      partners" is their choice (visible)
//    · notice period, optional
//    · cv: the CV they uploaded at the start, if any
// ══════════════════════════════════════════════════════════════════

class JoinRequest extends SaveBeneficiaryRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge([
            'visible'          => filter_var($this->input('visible', false), FILTER_VALIDATE_BOOLEAN),
            'privacy'          => filter_var($this->input('privacy', false), FILTER_VALIDATE_BOOLEAN),
            'notice_period'    => $this->input('notice_period') ?: null,
            'confirm_duplicate'=> false,
        ]);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['phone'] = ['required', ...array_values(array_filter($rules['phone'], fn ($r) => ! in_array($r, ['nullable', 'required_without:email'], true))),
            Rule::unique('job_seekers', 'phone')];
        $rules['email'] = ['required', 'string', 'email', 'max:150', Rule::unique('job_seekers', 'email')];

        return $rules + [
            'password'      => ['required', 'confirmed', PasswordRules::defaults()],
            'privacy'       => ['accepted'],
            'visible'       => ['boolean'],
            'notice_period' => ['nullable', Rule::in(JobSeeker::NOTICE_PERIODS)],
            'cv'            => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'phone.required'   => __('pool.v_phone'),
            'email.required'   => __('pool.v_email'),
            'phone.unique'     => __('pool.phone_taken'),
            'email.unique'     => __('pool.email_taken'),
            'privacy.accepted' => __('pool.v_privacy'),
        ];
    }
}
