<?php

namespace App\Console\Commands;

use App\Services\Employers\EmployerImporter;
use Illuminate\Console\Command;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — employers:import (Step 10.5)
//  Location: app/Console/Commands/ImportEmployers.php
//
//      php artisan employers:import
//      php artisan employers:import "C:\Data\employers.xlsx"
//
//  Loads the sectors and the companies from the Massar employers file
//  (default: database/data/employers/employers.xlsx). Run it again
//  after adding companies to the file: it adds and updates, never
//  duplicates or deletes. See EmployerImporter.
// ══════════════════════════════════════════════════════════════════

class ImportEmployers extends Command
{
    protected $signature = 'employers:import {file? : The Excel file (default: database/data/employers/employers.xlsx)}';

    protected $description = 'Load the business sectors and the list of companies from the Massar employers Excel file';

    public function handle(EmployerImporter $importer): int
    {
        $this->info('Loading sectors and companies…');
        try {
            $r = $importer->run($this->argument('file'));
        } catch (Throwable $e) {
            $this->error('The import stopped. Nothing was changed.');
            $this->line('Reason: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->line("Sub-sectors: {$r['sectors']}");
        $this->line("Companies added: {$r['added']} · updated: {$r['updated']}");
        foreach ($r['skipped'] as $s) {
            $this->warn('Not loaded — '.$s);
        }
        $this->info('Done.');

        return self::SUCCESS;
    }
}
