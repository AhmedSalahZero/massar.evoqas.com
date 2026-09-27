<?php

namespace App\Models;

use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════════════
//  Massar — SectorProposal: a sub-sector typed as "Other (not in the
//  list)", waiting for the Super Admin (Step 10.5).
//  Location: app/Models/SectorProposal.php · table: …_create_sector_proposals_table.php
// ══════════════════════════════════════════════════════════════════

class SectorProposal extends Model
{
    protected $guarded = ['id'];

    /** A profile's jobs were saved: count each "Other" wording that is new in them. */
    public static function count(array $before, array $after): void
    {
        $keys = fn (array $jobs) => collect($jobs)->filter(fn ($j) => is_array($j) && ! empty($j['sub_sector_other']) && ! empty($j['sector']))
            ->mapWithKeys(fn ($j) => [$j['sector'].'|'.TextNormalizer::normalize($j['sub_sector_other']) => $j])->all();
        $old = $keys($before);
        foreach ($keys($after) as $k => $job) {
            if (isset($old[$k]) || ! in_array($job['sector'], ['IND', 'TRD', 'SRV'], true)) {
                continue;
            }
            [$sector, $key] = explode('|', $k, 2);
            if ($key === '') {
                continue;
            }
            $p = static::query()->firstOrCreate(['sector' => $sector, 'key' => mb_substr($key, 0, 150)], ['label' => mb_substr($job['sub_sector_other'], 0, 150)]);
            $p->increment('times');
        }
    }
}
