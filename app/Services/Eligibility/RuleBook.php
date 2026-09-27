<?php

namespace App\Services\Eligibility;

use App\Models\Backbone\EnocOccupation;
use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use Illuminate\Validation\ValidationException;

// ══════════════════════════════════════════════════════════════════
//  Massar — RuleBook (Step 11 · Eligibility Assessment)
//  Location: app/Services/Eligibility/RuleBook.php
//
//  What an eligibility rule of a job or training can be, how it is cleaned and checked before
//  it is saved, and how it is shown. Every rule reads information the
//  profile ALREADY holds (docs/SCOPE_ELIGIBILITY_ASSESSMENT.md §3):
//
//    age              min, max               ← date of birth
//    governorate      values [cai, giz …]    ← governorate
//    education_level  values [university …]  ← education level (the 9 Egyptian levels)
//    field_of_study   words [accounting …]   ← education: field and qualification
//    skills           values, need all|any   ← skills
//    occupation       values [isco:24, enoc:4222, esco:2411.1]
//                                            ← occupation, any standard and level
//    experience       min_years              ← years of experience (from the work history)
//    language         code, level            ← languages
//    military         values [completed …]   ← military status (men)
//    gender           value                  ← gender
//    salary           max (EGP a month)      ← expected salary
//
//  Every rule also has:
//    mode    must    fail it → Not eligible, whatever the score
//            counts  pass it → its points are added to the score
//    points  1–100 for "counts" (all of them add up to exactly 100)
// ══════════════════════════════════════════════════════════════════

class RuleBook
{
    public const TYPES = [
        'age', 'governorate', 'education_level', 'field_of_study', 'skills', 'occupation',
        'experience', 'language', 'military', 'gender', 'salary',
    ];

    public const MODES = ['must', 'counts'];

    public const MAX_RULES = 30;

    /** A safety ceiling only: the partner decides how many occupations (Step 12). */
    public const MAX_OCCUPATIONS = 100;

    /** Language levels, lowest first. */
    public const LANGUAGE_RANK = ['basic' => 1, 'good' => 2, 'fluent' => 3, 'native' => 4];

