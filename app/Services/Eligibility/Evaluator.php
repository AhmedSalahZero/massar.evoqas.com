<?php

namespace App\Services\Eligibility;

use App\Models\Beneficiary;
use App\Models\EligibilityAssessment as A;
use App\Models\Opportunity;
use App\Support\TextNormalizer;

// ══════════════════════════════════════════════════════════════════
//  Massar — Evaluator (Step 11 · Eligibility Assessment)
//  Location: app/Services/Eligibility/Evaluator.php
//
//  Checks one profile against one job's or training's rules, with no database
//  writes. Each rule gives one line:
//
//    pass      ✓  the rule is met
//    fail      ✗  the rule is not met
//    missing   ?  the profile does not hold what the rule needs
//                 (no date of birth, no skills …) — NEVER guessed
//    na        –  the rule does not apply to this person (the military
//                 rule for women); counted as met
//
//  The score is out of 100: the points of the "Counts" rules met
//  (an opportunity with no "Counts" rule scores 100). The result:
//
//    a "Must have" rule fails      → not_eligible   (whatever the score)
//    otherwise, something missing  → check          (whatever the score)
//    otherwise  score ≥ eligible_from → eligible
//               score ≥ check_from    → check
//               lower                 → not_eligible
// ══════════════════════════════════════════════════════════════════

class Evaluator
{
    /**
     * @return array{score: int, result: string, reasons: list<array>, hash: string}
     */
    public function evaluate(Opportunity $opportunity, Beneficiary $b): array
    {
        $reasons = [];
        $score = 0;
        $hasCounts = false;
        $mustFailed = false;
        $missing = false;

        foreach ($opportunity->rules ?? [] as $rule) {
            [$status, $value] = $this->check($rule, $b);
            $reasons[] = ['rule' => $rule, 'status' => $status, 'value' => $value];

            $met = $status === 'pass' || $status === 'na';
            if ($rule['mode'] === 'counts') {
                $hasCounts = true;
                if ($met) {
                    $score += (int) $rule['points'];
                }
            } elseif ($status === 'fail') {
                $mustFailed = true;
            }
            if ($status === 'missing') {
                $missing = true;
            }
        }

        $score = $hasCounts ? min(100, $score) : 100;
        $result = match (true) {
            $mustFailed                        => A::NOT_ELIGIBLE,
            $missing                           => A::CHECK,
            $score >= $opportunity->eligible_from  => A::ELIGIBLE,
            $score >= $opportunity->check_from     => A::CHECK,
            default                            => A::NOT_ELIGIBLE,
        };

        return ['score' => $score, 'result' => $result, 'reasons' => $reasons, 'hash' => self::inputsHash($b)];
    }

