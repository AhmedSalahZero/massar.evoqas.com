<?php

namespace App\Console\Commands;

use App\Models\Backbone\SkillImport;
use App\Services\Backbone\SkillImporter;
use Illuminate\Console\Command;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — skills:import
//  Location: app/Console/Commands/ImportSkills.php
//
//      php artisan skills:import           import (skips if the files are unchanged)
//      php artisan skills:import --force   import even if nothing changed
//
//  Loads the ESCO skills pillar from database/data/backbone/esco and
//  links it to the ESCO occupations (see SkillImporter). Run it after
//  backbone:import. Safe to run as often as you like.
// ══════════════════════════════════════════════════════════════════

class ImportSkills extends Command
{
    protected $signature = 'skills:import {--force : Import even if the files have not changed}';

    protected $description = 'Import the ESCO skills and knowledge (and which occupations need them) from database/data/backbone/esco';

    public function handle(): int
    {
        $this->info('Importing the ESCO skills — this takes up to two minutes…');
        $started = microtime(true);

        try {
            $import = SkillImporter::make()->run((bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('The import stopped. Nothing was changed.');
            $this->line('Reason: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($import->status === SkillImport::UNCHANGED) {
            $this->info($import->message);
            $this->line('To import anyway: php artisan skills:import --force');

            return self::SUCCESS;
        }

        $c = $import->counts;
        $n = fn ($v) => number_format((int) $v);
        $this->newLine();
        $this->table(['What', 'Imported'], [
            ['Skills and knowledge', $n($c['skills'])],
            ['   of which skills · knowledge', $n($c['skills_by_type']['skill'] ?? 0).' · '.$n($c['skills_by_type']['knowledge'] ?? 0)],
            ['Skill groups (the skills tree)', $n($c['groups'])],
            ['Occupation ↔ skill links (essential · optional)', $n($c['occupation_links']).' ('.$n($c['occupation_links_essential']).' · '.$n($c['occupation_links_optional']).')'],
            ['ESCO occupations with skills', $n($c['occupations_with_skills'])],
            ['Skill ↔ skill links', $n($c['related_links'])],
            ['Searchable skill names (Arabic · English)', $n($c['labels']).' ('.$n($c['labels_ar']).' · '.$n($c['labels_en']).')'],
        ]);

        $this->line('Notes on the data:');
        $this->line("  · Duplicate skill rows in the ESCO file, newest kept: {$c['skills_duplicate_rows_skipped']}");
        $this->line("  · Knowledge groups with no Arabic name in ESCO: {$c['groups_without_arabic']} (shown in English)");
        $this->line("  · Skills whose Arabic name is in Latin letters (product names such as \"Apache Maven\"): {$c['skills_arabic_in_latin']}");
        $this->line("  · Skills no occupation uses: {$n($c['skills_without_occupation'])}");
        if ($c['occupations_without_skills_count']) {
            $this->line("  · ESCO occupations with no skills listed: {$c['occupations_without_skills_count']}");
        }

        $this->newLine();
        $this->info(sprintf('Done in %.1f seconds (skills import #%d).', microtime(true) - $started, $import->id));

        return self::SUCCESS;
    }
}
