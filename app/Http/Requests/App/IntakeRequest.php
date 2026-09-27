<?php

namespace App\Http\Requests\App;

// ══════════════════════════════════════════════════════════════════
//  Massar — IntakeRequest (Step 9 · Guided Intake)
//  Location: app/Http/Requests/App/IntakeRequest.php
//
//  The Guided Intake's answers are the same profile as the
//  registration form, so they are checked with EXACTLY the same rules
//  and tidying (SaveBeneficiaryRequest). One more field: `cv`, the CV
//  read at the start of the journey (when the person had one), which
//  is attached to the new profile.
// ══════════════════════════════════════════════════════════════════

class IntakeRequest extends SaveBeneficiaryRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('beneficiaries.create');
    }

    public function rules(): array
    {
        return parent::rules() + [
            'cv' => ['nullable', 'uuid'],
        ];
    }
}
