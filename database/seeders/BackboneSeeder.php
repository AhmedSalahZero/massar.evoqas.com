<?php

namespace Database\Seeders;

use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use App\Services\Backbone\SkillImporter;
use Illuminate\Database\Seeder;

// ══════════════════════════════════════════════════════════════════
//  Massar — BackboneSeeder
//  Location: database/seeders/BackboneSeeder.php
//
//  Loads the occupation backbone (ISCO-08, ENOC, ESCO) on a fresh
//  install, so `php artisan migrate --seed` gives a working app in
//  one step. Same as running `php artisan backbone:import`, then
//  `php artisan skills:import` (ESCO skills) and
//  `php artisan market:import` (Egypt labour market figures).
// ══════════════════════════════════════════════════════════════════

class BackboneSeeder extends Seeder
{
    public function run(): void
    {
        $import = BackboneImporter::make()->run();

        $this->command?->info(sprintf(
            'Occupation backbone ready: %d ENOC · %d ISCO-08 groups · %d ESCO occupations.',
            $import->counts['enoc_occupations'] ?? 0,
            ($import->counts['isco_major'] ?? 0) + ($import->counts['isco_sub_major'] ?? 0) + ($import->counts['isco_minor'] ?? 0) + ($import->counts['isco_unit'] ?? 0),
            $import->counts['esco_occupations'] ?? 0,
        ));

        $skills = SkillImporter::make()->run();
        $this->command?->info(sprintf(
            'ESCO skills ready: %d skills and knowledge · %d occupation links.',
            $skills->counts['skills'] ?? 0,
            $skills->counts['occupation_links'] ?? 0,
        ));

        $market = MarketImporter::make()->run()['edition'];
        $this->command?->info("Egypt labour market ready: edition #{$market->id} ({$market->counts['occupations']} occupations).");
    }
}