    /** @return array{0: string, 1: mixed} status and what the profile holds */
    private function check(array $rule, Beneficiary $b): array
    {
        switch ($rule['type']) {
            case 'age':
                $age = $b->date_of_birth?->age;
                if ($age === null) {
                    return ['missing', null];
                }
                $ok = ($rule['min'] === null || $age >= $rule['min']) && ($rule['max'] === null || $age <= $rule['max']);

                return [$ok ? 'pass' : 'fail', $age];

            case 'governorate':
                return [in_array($b->governorate, $rule['values'], true) ? 'pass' : 'fail', $b->governorate];

            case 'education_level':
                if (! $b->education_level) {
                    return ['missing', null];
                }

                return [in_array($b->education_level, $rule['values'], true) ? 'pass' : 'fail', $b->education_level];

            case 'field_of_study':
                $texts = [];
                foreach ($b->education ?? [] as $e) {
                    foreach (['field', 'qualification'] as $k) {
                        if (is_array($e) && ! empty($e[$k]) && is_string($e[$k])) {
                            $texts[] = $e[$k];
                        }
                    }
                }
                if ($texts === []) {
                    return ['missing', null];
                }
                $hay = ' '.TextNormalizer::normalize(implode(' | ', $texts)).' ';
                foreach ($rule['words'] as $w) {
                    $n = TextNormalizer::normalize($w);
                    if ($n !== '' && str_contains($hay, $n)) {
                        return ['pass', $w];
                    }
                }

                return ['fail', $texts[0]];

            case 'skills':
                $have = array_map(fn ($s) => TextNormalizer::normalize((string) $s), $b->skills ?? []);
                $have = array_values(array_filter($have));
                if ($have === []) {
                    return ['missing', null];
                }
                $found = [];
                $lacking = [];
                foreach ($rule['values'] as $want) {
                    $n = TextNormalizer::normalize($want);
                    $hit = $n !== '' && (bool) array_filter($have, fn ($h) => $h === $n || str_contains(' '.$h.' ', ' '.$n.' '));   // whole words: "excel" is not in "excellence"
                    $hit ? $found[] = $want : $lacking[] = $want;
                }
                $ok = $rule['need'] === 'any' ? $found !== [] : $lacking === [];

                return [$ok ? 'pass' : 'fail', ['found' => $found, 'lacking' => $lacking]];

            case 'occupation':
                if (! $b->isco_code) {
                    return ['missing', null];
                }
                $esco = $b->relationLoaded('escoOccupation') ? $b->escoOccupation?->code
                    : ($b->esco_occupation_id ? \App\Models\Backbone\EscoOccupation::query()->whereKey($b->esco_occupation_id)->value('code') : null);
                foreach ($rule['values'] as $v) {
                    [$std, $code] = explode(':', $v, 2);
                    if ($std === 'esco') {
                        if ($esco && ($esco === $code || str_starts_with($esco, $code.'.'))) {
                            return ['pass', $esco];
                        }
                    } elseif (str_starts_with((string) $b->isco_code, substr(preg_replace('/\D/', '', $code), 0, 4))) {
                        return ['pass', $esco ?: $b->isco_code];
                    }
                }

                return ['fail', $esco ?: $b->isco_code];

            case 'experience':
                if (empty($b->work_history)) {
                    return ['missing', null];
                }
                $months = (int) $b->experience_months;

                return [$months >= $rule['min_years'] * 12 ? 'pass' : 'fail', $months];

            case 'language':
                $langs = array_values(array_filter($b->languages ?? [], 'is_array'));
                if ($langs === []) {
                    return ['missing', null];
                }
                foreach ($langs as $l) {
                    if (($l['code'] ?? null) === $rule['code']) {
                        $ok = (RuleBook::LANGUAGE_RANK[$l['level'] ?? ''] ?? 0) >= RuleBook::LANGUAGE_RANK[$rule['level']];

                        return [$ok ? 'pass' : 'fail', $l['level'] ?? null];
                    }
                }

                return ['fail', null];

            case 'military':
                if ($b->gender !== 'male') {
                    return ['na', null];
                }
                if (! $b->military_status) {
                    return ['missing', null];
                }

                return [in_array($b->military_status, $rule['values'], true) ? 'pass' : 'fail', $b->military_status];

            case 'gender':
                return [$b->gender === $rule['value'] ? 'pass' : 'fail', $b->gender];

            case 'salary':
                if (! $b->expected_salary) {
                    return ['missing', null];
                }

                return [(int) $b->expected_salary <= $rule['max'] ? 'pass' : 'fail', (int) $b->expected_salary];
        }

        return ['missing', null];
    }

    /**
     * A fingerprint of every profile detail a rule can read. When it
     * changes, the results are worked out again (and a case worker's
     * decision is marked "the profile changed since this decision").
     */
    public static function inputsHash(Beneficiary $b): string
    {
        return sha1(json_encode([
            $b->gender, $b->date_of_birth?->format('Y-m-d'), $b->governorate, $b->education_level,
            array_map(fn ($e) => is_array($e) ? [$e['field'] ?? null, $e['qualification'] ?? null] : null, $b->education ?? []),
            array_values($b->skills ?? []), array_values($b->languages ?? []),
            $b->isco_code, $b->esco_occupation_id, (int) $b->experience_months, ! empty($b->work_history),
            $b->military_status, $b->expected_salary,
        ]));
    }
}
