<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// ══════════════════════════════════════════════════════════════════
//  Massar — DatabaseSeeder
//  Location: database/seeders/DatabaseSeeder.php
//
//  `php artisan migrate --seed` runs this.
//    SuperAdminSeeder   → always (the one platform account)
//    BackboneSeeder     → always (ISCO-08, ENOC, ESCO from
//                         database/data/backbone — same as
//                         `php artisan backbone:import`, then the
//                         Egypt labour market edition)
//    DemoPartnerSeeder  → everywhere except production
// ══════════════════════════════════════════════════════════════════

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);
        $this->call(BackboneSeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoPartnerSeeder::class);
        }
    }
}
