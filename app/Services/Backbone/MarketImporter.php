<?php

namespace App\Services\Backbone;

use App\Models\Backbone\LabourMarketEdition;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — MarketImporter (Egypt labour market editions)
//  Location: app/Services/Backbone/MarketImporter.php
//
//  Loads the Egypt Occupational Outlook figures as a new EDITION.
//  Run by `php artisan market:import` (and BackboneSeeder).
//
//  · Needs the occupation backbone first (the figures hang on ENOC).
//  · The same file (same checksum) is never loaded twice.
//  · A new edition never touches an older one. The first edition
//    becomes current automatically; a later one only when asked
//    (--make-current, the "Use this edition" button on the admin
//    backbone page, or `php artisan market:use <id>` after review).
//  · Before switching, it is compared with the current edition:
//    occupations added / dropped, and big jumps in workers or wages.
//  · All-or-nothing: a failure leaves the database as it was and is
//    recorded on the edition with a plain-language reason.
// ══════════════════════════════════════════════════════════════════

class MarketImporter
{
    /** A change bigger than this share is listed for review. */
    private const BIG_CHANGE = 0.5;

    public function __construct(private readonly MarketFile $file) {}

    public static function make(): self
    {
        return new self(MarketFile::fromConfig());
    }

    /**
     * @return array{edition: LabourMarketEdition, unchanged: bool}
     */
    public function run(bool $makeCurrent = false, ?int $userId = null): array
    {
        $sha = $this->file->sha256();

        $same = LabourMarketEdition::query()->where('sha256', $sha)->where('status', LabourMarketEdition::COMPLETED)->first();
        if ($same) {
            return ['edition' => $same, 'unchanged' => true];
        }

        $enocIds = DB::table('enoc_occupations')->where('is_active', true)->pluck('id', 'code')->all();
        if (! $enocIds) {
            throw new RuntimeException('The occupation backbone is empty. Run `php artisan backbone:import` first.');
        }
        $unitIds = DB::table('enoc_occupations')->pluck('isco_group_id', 'code')->all();

        $edition = LabourMarketEdition::create([
            'name'             => config('backbone.market.edition'),
            'name_ar'          => config('backbone.market.edition_ar'),
            'reference_period' => config('backbone.market.reference_period'),
            'reference_estimated' => (bool) config('backbone.market.reference_is_estimate', false),
            'source'           => config('backbone.market.source'),
            'file_name'        => $this->file->fileName(),
            'sha256'           => $sha,
            'status'           => LabourMarketEdition::RUNNING,
            'user_id'          => $userId,
        ]);

        try {
            $data = $this->file->read();

            $unknownCodes = array_keys(array_diff_key($data['rows'], $enocIds));
            if ($unknownCodes) {
                throw new RuntimeException('These codes are not ENOC occupations in the backbone: '.implode(', ', array_slice($unknownCodes, 0, 20)).'. Nothing was imported.');
            }

            $current = LabourMarketEdition::current();
            $becomesCurrent = $makeCurrent || ! $current;

            DB::transaction(function () use ($edition, $data, $enocIds, $unitIds, $current, $becomesCurrent) {
                $rows = [];
                foreach ($data['rows'] as $code => $r) {
                    $rows[] = $this->profileRow($edition->id, $enocIds[$code], $unitIds[$code], $r);
                }
                foreach (array_chunk($rows, (int) config('backbone.chunk', 500)) as $chunk) {
                    DB::table('enoc_market_profiles')->insert($chunk);
                }

                $edition->update([
                    'status'     => LabourMarketEdition::COMPLETED,
                    'counts'     => $this->coverage($data['rows']),
                    'quality'    => $data['quality'],
                    'comparison' => $current ? $this->compare($current, $data['rows']) : null,
                ]);

                if ($becomesCurrent) {
                    $this->makeCurrent($edition);
                }
            });
        } catch (Throwable $e) {
            $edition->update(['status' => LabourMarketEdition::FAILED, 'message' => mb_substr($e->getMessage(), 0, 2000)]);

            throw $e;
        }

        return ['edition' => $edition->refresh(), 'unchanged' => false];
    }

