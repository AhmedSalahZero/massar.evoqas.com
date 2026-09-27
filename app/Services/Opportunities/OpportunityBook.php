<?php

namespace App\Services\Opportunities;

use App\Models\Backbone\EnocOccupation;
use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Models\Opportunity;
use App\Models\Sector;
use App\Models\User;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Beneficiaries\OccupationDisplay;
use App\Services\Eligibility\RuleBook;

// ══════════════════════════════════════════════════════════════════
//  Massar — OpportunityBook (Step 12 · Jobs & Training)
//  Location: app/Services/Opportunities/OpportunityBook.php
//
//  What the Job and Training screens need beyond the rules engine:
//
//    exists()       is an occupation value ('isco:24', 'enoc:2411',
//                   'esco:2411.1') a real one in the backbone?
//    keys()         every level an opportunity's occupations sit in,
//                   "|isco:2|isco:24|isco:241|isco:2411|enoc:2411|esco:2411.1|",
//                   so the list can be filtered by an occupation at any
//                   level (a job for ESCO 2411.1 is found under ISCO 24)
//    occupations()  each chosen occupation, ready to show: its label as
//                   chosen, and — when it is (inside) a 4-digit group —
//                   the block in all three standards, so the screen can
//                   follow the standard switch in the top bar
//    market()       the Egypt market wages of those groups (for a job's
//                   salary range), published figures only
//    summary() / detail()   the opportunity for the list and its page
// ══════════════════════════════════════════════════════════════════

class OpportunityBook
{
    public function __construct(
        private readonly RuleBook $rules,
        private readonly OccupationCatalog $catalog,
    ) {}

    // ── Occupations ──────────────────────────────────────────────────

    public static function validValue(mixed $v): bool
    {
        return is_string($v) && (bool) preg_match('/^(isco|enoc|esco):[\d.]{1,40}$/', $v);
    }

