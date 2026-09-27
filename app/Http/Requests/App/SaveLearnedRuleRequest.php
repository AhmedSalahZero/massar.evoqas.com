<?php

namespace App\Http\Requests\App;

use App\Models\LearnedRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// ══════════════════════════════════════════════════════════════════
//  Massar — SaveLearnedRuleRequest (Scope v2 §3 Learned Rules)
//  Location: app/Http/Requests/App/SaveLearnedRuleRequest.php
//
//  One rule, taught on the review screen or the Learned Rules page:
//    kind      heading | title | skill | employer
//    phrase    the words as they appear in CVs
//    section             (heading) what the heading means — LearnedRule::SECTIONS
//    esco_occupation_id  (title)   a detailed ESCO job, or
//    occupation_unit     (title)   a 4-digit group ESCO does not detail
//    skill_name          (skill)   the name to add to the profile (optional:
//                                  the phrase itself when empty)
//    (employer)          only the phrase: a company name, so the CV reader
//                        knows the line is the employer ("Etisalat Misr")
//  Permission: rules.manage (checked on the route).
// ══════════════════════════════════════════════════════════════════

class SaveLearnedRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => is_string($v) ? (trim(preg_replace('/\s+/u', ' ', $v) ?? '') ?: null) : $v;
        $this->merge([
            'phrase'             => $clean($this->input('phrase')),
            'skill_name'         => $clean($this->input('skill_name')),
            'esco_occupation_id' => $this->input('esco_occupation_id') ?: null,
            'occupation_unit'    => $this->input('occupation_unit') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'kind'               => ['required', Rule::in(LearnedRule::KINDS)],
            'phrase'             => ['required', 'string', 'min:2', 'max:100'],
            'section'            => ['required_if:kind,heading', 'nullable', Rule::in(LearnedRule::SECTIONS)],
            'esco_occupation_id' => ['nullable', 'integer', Rule::exists('esco_occupations', 'id')->where('is_active', true)],
            'occupation_unit'    => ['nullable', 'regex:/^\d{4}$/', Rule::exists('isco_groups', 'code')->where('level', 4)],
            'skill_name'         => ['nullable', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $kind = $this->input('kind');
            $phrase = (string) $this->input('phrase');
            if ($kind === 'heading' && count(preg_split('/\s+/u', trim($phrase))) > 7) {
                $v->errors()->add('phrase', __('rules.v.heading_long'));
            }
            if ($kind === 'skill' && count(preg_split('/\s+/u', trim($phrase))) > 6) {
                $v->errors()->add('phrase', __('rules.v.skill_long'));
            }
            if ($kind === 'employer' && count(preg_split('/\s+/u', trim($phrase))) > 10) {
                $v->errors()->add('phrase', __('rules.v.employer_long'));
            }
            if ($kind === 'title' && ! $this->input('esco_occupation_id') && ! $this->input('occupation_unit')) {
                $v->errors()->add('occupation', __('rules.v.occupation'));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'phrase.required'      => __('rules.v.phrase'),
            'phrase.min'           => __('rules.v.phrase'),
            'phrase.max'           => __('rules.v.phrase_long'),
            'section.required_if'  => __('rules.v.section'),
            'section.in'           => __('rules.v.section'),
            'esco_occupation_id.*' => __('rules.v.occupation'),
            'occupation_unit.*'    => __('rules.v.occupation'),
        ];
    }

    /** The target of the rule, for LearnedRuleBook::teach(). */
    public function target(): array
    {
        return [
            'section'            => $this->input('section'),
            'esco_occupation_id' => $this->input('esco_occupation_id') ? (int) $this->input('esco_occupation_id') : null,
            'occupation_unit'    => $this->input('occupation_unit'),
            'skill_name'         => $this->input('skill_name'),
        ];
    }
}
