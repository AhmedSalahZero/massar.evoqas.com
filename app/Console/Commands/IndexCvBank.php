<?php

namespace App\Console\Commands;

use App\Services\Cv\CvBankIndex;
use Illuminate\Console\Command;

// ══════════════════════════════════════════════════════════════════
//  Massar — cvbank:index (Scope v2 §3 Searchable CV Bank)
//  Location: app/Console/Commands/IndexCvBank.php
//
//      php artisan cvbank:index
//
//  Builds the search index for every profile — needed once, after
//  installing Step 8, for the profiles registered before. After that
//  the index follows every change by itself. Safe to run again.
// ══════════════════════════════════════════════════════════════════

class IndexCvBank extends Command
{
    protected $signature = 'cvbank:index';

    protected $description = 'Build the Searchable CV Bank index for every profile';

    public function handle(CvBankIndex $index): int
    {
        CvBankIndex::forget();
        if (! CvBankIndex::ready()) {
            $this->error('The CV Bank table is missing. Run `php artisan migrate` first.');

            return self::FAILURE;
        }
        $n = $index->refreshAll(null, fn (int $done) => $this->line("  {$done} profiles…"));
        $this->info("Done: {$n} profiles can now be found in the CV Bank.");

        return self::SUCCESS;
    }
}