    public function exists(string $value): bool
    {
        [$std, $code] = array_pad(explode(':', $value, 2), 2, '');
        try {
            return match ($std) {
                'isco'  => IscoGroup::query()->where('code', $code)->exists(),
                'enoc'  => EnocOccupation::query()->where('code', $code)->exists(),
                'esco'  => EscoOccupation::query()->where('code', $code)->exists(),
                default => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }

    /** The 4-digit group an occupation value is (in), or null (an ISCO group above that level). */
    public function unitOf(string $value): ?IscoGroup
    {
        [$std, $code] = array_pad(explode(':', $value, 2), 2, '');
        if ($std === 'esco') {
            $e = EscoOccupation::query()->where('code', $code)->first(['isco_group_id']);

            return $e ? IscoGroup::query()->with('enoc')->find($e->isco_group_id) : null;
        }
        if (strlen($code) !== 4) {
            return null;
        }

        return IscoGroup::query()->with('enoc')->where('code', $code)->first();
    }

    /** "|isco:2|isco:24|…|" — every level the values sit in. */
    public function keys(array $values): string
    {
        $keys = [];
        foreach ($values as $v) {
            [$std, $code] = array_pad(explode(':', $v, 2), 2, '');
            $unit = $std === 'esco' ? $this->unitOf($v)?->code : ($std === 'enoc' ? $code : $code);
            if ($unit) {
                for ($i = 1; $i <= strlen($unit); $i++) {
                    $keys[] = 'isco:'.substr($unit, 0, $i);
                }
                if (strlen($unit) === 4) {
                    $keys[] = 'enoc:'.$unit;
                }
            }
            if ($std === 'esco') {
                $parts = explode('.', $code);
                for ($i = 2; $i <= count($parts); $i++) {
                    $keys[] = 'esco:'.implode('.', array_slice($parts, 0, $i));
                }
            }
        }

        return '|'.implode('|', array_values(array_unique($keys))).'|';
    }

    /** @return list<array{value: string, label: array, block: ?array}> */
    public function occupations(array $values): array
    {
        return array_map(function (string $v) {
            $esco = str_starts_with($v, 'esco:')
                ? EscoOccupation::query()->where('code', substr($v, 5))->first()
                : null;

            return [
                'value' => $v,
                'label' => $this->rules->occupationLabel($v),
                'block' => ($unit = $this->unitOf($v)) ? OccupationDisplay::block($unit, $esco) : null,
            ];
        }, array_values($values));
    }

    /**
     * The Egypt market wages of the 4-digit groups of these occupations
     * (a job's salary range is shown next to them).
     *
     * @return array{edition: ?array, groups: list<array>}
     */
    public function market(array $values): array
    {
        $groups = [];
        $edition = null;
        foreach ($values as $v) {
            $unit = $this->unitOf($v);
            if (! $unit || isset($groups[$unit->code])) {
                continue;
            }
            $m = $this->catalog->market($unit->id, forPartners: true);
            $edition ??= $m['edition'] ?? null;
            $p = $m['profile'] ?? null;
            $avg = isset($p['wage_avg']) && (float) $p['wage_avg'] > 0 ? (int) round((float) $p['wage_avg']) : null;
            $private = isset($p['wage_private']) && (float) $p['wage_private'] > 0 ? (int) round((float) $p['wage_private']) : null;
            if ($avg || $private) {
                $groups[$unit->code] = [
                    'code' => $unit->code, 'title_en' => $unit->title_en, 'title_ar' => $unit->title_ar,
                    'avg' => $avg, 'private' => $private,
                ];
            }
        }

        return ['edition' => $edition, 'groups' => array_values($groups)];
    }

    // ── Presenting ───────────────────────────────────────────────────

    public function summary(Opportunity $o): array
    {
        return [
            'id'            => $o->id,
            'kind'          => $o->kind,
            'section'       => $o->section(),
            'title'         => $o->title,
            'status'        => $o->status,
            'close_reason'  => $o->close_reason,
            'closed_at'     => $o->closed_at?->toIso8601String(),
            'occupations'   => $this->occupations($o->occupations ?? []),
            'governorates'  => $o->governorates ?? [],
            'city'          => $o->city,
            'seats'         => $o->seats,
            'deadline'      => $o->deadline?->toDateString(),
            'deadline_passed' => $o->isOpen() && $o->deadline && $o->deadline->lt(today()),
            'starts_on'     => $o->starts_on?->toDateString(),
            'employer'      => $o->employer,
            'provider'      => $o->provider,
            'eligible_from' => $o->eligible_from,
            'check_from'    => $o->check_from,
            'rules_count'   => count($o->rules ?? []),
            'rules_version' => $o->rules_version,
        ];
    }

    public function detail(Opportunity $o): array
    {
        $contact = $o->contact_user_id ? User::query()->whereKey($o->contact_user_id)->first(['id', 'name', 'email']) : null;

        return $this->summary($o) + [
            'description'    => $o->description,
            'ends_on'        => $o->ends_on?->toDateString(),
            'contact'        => $contact ? ['id' => $contact->id, 'name' => $contact->name, 'email' => $contact->email] : null,
            'contact_user_id' => $o->contact_user_id,
            'employer_id'    => $o->employer_id,
            'sub_sector'     => $o->sub_sector,
            'job_type'       => $o->job_type,
            'salary_from'    => $o->salary_from,
            'salary_to'      => $o->salary_to,
            'duration_value' => $o->duration_value,
            'duration_unit'  => $o->duration_unit,
            'format'         => $o->format,
            'cost_type'      => $o->cost_type,
            'cost_amount'    => $o->cost_amount,
            'certificate'    => $o->certificate,
            'rules'          => $this->rules->present($o->rules ?? []),
            'history'        => array_reverse($o->history ?? []),
            'created_by'     => $o->created_by_name,
            'updated_by'     => $o->updated_by_name,
            'created_at'     => $o->created_at?->toIso8601String(),
            'updated_at'     => $o->updated_at?->toIso8601String(),
        ];
    }

    /** The choices the form, the rule lines and the page show. */
    public function formOptions(int $companyId): array
    {
        return [
            'types'   => RuleBook::TYPES,
            'choices' => collect(config('beneficiaries'))->only([
                'governorates', 'genders', 'military_statuses', 'education_levels', 'languages', 'language_levels',
            ])->all() + [
                'job_types'      => Opportunity::JOB_TYPES,
                'duration_units' => Opportunity::DURATION_UNITS,
                'formats'        => Opportunity::FORMATS,
                'costs'          => Opportunity::COSTS,
                'close_reasons'  => Opportunity::CLOSE_REASONS,
            ],
            'sectors' => Sector::tree(),
            'team'    => User::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
        ];
    }
}
