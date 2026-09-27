<?php

namespace App\Http\Requests\App;

use App\Models\Employer;
use App\Models\Opportunity;
use App\Services\Eligibility\RuleBook;
use App\Services\Opportunities\OpportunityBook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// ══════════════════════════════════════════════════════════════════
//  Massar — SaveOpportunityRequest (Step 12 · Jobs & Training)
//  Location: app/Http/Requests/App/SaveOpportunityRequest.php
//  Scope: docs/SCOPE_JOBS_AND_TRAINING.md §4–5
//
//  Checks a Job or a Training Program before it is saved
//  (opportunities.manage). The kind comes from the address
//  (/app/jobs… or /app/training…), never from the form.
//
//    both       title (one field, any language), occupations (at least
//               one; the partner decides how many), governorates (at
//               least one), seats; optional description, city, dates,
//               contact person (someone of this workspace)
//    job        job type; optional employer (a company of the list, or
//               typed), sector, salary range
//    training   provider; optional duration, format, cost, certificate
//    eligibility  the two levels and the rules, cleaned by RuleBook:
//               REQUIRED — at least one rule (decision 27 Sep 2026)
// ══════════════════════════════════════════════════════════════════

class SaveOpportunityRequest extends FormRequest
{
    private ?array $cleaned = null;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('opportunities.manage');
    }

    public function kind(): string
    {
        $k = (string) $this->route()?->parameter('kind');

        return in_array($k, Opportunity::KINDS, true) ? $k : Opportunity::JOB;
    }

    protected function prepareForValidation(): void
    {
        $num = fn ($v) => is_string($v) ? str_replace([',', '٬', ' '], '', $v) : $v;
        $this->merge([
            'title'       => is_string($this->input('title')) ? trim(preg_replace('/\s+/u', ' ', $this->input('title'))) : $this->input('title'),
            'seats'       => $num($this->input('seats')),
            'salary_from' => $num($this->input('salary_from')) ?: null,
            'salary_to'   => $num($this->input('salary_to')) ?: null,
            'cost_amount' => $num($this->input('cost_amount')) ?: null,
            'employer'    => is_string($this->input('employer')) ? (trim($this->input('employer')) ?: null) : null,
            'provider'    => is_string($this->input('provider')) ? (trim($this->input('provider')) ?: null) : null,
        ]);
    }

    public function rules(): array
    {
        $companyId = (int) $this->user()?->company_id;
        $job = $this->kind() === Opportunity::JOB;

        $rules = [
            'title'           => ['required', 'string', 'max:150'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'occupations'     => ['required', 'array', 'min:1', 'max:'.RuleBook::MAX_OCCUPATIONS],
            'occupations.*'   => ['string', 'distinct'],
            'governorates'    => ['required', 'array', 'min:1'],
            'governorates.*'  => ['string', 'distinct', Rule::in(config('beneficiaries.governorates'))],
            'city'            => ['nullable', 'string', 'max:100'],
            'seats'           => ['required', 'integer', 'min:1', 'max:100000'],
            'deadline'        => ['nullable', 'date'],
            'starts_on'       => ['nullable', 'date'],
            'ends_on'         => ['nullable', 'date', 'after_or_equal:starts_on'],
            'contact_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'eligible_from'   => ['required', 'integer', 'min:0', 'max:100'],
            'check_from'      => ['required', 'integer', 'min:0', 'max:100', 'lte:eligible_from'],
            'rules'           => ['required', 'array'],
        ];

        return $rules + ($job ? [
            'employer'    => ['nullable', 'string', 'max:200'],
            'employer_id' => ['nullable', 'integer', Rule::exists('employers', 'id')->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))],
            'sub_sector'  => ['nullable', Rule::exists('sectors', 'code')->whereNotNull('parent')],
            'job_type'    => ['required', Rule::in(Opportunity::JOB_TYPES)],
            'salary_from' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'salary_to'   => ['nullable', 'integer', 'min:1', 'max:1000000', 'gte:salary_from'],
        ] : [
            'provider'       => ['required', 'string', 'max:200'],
            'duration_value' => ['nullable', 'integer', 'min:1', 'max:1000', 'required_with:duration_unit'],
            'duration_unit'  => ['nullable', Rule::in(Opportunity::DURATION_UNITS), 'required_with:duration_value'],
            'format'         => ['nullable', Rule::in(Opportunity::FORMATS)],
            'cost_type'      => ['nullable', Rule::in(Opportunity::COSTS)],
            'cost_amount'    => ['nullable', 'integer', 'min:1', 'max:10000000', 'required_if:cost_type,paid'],
            'certificate'    => ['nullable', 'string', 'max:200'],
        ]);
    }

    public function messages(): array
    {
        $v = fn (string $k, array $p = []) => __("opportunities.v.$k", $p);

        return [
            'title.*'            => $v('title'),
            'occupations.required' => $v('occupations'),
            'occupations.min'    => $v('occupations'),
            'occupations.array'  => $v('occupations'),
            'occupations.max'    => $v('occupations_max', ['n' => RuleBook::MAX_OCCUPATIONS]),
            'occupations.*'      => $v('occupation_bad'),
            'governorates.*'     => $v('governorates'),
            'governorates.*.*'   => $v('governorates'),
            'seats.*'            => $v('seats'),
            'deadline.*'         => $v('date'),
            'starts_on.*'        => $v('date'),
            'ends_on.after_or_equal' => $v('ends_after'),
            'ends_on.*'          => $v('date'),
            'contact_user_id.*'  => $v('contact'),
            'employer_id.*'      => $v('employer'),
            'sub_sector.*'       => $v('sector'),
            'job_type.*'         => $v('job_type'),
            'salary_from.*'      => $v('salary'),
            'salary_to.gte'      => $v('salary_order'),
            'salary_to.*'        => $v('salary'),
            'provider.*'         => $v('provider'),
            'duration_value.*'   => $v('duration'),
            'duration_unit.*'    => $v('duration'),
            'format.*'           => $v('choose'),
            'cost_type.*'        => $v('choose'),
            'cost_amount.*'      => $v('cost'),
            'eligible_from.*'    => __('assessments.v.eligible_from'),
            'check_from.*'       => __('assessments.v.check_from'),
            'rules.required'     => __('assessments.v.no_rules'),
            'rules.array'        => __('assessments.v.no_rules'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            // Every occupation must be a real one of the backbone.
            $book = app(OpportunityBook::class);
            foreach ((array) $this->input('occupations', []) as $i => $v) {
                if (! OpportunityBook::validValue($v) || ! $book->exists($v)) {
                    $validator->errors()->add("occupations.$i", __('opportunities.v.occupation_bad'));
                }
            }
            // The rules: required, complete, points adding up to 100.
            try {
                $this->cleaned = app(RuleBook::class)->clean($this->input('rules'));
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $m) {
                        $validator->errors()->add($key, $m);
                    }
                }
            }
        }];
    }

    /** The details as they are stored, for this kind only. */
    public function details(): array
    {
        $job = $this->kind() === Opportunity::JOB;
        $d = [
            'title'           => mb_substr((string) $this->input('title'), 0, 150),
            'description'     => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'occupations'     => array_values(array_unique((array) $this->input('occupations'))),
            'governorates'    => array_values(array_unique((array) $this->input('governorates'))),
            'city'            => $this->filled('city') ? trim((string) $this->input('city')) : null,
            'seats'           => (int) $this->input('seats'),
            'deadline'        => $this->input('deadline') ?: null,
            'starts_on'       => $this->input('starts_on') ?: null,
            'ends_on'         => $this->input('ends_on') ?: null,
            'contact_user_id' => $this->input('contact_user_id') ?: null,
            'eligible_from'   => (int) $this->input('eligible_from'),
            'check_from'      => (int) $this->input('check_from'),
        ];

        // Only the fields of this kind are kept; the others are emptied.
        $jobFields = ['employer' => null, 'employer_id' => null, 'sub_sector' => null, 'job_type' => null, 'salary_from' => null, 'salary_to' => null];
        $trainingFields = ['provider' => null, 'duration_value' => null, 'duration_unit' => null, 'format' => null, 'cost_type' => null, 'cost_amount' => null, 'certificate' => null];

        if ($job) {
            $employerId = $this->input('employer_id') ? (int) $this->input('employer_id') : null;
            $subSector = $this->input('sub_sector') ?: null;
            if ($employerId && ! $subSector) {
                $subSector = Employer::query()->whereKey($employerId)->value('sub_sector');   // a known company brings its sector
            }

            return $d + $trainingFields + [
                'employer'    => $this->input('employer') ? mb_substr((string) $this->input('employer'), 0, 200) : null,
                'employer_id' => $this->input('employer') ? $employerId : null,
                'sub_sector'  => $subSector,
                'job_type'    => $this->input('job_type'),
                'salary_from' => $this->input('salary_from') ? (int) $this->input('salary_from') : null,
                'salary_to'   => $this->input('salary_to') ? (int) $this->input('salary_to') : null,
            ];
        }

        $paid = $this->input('cost_type') === 'paid';

        return $d + $jobFields + [
            'provider'       => mb_substr((string) $this->input('provider'), 0, 200),
            'duration_value' => $this->input('duration_value') ? (int) $this->input('duration_value') : null,
            'duration_unit'  => $this->input('duration_value') ? $this->input('duration_unit') : null,
            'format'         => $this->input('format') ?: null,
            'cost_type'      => $this->input('cost_type') ?: null,
            'cost_amount'    => $paid ? (int) $this->input('cost_amount') : null,
            'certificate'    => $this->filled('certificate') ? trim((string) $this->input('certificate')) : null,
        ];
    }

    /** The rules as they are stored (after validation). */
    public function cleanRules(): array
    {
        return $this->cleaned ?? app(RuleBook::class)->clean($this->input('rules'));
    }
}
