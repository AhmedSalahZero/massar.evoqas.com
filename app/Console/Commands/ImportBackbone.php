<?php

namespace App\Console\Commands;

use App\Models\Backbone\BackboneImport;
use App\Services\Backbone\BackboneImporter;
use Illuminate\Console\Command;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — backbone:import
//  Location: app/Console/Commands/ImportBackbone.php
//
//      php artisan backbone:import           import (skips if the files are unchanged)
//      php artisan backbone:import --force   import even if nothing changed
//
//  Loads ISCO-08, ENOC and ESCO from database/data/backbone into the
//  database (see BackboneImporter). Safe to run as often as you like.
// ══════════════════════════════════════════════════════════════════

class ImportBackbone extends Command
{
    protected $signature = 'backbone:import {--force : Import even if the files have not changed}';

    protected $description = 'Import the occupation backbone (ISCO-08, ENOC, ESCO) from database/data/backbone';

    public function handle(): int
    {
        $this->info('Importing the occupation backbone — this takes up to a minute…');
        $started = microtime(true);

        try {
            $import = BackboneImporter::make()->run((bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('The import stopped. Nothing was changed.');
            $this->line('Reason: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($import->status === BackboneImport::UNCHANGED) {
            $this->info($import->message);
            $this->line('To import anyway: php artisan backbone:import --force');

            return self::SUCCESS;
        }

        $c = $import->counts;
        $this->newLine();
        $this->table(['What', 'Imported'], [
            ['ISCO-08 groups (major · sub-major · minor · unit)', "{$c['isco_major']} · {$c['isco_sub_major']} · {$c['isco_minor']} · {$c['isco_unit']}"],
            ['ENOC major groups', $c['enoc_major']],
            ['ENOC occupations', $c['enoc_occupations']],
            ['ESCO occupations', $c['esco_occupations']],
            ['Searchable titles (Arabic + English)', $c['labels']],
        ]);

        $this->line('Notes on the data:');
        $this->line('  · ISCO-08 unit groups with no ENOC occupation: '.implode(', ', $c['units_without_enoc']));
        $this->line('  · ISCO-08 unit groups with no ESCO occupation: '.implode(', ', $c['units_without_esco']).' (classified at group level only)');
        $this->line("  · Duplicate rows in the ESCO file, skipped: {$c['esco_duplicate_rows_skipped']}");

        $this->newLine();
        $this->info(sprintf('Done in %.1f seconds (import #%d).', microtime(true) - $started, $import->id));

        return self::SUCCESS;
    }
}
