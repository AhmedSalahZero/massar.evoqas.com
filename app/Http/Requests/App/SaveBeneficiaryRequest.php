<?php

namespace App\Http\Requests\App;

use App\Support\EgyptPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// ══════════════════════════════════════════════════════════════════
//  Massar — SaveBeneficiaryRequest
//  Location: app/Http/Requests/App/SaveBeneficiaryRequest.php
//
//  Checks a beneficiary profile before it is saved — for registering
//  (POST, needs beneficiaries.create) and editing (PATCH, needs
//  beneficiaries.edit). Every choice list comes from
//  config/beneficiaries.php, the same lists the form shows.
//
//  What a profile needs, at minimum:
//    · a name — in Arabic, in English, or both
//    · gender and governorate
//    · one way to reach the person — an Egyptian mobile or an email
//  Everything else is optional, so a case worker can register someone
//  in a minute and complete the profile later.
//
//  Tidying before the checks (prepareForValidation):
//    · names: extra spaces removed
//    · mobile: Arabic digits, spaces, dashes and +20 / 0020 removed
//      → always stored as 01XXXXXXXXX (EgyptPhone)
//    · email: lower case
//    · empty rows in the lists are dropped; a job marked "current"
//      has no end month; repeated skills are kept once
//    · military status is kept for men only
//
//  The occupation arrives as ONE of:
//    esco_occupation_id  a detailed ESCO job (the normal case), or
//    occupation_unit     a 4-digit group ESCO does not detail
//  BeneficiaryRecorder turns it into the stored link.
// ══════════════════════════════════════════════════════════════════

class SaveBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->isMethod('post') ? 'beneficiaries.create' : 'beneficiaries.edit');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => is_string($v) ? (trim(preg_replace('/\s+/u', ' ', $v)) ?: null) : $v;
        $list = fn ($key) => is_array($this->input($key)) ? array_values($this->input($key)) : [];

        $education = array_values(array_filter(array_map(fn ($r) => is_array($r) ? [
            'qualification' => $clean($r['qualification'] ?? null),
            'field'         => $clean($r['field'] ?? null),
            'institution'   => $clean($r['institution'] ?? null),
            'year'          => ($r['year'] ?? '') === '' ? null : $r['year'],
        ] : null, $list('education')), fn ($r) => $r && array_filter($r, fn ($v) => $v !== null)));

        $work = array_values(array_filter(array_map(function ($r) use ($clean) {
            if (! is_array($r)) {
                return null;
            }
            $current = filter_var($r['current'] ?? false, FILTER_VALIDATE_BOOLEAN);
            // Job responsibilities: a list, or text with one duty per line. Empty lines are dropped.
            $duties = $r['responsibilities'] ?? [];
            $duties = is_string($duties) ? preg_split('/\R/u', $duties) : (is_array($duties) ? $duties : []);
            $duties = array_values(array_filter(array_map(fn ($d) => is_string($d) ? $clean($d) : $d, $duties), fn ($d) => $d !== null && $d !== ''));

            return [
                'title'    => $clean($r['title'] ?? null),
                'employer' => $clean($r['employer'] ?? null),
                'location' => $clean($r['location'] ?? null),
                // Step 10.5: the country, the sub-sector and the known company (if one was picked).
                'country'     => ($c = strtoupper((string) $clean($r['country'] ?? null))) !== '' ? $c : null,
                'sub_sector'  => $clean($r['sub_sector'] ?? null),
                'employer_id' => is_numeric($r['employer_id'] ?? null) ? (int) $r['employer_id'] : null,
                'governorate' => $clean($r['governorate'] ?? null),               // jobs in Egypt
                'sector'      => $clean($r['sector'] ?? null),                    // only with "Other"
                'sub_sector_other' => $clean($r['sub_sector_other'] ?? null),     // "Other (not in the list)"
                'sector_unknown'   => filter_var($r['sector_unknown'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'from'     => $clean($r['from'] ?? null),
                'to'       => $current ? null : $clean($r['to'] ?? null),
                'current'  => $current,
                'responsibilities' => $duties,
            ];
        }, $list('work_history')), fn ($r) => $r && ($r['title'] || $r['employer'] || $r['from'] || $r['to'] || $r['responsibilities'])));

        $skills = [];
        foreach ($list('skills') as $s) {
            $s = $clean(is_string($s) ? $s : null);
            if ($s !== null && ! isset($skills[mb_strtolower($s)])) {
                $skills[mb_strtolower($s)] = $s;
            }
        }

        $languages = array_values(array_filter(array_map(fn ($r) => is_array($r) ? [
            'code'  => $clean($r['code'] ?? null),
            'level' => $clean($r['level'] ?? null),
        ] : null, $list('languages')), fn ($r) => $r && $r['code']));

        $email = $clean($this->input('email'));
        $gender = $this->input('gender');

        $this->merge([
            'name_ar'            => $clean($this->input('name_ar')),
            'name_en'            => $clean($this->input('name_en')),
            'city'               => $clean($this->input('city')),
            'phone'              => EgyptPhone::normalize($this->input('phone')),
            'email'              => $email ? mb_strtolower($email) : null,
            'military_status'    => $gender === 'male' ? ($this->input('military_status') ?: null) : null,
            'date_of_birth'      => $this->input('date_of_birth') ?: null,
            'education_level'    => $this->input('education_level') ?: null,
            'education'          => $education,
            'work_history'       => $work,
            'skills'             => array_values($skills),
            'languages'          => $languages,
            'esco_occupation_id' => $this->input('esco_occupation_id') ?: null,
            'occupation_unit'    => $this->input('occupation_unit') ?: null,
            'expected_salary'    => $this->amount($this->input('expected_salary')),
            'job_type'           => $this->input('job_type') ?: null,
            'confirm_duplicate'  => filter_var($this->input('confirm_duplicate', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /** '7,500' / '٧٥٠٠' / '7 500' → '7500'. Anything else is left as typed, so it fails the number check. */
    private function amount(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $raw = trim((string) $v);
        $digits = EgyptPhone::digits($raw);
        $separators = preg_replace('/[\d\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', '', $raw);

        return $digits !== '' && preg_match('/^[\s,،\x{066C}]*$/u', $separators) ? $digits : $raw;
    }

    public function rules(): array
    {
        $c = config('beneficiaries');
        $thisYear = (int) date('Y');

        return [
            'name_ar'          => ['nullable', 'string', 'min:2', 'max:150', 'required_without:name_en'],
            'name_en'          => ['nullable', 'string', 'min:2', 'max:150', 'required_without:name_ar'],
            'gender'           => ['required', Rule::in($c['genders'])],
            'date_of_birth'    => ['nullable', 'date_format:Y-m-d', 'after:1940-01-01', 'before:today'],
            'military_status'  => ['nullable', Rule::in($c['military_statuses'])],
            'governorate'      => ['required', Rule::in($c['governorates'])],
            'city'             => ['nullable', 'string', 'max:100'],

            'phone'            => ['nullable', 'regex:'.EgyptPhone::PATTERN, 'required_without:email'],
            'email'            => ['nullable', 'string', 'email', 'max:150', 'required_without:phone'],

            'education_level'  => ['nullable', Rule::in($c['education_levels'])],
            'education'        => ['array', 'max:'.$c['max']['education']],
            'education.*.qualification' => ['required', 'string', 'max:150'],
            'education.*.field'         => ['nullable', 'string', 'max:150'],
            'education.*.institution'   => ['nullable', 'string', 'max:150'],
            'education.*.year'          => ['nullable', 'integer', 'min:1950', 'max:'.($thisYear + 6)],

            'work_history'            => ['array', 'max:'.$c['max']['work_history']],
            'work_history.*.title'    => ['required', 'string', 'max:150'],
            'work_history.*.employer' => ['nullable', 'string', 'max:150'],
            'work_history.*.location' => ['nullable', 'string', 'max:100'],
            'work_history.*.country'  => ['nullable', Rule::in(config('countries.codes'))],
            'work_history.*.sub_sector'  => ['nullable', Rule::exists('sectors', 'code')->whereNotNull('parent')],
            'work_history.*.employer_id' => ['nullable', 'integer', Rule::exists('employers', 'id')],
            'work_history.*.governorate' => ['nullable', Rule::in($c['governorates'])],
            'work_history.*.sector'      => ['nullable', Rule::in(['IND', 'TRD', 'SRV'])],
            'work_history.*.sub_sector_other' => ['nullable', 'string', 'max:100'],
            'work_history.*.sector_unknown'   => ['boolean'],
            'work_history.*.from'     => ['required', 'date_format:Y-m'],
            'work_history.*.to'       => ['nullable', 'date_format:Y-m'],
            'work_history.*.current'  => ['boolean'],
            'work_history.*.responsibilities'   => ['array', 'max:'.($c['max']['responsibilities'] ?? 30)],
            'work_history.*.responsibilities.*' => ['string', 'max:500'],

            'skills'           => ['array', 'max:'.$c['max']['skills']],
            'skills.*'         => ['string', 'max:60'],
            'languages'        => ['array', 'max:'.$c['max']['languages']],
            'languages.*.code' => ['required', 'distinct', Rule::in($c['languages'])],
            'languages.*.level'=> ['required', Rule::in($c['language_levels'])],

            'esco_occupation_id' => ['nullable', 'integer', Rule::exists('esco_occupations', 'id')->where('is_active', true)],
            'occupation_unit'    => ['nullable', 'regex:/^\d{4}$/', Rule::exists('isco_groups', 'code')->where('level', 4)],

            'expected_salary'  => ['nullable', 'integer', 'min:100', 'max:1000000'],
            'job_type'         => ['nullable', Rule::in($c['job_types'])],
            'confirm_duplicate'=> ['boolean'],
        ];
    }

    /** Checks that need more than one field. */
    public function after(): array
    {
        return [function (Validator $v) {
            $thisMonth = date('Y-m');
            foreach ($this->input('work_history', []) as $i => $job) {
                $from = $job['from'] ?? null;
                $to = $job['to'] ?? null;
                if (! $v->errors()->has("work_history.{$i}.from") && $from && $from > $thisMonth) {
                    $v->errors()->add("work_history.{$i}.from", __('beneficiaries.v.month_future'));
                }
                if (! $v->errors()->has("work_history.{$i}.to") && $to && $to > $thisMonth) {
                    $v->errors()->add("work_history.{$i}.to", __('beneficiaries.v.month_future'));
                }
                if ($from && $to && $to < $from && ! $v->errors()->has("work_history.{$i}.to")) {
                    $v->errors()->add("work_history.{$i}.to", __('beneficiaries.v.end_before_start'));
                }
                if (! $to && ! ($job['current'] ?? false) && ! $v->errors()->has("work_history.{$i}.to")) {
                    $v->errors()->add("work_history.{$i}.to", __('beneficiaries.v.end_or_current'));
                }
            }

            if ($this->input('esco_occupation_id') && $this->input('occupation_unit')) {
                $v->errors()->add('occupation', __('beneficiaries.v.occupation_one'));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name_ar.required_without' => __('beneficiaries.v.name'),
            'name_en.required_without' => __('beneficiaries.v.name'),
            'phone.required_without'   => __('beneficiaries.v.contact'),
            'email.required_without'   => __('beneficiaries.v.contact'),
            'phone.regex'              => __('beneficiaries.v.phone'),
            'date_of_birth.before'     => __('beneficiaries.v.dob'),
            'date_of_birth.after'      => __('beneficiaries.v.dob'),
            'date_of_birth.date_format'=> __('beneficiaries.v.dob'),
            'education.*.qualification.required' => __('beneficiaries.v.qualification'),
            'work_history.*.title.required'      => __('beneficiaries.v.job_title'),
            'work_history.*.from.required'       => __('beneficiaries.v.month'),
            'work_history.*.from.date_format'    => __('beneficiaries.v.month'),
            'work_history.*.to.date_format'      => __('beneficiaries.v.month'),
            'work_history.*.responsibilities.max'   => __('beneficiaries.v.duties_many'),
            'work_history.*.sub_sector.exists'      => __('beneficiaries.v.sector'),
            'work_history.*.employer_id.exists'     => __('beneficiaries.v.employer'),
            'work_history.*.responsibilities.*.max' => __('beneficiaries.v.duty_long'),
            'languages.*.code.distinct'          => __('beneficiaries.v.language_twice'),
            'languages.*.level.required'         => __('beneficiaries.v.language_level'),
            'esco_occupation_id.exists'          => __('beneficiaries.v.occupation'),
            'occupation_unit.exists'             => __('beneficiaries.v.occupation'),
            'occupation_unit.regex'              => __('beneficiaries.v.occupation'),
            'expected_salary.integer'            => __('beneficiaries.v.salary'),
            'expected_salary.min'                => __('beneficiaries.v.salary'),
            'expected_salary.max'                => __('beneficiaries.v.salary'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name_ar'         => __('beneficiaries.f.name_ar'),
            'name_en'         => __('beneficiaries.f.name_en'),
            'gender'          => __('beneficiaries.f.gender'),
            'date_of_birth'   => __('beneficiaries.f.date_of_birth'),
            'military_status' => __('beneficiaries.f.military_status'),
            'governorate'     => __('beneficiaries.f.governorate'),
            'city'            => __('beneficiaries.f.city'),
            'phone'           => __('beneficiaries.f.phone'),
            'email'           => __('beneficiaries.f.email'),
            'education_level' => __('beneficiaries.f.education_level'),
            'expected_salary' => __('beneficiaries.f.expected_salary'),
            'job_type'        => __('beneficiaries.f.job_type'),
        ];
    }
}
