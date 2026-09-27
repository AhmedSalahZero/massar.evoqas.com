<?php

namespace App\Console\Commands;

use App\Models\Backbone\LabourMarketEdition;
use App\Services\Backbone\MarketImporter;
use Illuminate\Console\Command;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — market:use
//  Location: app/Console/Commands/UseMarketEdition.php
//
//      php artisan market:use 2    show edition #2 everywhere from now on
//      php artisan market:use      list the editions
//
//  Switching back to an older edition works the same way.
// ══════════════════════════════════════════════════════════════════

class UseMarketEdition extends Command
{
    protected $signature = 'market:use {edition? : The edition number}';

    protected $description = 'Choose which Egypt labour market edition is shown everywhere';

    public function handle(): int
    {
        $id = $this->argument('edition');

        if ($id === null) {
            $this->table(['#', 'Edition', 'Reference period', 'Status', 'In use'], LabourMarketEdition::query()->orderBy('id')->get()
                ->map(fn ($e) => [$e->id, $e->name, $e->reference_period.($e->reference_estimated ? ' (estimate)' : ''), $e->status, $e->is_current ? 'YES' : ''])->all());

            return self::SUCCESS;
        }

        $edition = LabourMarketEdition::find($id);
        if (! $edition) {
            $this->error("There is no edition #{$id}. Run `php artisan market:use` to see the list.");

            return self::FAILURE;
        }

        try {
            MarketImporter::make()->makeCurrent($edition);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Edition #{$edition->id} (\"{$edition->name}\") is now shown everywhere.");

        return self::SUCCESS;
    }
}
