<?php

namespace App\Services\Beneficiaries;

use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Services\Backbone\OccupationSearch;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — OccupationDisplay
//  Location: app/Services/Beneficiaries/OccupationDisplay.php
//
//  A chosen occupation, ready to show in whichever standard the user
//  picks in the top bar. It is stored once (ESCO job + its unit
//  group); this sends all three readings and the screen shows the
//  one that matches the switch:
//
//      enoc  {code: '2411', title: 'محاسبون'}            null if Egypt does not list the group
//      isco  {code: '2411', title_en, title_ar}
//      esco  {code: '2411.1', title_en, title_ar}       null = "group level only"
//
//  The Arabic ESCO title follows the person's gender where ESCO has
//  both forms (محاسب / محاسبة).
//
//  pick() is the occupation picker's search — the same search as the
//  Occupations page. It offers detailed ESCO jobs, plus the few groups
//  that ESCO does not detail (they can only be chosen as a group).
// ══════════════════════════════════════════════════════════════════

class OccupationDisplay
{
    public function __construct(private readonly OccupationSearch $search) {}

    public static function block(?IscoGroup $unit, ?EscoOccupation $esco, ?string $gender = null): ?array
    {
        if (! $unit) {
            return null;
        }
        $enoc = $unit->relationLoaded('enoc') ? $unit->enoc : $unit->enoc()->first();

        return [
            'unit_code'  => $unit->code,
            'esco_id'    => $esco?->id,
            'group_only' => $esco === null,
            'enoc'       => $enoc ? ['code' => $enoc->code, 'title' => $enoc->title_ar] : null,
            'isco'       => ['code' => $unit->code, 'title_en' => $unit->title_en, 'title_ar' => $unit->title_ar],
            'esco'       => $esco ? [
                'id'       => $esco->id,
                'code'     => $esco->code,
                'title_en' => $esco->title_en,
                'title_ar' => ($gender === 'female' && $esco->title_ar_female) ? $esco->title_ar_female : ($esco->title_ar_male ?: $esco->title_ar),
            ] : null,
        ];
    }

    /**
     * Picker results for a search text, best first.
     *
     * @return list<array{block: array, match: ?string}>
     */
    public function pick(string $text, ?string $gender = null, int $limit = 20): array
    {
        $esco = $this->search->esco($text);
        $units = $this->search->units($text);

        // Groups ESCO does not detail can only be chosen as a group.
        $detailed = $units
            ? DB::table('esco_occupations')->whereIn('isco_group_id', array_keys($units))->where('is_active', true)
                ->distinct()->pluck('isco_group_id')->flip()->all()
            : [];

        $ranked = [];
        foreach ($esco as $id => $m) {
            $ranked[] = ['esco', $id, $m];
        }
        foreach ($units as $id => $m) {
            if (! isset($detailed[$id])) {
                $ranked[] = ['unit', $id, $m];
            }
        }
        usort($ranked, fn ($a, $b) => $a[2]['score'] <=> $b[2]['score']);
        $ranked = array_slice($ranked, 0, $limit);

        $escoModels = EscoOccupation::query()->whereIn('id', array_column(array_filter($ranked, fn ($r) => $r[0] === 'esco'), 1))
            ->where('is_active', true)->with('iscoGroup.enoc')->get()->keyBy('id');
        $unitModels = IscoGroup::query()->whereIn('id', array_column(array_filter($ranked, fn ($r) => $r[0] === 'unit'), 1))
            ->with('enoc')->get()->keyBy('id');

        $out = [];
        foreach ($ranked as [$kind, $id, $m]) {
            $block = $kind === 'esco'
                ? (isset($escoModels[$id]) ? self::block($escoModels[$id]->iscoGroup, $escoModels[$id], $gender) : null)
                : (isset($unitModels[$id]) ? self::block($unitModels[$id], null, $gender) : null);
            if ($block) {
                $out[] = ['block' => $block, 'match' => $m['label']];
            }
        }

        return $out;
    }
}
