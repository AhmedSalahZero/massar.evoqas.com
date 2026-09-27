<?php

namespace App\Http\Requests\App;

// ══════════════════════════════════════════════════════════════════
//  Massar — ApproveCvRequest
//  Location: app/Http/Requests/App/ApproveCvRequest.php
//
//  The review screen's "Approve and create profile". The reviewer
//  sends the same fields as the registration form, so it is checked
//  with EXACTLY the same rules and tidying (SaveBeneficiaryRequest);
//  only the permission differs: cv.review.
// ══════════════════════════════════════════════════════════════════

class ApproveCvRequest extends SaveBeneficiaryRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('cv.review');
    }
}