    /**
     * Clean the rules sent by the form and check them. Throws a
     * ValidationException naming the rule ("rules.2.values") when
     * something is wrong.
     *
     * @return list<array>  the rules as they are stored
     */
    public function clean(mixed $rules): array
    {
        if (! is_array($rules) || $rules === []) {
            throw ValidationException::withMessages(['rules' => __('assessments.v.no_rules')]);
        }
        if (count($rules) > self::MAX_RULES) {
            throw ValidationException::withMessages(['rules' => __('assessments.v.too_many', ['n' => self::MAX_RULES])]);
        }

        $out = [];
        $errors = [];
        foreach (array_values($rules) as $i => $r) {
            try {
                $out[] = $this->cleanOne(is_array($r) ? $r : [], $i);
            } catch (ValidationException $e) {
                $errors += $e->errors();
            }
        }

        // The "Counts" points must add up to exactly 100 (said together with any other mistake).
        $counting = array_filter($rules, fn ($r) => is_array($r) && ($r['mode'] ?? null) === 'counts');
        $sum = array_sum(array_map(fn ($r) => (int) filter_var($r['points'] ?? 0, FILTER_VALIDATE_INT), $counting));
        if ($counting && $sum !== 100) {
            $errors['points_total'] = [__('assessments.v.points_total', ['n' => $sum])];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    private function cleanOne(array $r, int $i): array
    {
        $key = "rules.$i";
        $fail = fn (string $field, string $message, array $p = []) => throw ValidationException::withMessages(["$key.$field" => __("assessments.v.$message", $p)]);

        $type = $r['type'] ?? null;
        if (! in_array($type, self::TYPES, true)) {
            $fail('type', 'type');
        }
        $mode = in_array($r['mode'] ?? null, self::MODES, true) ? $r['mode'] : 'must';
        $points = 0;
        if ($mode === 'counts') {
            $points = filter_var($r['points'] ?? null, FILTER_VALIDATE_INT);
            if ($points === false || $points < 1 || $points > 100) {
                $fail('points', 'points');
            }
        }
        $rule = ['type' => $type, 'mode' => $mode, 'points' => $points];
        $c = config('beneficiaries');
        $pickList = function (string $field, array $allowed) use ($r, $fail): array {
            $v = array_values(array_unique(array_filter((array) ($r[$field] ?? []), fn ($x) => is_string($x) && in_array($x, $allowed, true))));
            if ($v === []) {
                $fail($field, 'choose');
            }

            return $v;
        };
        $words = function (string $field, int $max, int $len) use ($r, $fail): array {
            $v = [];
            foreach ((array) ($r[$field] ?? []) as $w) {
                $w = is_string($w) ? trim(preg_replace('/\s+/u', ' ', $w)) : '';
                if ($w !== '' && ! in_array(mb_strtolower($w), array_map('mb_strtolower', $v), true)) {
                    $v[] = mb_substr($w, 0, $len);
                }
            }
            if ($v === []) {
                $fail($field, 'words');
            }
            if (count($v) > $max) {
                $fail($field, 'too_many_words', ['n' => $max]);
            }

            return $v;
        };
        $int = function (string $field, int $min, int $max, bool $required = true) use ($r, $fail): ?int {
            $raw = $r[$field] ?? null;
            if ($raw === null || $raw === '') {
                if ($required) {
                    $fail($field, 'number', ['min' => $min, 'max' => $max]);
                }

                return null;
            }
            $v = filter_var(str_replace([',', '٬'], '', (string) $raw), FILTER_VALIDATE_INT);
            if ($v === false || $v < $min || $v > $max) {
                $fail($field, 'number', ['min' => $min, 'max' => $max]);
            }

            return $v;
        };

        switch ($type) {
            case 'age':
                $rule['min'] = $int('min', 14, 80, false);
                $rule['max'] = $int('max', 14, 80, false);
                if ($rule['min'] === null && $rule['max'] === null) {
                    $fail('min', 'age_empty');
                }
                if ($rule['min'] !== null && $rule['max'] !== null && $rule['min'] > $rule['max']) {
                    $fail('max', 'age_order');
                }
                break;
            case 'governorate':
                $rule['values'] = $pickList('values', $c['governorates']);
                break;
            case 'education_level':
                $rule['values'] = $pickList('values', $c['education_levels']);
                break;
            case 'field_of_study':
                $rule['words'] = $words('words', 20, 60);
                break;
            case 'skills':
                $rule['values'] = $words('values', 30, 80);
                $rule['need'] = ($r['need'] ?? 'all') === 'any' ? 'any' : 'all';
                break;
            case 'occupation':
                $v = array_values(array_unique(array_filter((array) ($r['values'] ?? []),
                    fn ($x) => is_string($x) && preg_match('/^(isco|enoc|esco):[\d.]{1,40}$/', $x))));
                if ($v === []) {
                    $fail('values', 'occupation');
                }
                if (count($v) > self::MAX_OCCUPATIONS) {
                    $fail('values', 'too_many_words', ['n' => self::MAX_OCCUPATIONS]);
                }
                $rule['values'] = $v;
                break;
            case 'experience':
                $rule['min_years'] = $int('min_years', 1, 40);
                break;
            case 'language':
                if (! in_array($r['code'] ?? null, $c['languages'], true)) {
                    $fail('code', 'choose');
                }
                if (! isset(self::LANGUAGE_RANK[$r['level'] ?? ''])) {
                    $fail('level', 'choose');
                }
                $rule['code'] = $r['code'];
                $rule['level'] = $r['level'];
                break;
            case 'military':
                $rule['values'] = $pickList('values', $c['military_statuses']);
                break;
            case 'gender':
                if (! in_array($r['value'] ?? null, $c['genders'], true)) {
                    $fail('value', 'choose');
                }
                $rule['value'] = $r['value'];
                break;
            case 'salary':
                $rule['max'] = $int('max', 1, 1000000);
                break;
        }

        return $rule;
    }

    /**
     * The rules for the screens: occupation codes get their titles in
     * English and Arabic, so the page can show them in either language.
     */
    public function present(array $rules): array
    {
        return array_map(function ($r) {
            if (($r['type'] ?? null) === 'occupation') {
                $r['labels'] = array_map(fn ($v) => $this->occupationLabel($v), $r['values'] ?? []);
            }

            return $r;
        }, $rules);
    }

    /** "isco:24" → {value, standard, code, title_en, title_ar}. */
    public function occupationLabel(string $value): array
    {
        [$standard, $code] = array_pad(explode(':', $value, 2), 2, '');
        $out = ['value' => $value, 'standard' => $standard, 'code' => $code, 'title_en' => null, 'title_ar' => null];
        try {
            if ($standard === 'esco') {
                $e = EscoOccupation::query()->where('code', $code)->first(['title_en', 'title_ar']);
                $out['title_en'] = $e?->title_en;
                $out['title_ar'] = $e?->title_ar;
            } elseif ($standard === 'enoc') {
                $e = EnocOccupation::query()->where('code', $code)->first(['title_ar']);
                $out['title_ar'] = $e?->title_ar;
                $out['title_en'] = IscoGroup::query()->where('code', $code)->value('title_en');
            } else {
                $g = IscoGroup::query()->where('code', $code)->first(['title_en', 'title_ar']);
                $out['title_en'] = $g?->title_en;
                $out['title_ar'] = $g?->title_ar;
            }
        } catch (\Throwable) {
            // The backbone is not loaded yet: the code alone is shown.
        }

        return $out;
    }
}
