<?php

namespace App\Services\Beneficiaries;

// ══════════════════════════════════════════════════════════════════
//  Massar — SalaryCheck (Scope v2 §3 "Egypt Market Panel &
//  Expected-Salary Check")
//  Location: app/Services/Beneficiaries/SalaryCheck.php
//
//  Compares what a beneficiary expects to earn each month with the
//  Egypt labour market figures of their occupation: the average wage
//  and the private-sector average.
//
//      difference = (expected − market) ÷ market
//      below   more than 15% under the figure
//      close   within ±15%            (config beneficiaries.salary_close_pct)
//      above   more than 15% over the figure
//
//  IMPORTANT for anyone reading the result: the market wages are
//  averages from the time of the survey (the edition's data period,
//  2017–2021 in the current edition), before later price rises. The
//  screen always says so next to the result. The figures are never
//  adjusted here — Massar shows the published figures only.
//
//  Figures removed for partners ("under review") are simply not
//  compared. Nothing is compared when a figure is missing.
// ══════════════════════════════════════════════════════════════════

class SalaryCheck
{
    /**
     * @param  array|null  $market  OccupationCatalog::market(..., forPartners: true)
     * @return array{status: string, expected: ?int, close_pct: int, references: list<array>}
     */
    public static function compare(?int $expected, bool $hasOccupation, ?array $market): array
    {
        $close = (int) config('beneficiaries.salary_close_pct', 15);
        $result = ['status' => 'ok', 'expected' => $expected, 'close_pct' => $close, 'references' => []];

        if (! $hasOccupation) {
            return ['status' => 'no_occupation'] + $result;
        }
        if (! $expected) {
            return ['status' => 'no_expected'] + $result;
        }

        $profile = $market['profile'] ?? null;
        foreach (['avg' => 'wage_avg', 'private' => 'wage_private'] as $key => $field) {
            $value = $profile[$field] ?? null;
            if ($value === null || (float) $value <= 0) {
                continue;
            }
            $diff = ($expected - (float) $value) / (float) $value * 100;
            $result['references'][] = [
                'key'      => $key,
                'value'    => (int) round((float) $value),
                'diff_pct' => (int) round($diff),
                'band'     => $diff < -$close ? 'below' : ($diff > $close ? 'above' : 'close'),
            ];
        }

        if ($result['references'] === []) {
            $result['status'] = 'no_wage';
        }

        return $result;
    }
}