    public function makeCurrent(LabourMarketEdition $edition, ?int $userId = null): void
    {
        if ($edition->status !== LabourMarketEdition::COMPLETED) {
            throw new RuntimeException("Edition #{$edition->id} did not import completely and cannot be used.");
        }

        DB::transaction(function () use ($edition, $userId) {
            LabourMarketEdition::query()->where('id', '<>', $edition->id)->update(['is_current' => false]);
            $edition->update(['is_current' => true, 'made_current_at' => now(), 'made_current_by' => $userId]);
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function profileRow(int $editionId, int $enocId, int $unitId, array $r): array
    {
        $json = fn ($v) => $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE);

        return [
            'edition_id' => $editionId, 'enoc_occupation_id' => $enocId, 'isco_group_id' => $unitId,
            'workers' => $r['workers'], 'workers_trend' => $r['workers_trend'], 'share_of_employment' => $r['share_of_employment'],
            'pct_paid' => $r['pct_paid'], 'pct_unpaid' => $r['pct_unpaid'], 'pct_formal' => $r['pct_formal'],
            'pct_regular' => $r['pct_regular'], 'pct_women' => $r['pct_women'],
            'pct_public' => $r['pct_public'], 'pct_private' => $r['pct_private'],
            'sectors' => $json($r['sectors']), 'regions' => $json($r['regions']),
            'wage_avg' => $r['wage_avg'], 'wage_male' => $r['wage_male'], 'wage_female' => $r['wage_female'],
            'wage_public' => $r['wage_public'], 'wage_private' => $r['wage_private'], 'weekly_hours' => $r['weekly_hours'],
            'education' => $r['education'], 'knowledge' => $json($r['knowledge']), 'abilities' => $json($r['abilities']),
            'skills' => $json($r['skills']), 'skill_groups' => $json($r['skill_groups']),
            'outlook_trend' => $r['outlook_trend'], 'outlook_jobs' => $r['outlook_jobs'], 'green' => $r['green'],
            'flags' => $r['flags'] ? $json($r['flags']) : null,
        ];
    }

    /** How many occupations have each kind of figure. */
    private function coverage(array $rows): array
    {
        $has = fn (string $f) => count(array_filter($rows, fn ($r) => $r[$f] !== null));

        return [
            'occupations'  => count($rows),
            'workers'      => $has('workers'),
            'wages'        => $has('wage_avg'),
            'wages_gender' => $has('wage_female'),
            'wages_sector' => $has('wage_public'),
            'hours'        => $has('weekly_hours'),
            'sectors'      => $has('sectors'),
            'regions'      => $has('regions'),
            'education'    => $has('education'),
            'knowledge'    => $has('knowledge'),
            'skill_groups' => $has('skill_groups'),
            'outlook'      => $has('outlook_trend'),
            'green'        => $has('green'),
        ];
    }

    /** What changed compared with the edition that is current now. */
    private function compare(LabourMarketEdition $current, array $rows): array
    {
        $old = DB::table('enoc_market_profiles')
            ->join('enoc_occupations', 'enoc_occupations.id', '=', 'enoc_market_profiles.enoc_occupation_id')
            ->where('edition_id', $current->id)
            ->get(['enoc_occupations.code', 'workers', 'wage_avg'])->keyBy('code');

        $jumps = [];
        foreach ($rows as $code => $r) {
            $o = $old[$code] ?? null;
            foreach (['workers', 'wage_avg'] as $f) {
                if ($o && $o->{$f} && $r[$f] !== null && abs($r[$f] - $o->{$f}) / $o->{$f} > self::BIG_CHANGE) {
                    $jumps[] = ['code' => (string) $code, 'field' => $f, 'from' => (int) $o->{$f}, 'to' => $r[$f]];
                }
            }
        }

        $newCodes = array_map('strval', array_keys($rows));
        $oldCodes = array_map('strval', $old->keys()->all());

        return [
            'against_edition' => $current->id,
            'added'           => array_values(array_diff($newCodes, $oldCodes)),
            'dropped'         => array_values(array_diff($oldCodes, $newCodes)),
            'big_changes'     => array_slice($jumps, 0, 200),
            'big_change_rule' => 'more than '.(self::BIG_CHANGE * 100).'% up or down',
        ];
    }
}
