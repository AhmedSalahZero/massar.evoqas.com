<?php

namespace App\Console\Commands;

use App\Services\Backbone\MarketImporter;
use Illuminate\Console\Command;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — market:import
//  Location: app/Console/Commands/ImportMarket.php
//
//      php artisan market:import                  load the file as a new edition
//      php artisan market:import --make-current   …and use it right away
//
//  Loads the Egypt Occupational Outlook figures (see MarketImporter).
//  The first edition is used automatically. A later one waits until
//  you have reviewed it: php artisan market:use <edition number>
// ══════════════════════════════════════════════════════════════════

class ImportMarket extends Command
{
    protected $signature = 'market:import {--make-current : Use the new edition right away}';

    protected $description = 'Load the Egypt labour market figures as a new edition';

    public function handle(): int
    {
        $this->info('Loading the Egypt labour market figures…');

        try {
            ['edition' => $e, 'unchanged' => $unchanged] = MarketImporter::make()->run((bool) $this->option('make-current'));
        } catch (Throwable $ex) {
            $this->newLine();
            $this->error('The import stopped. Nothing was changed.');
            $this->line('Reason: '.$ex->getMessage());

            return self::FAILURE;
        }

        if ($unchanged) {
            $this->info("This file is already loaded as edition #{$e->id} (\"{$e->name}\"). Nothing was changed.");

            return self::SUCCESS;
        }

        $c = $e->counts;
        $this->newLine();
        $this->table(['Figures', 'Occupations (of '.$c['occupations'].')'], [
            ['Workers, growth, women, formal, public/private, sectors', $c['workers']],
            ['Average monthly wage', $c['wages']],
            ['Wage for women', $c['wages_gender']],
            ['Wage public / private', $c['wages_sector']],
            ['Required education · skill groups', $c['education'].' · '.$c['skill_groups']],
            ['Top 5 knowledge, abilities, skills', $c['knowledge']],
            ['Outlook to 2030', $c['outlook']],
            ['Green transition', $c['green']],
        ]);

        $q = $e->quality;
        $this->line('Findings about the file:');
        if ($q['regions_outside_cairo_identical']) {
            $this->warn('  · The 6 regions outside Greater Cairo have the same value for every occupation — probably an error in the file. They are flagged.');
        }
        foreach ($q['flagged'] as $what => $codes) {
            $this->warn("  · Figures to check ({$what}): ".implode(', ', $codes));
        }
        $this->line('  · The two copies of the wages in the file '.($q['wage_copies_disagree'] ? 'DISAGREE for: '.implode(', ', $q['wage_copies_disagree']) : 'agree for every occupation.'));

        if ($e->comparison) {
            $cmp = $e->comparison;
            $this->newLine();
            $this->line("Compared with edition #{$cmp['against_edition']}: ".count($cmp['added']).' occupations added, '.count($cmp['dropped']).' dropped, '.count($cmp['big_changes'])." big changes ({$cmp['big_change_rule']}).");
        }

        $this->newLine();
        if ($e->is_current) {
            $this->info("Done. Edition #{$e->id} is now the one shown everywhere.");
        } else {
            $this->info("Done. Edition #{$e->id} is loaded but NOT yet shown. Review it on the Occupation backbone page and press \"Use this edition\" (or run: php artisan market:use {$e->id}).");
        }

        return self::SUCCESS;
    }
}
