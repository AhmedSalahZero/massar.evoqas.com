<?php

namespace App\Console\Commands;

use App\Services\Cv\CvGoldSet;
use App\Services\Cv\CvReader;
use App\Services\Cv\TextExtractor;
use Illuminate\Console\Command;

// ══════════════════════════════════════════════════════════════════
//  Massar — cv:score (how well the CV reader does on the test CVs)
//  Location: app/Console/Commands/ScoreCvReader.php
//
//      php artisan cv:score
//          Reads every CV in tests/Fixtures/cv-gold/ and compares it with
//          its correct answers:
//              ✗ 07_detail_labels
//                  job 1 employer: null, expected "Americana Foods"
//              Fully correct: 49 of 50 CVs · jobs right: 97 of 98
//
//      php artisan cv:score --only=arabic
//          Only the CVs whose file name contains "arabic".
//
//      php artisan cv:score --add="C:\CVs\Hany Lotfy.pdf"
//          Adds a real CV to the set: the file is copied into the folder
//          and a .json is written with what the reader reads TODAY. Open
//          the .json in Notepad, correct what is wrong, save — from then
//          on every change to the reader is checked against it.
//          ⚠ These are real people's CVs: keep the folder on your
//          computer, do not send it outside your organisation.
// ══════════════════════════════════════════════════════════════════

class ScoreCvReader extends Command
{
    protected $signature = 'cv:score {--only= : only the CVs whose name contains this} {--add= : a PDF or Word CV to add to the set}';

    protected $description = 'Score the CV reader on the test CVs with their correct answers (tests/Fixtures/cv-gold)';

    public function handle(CvReader $reader, TextExtractor $extractor): int
    {
        if ($this->option('add')) {
            return $this->add((string) $this->option('add'), $reader, $extractor);
        }

        $full = 0;
        $count = 0;
        $jobsRight = 0;
        $jobsAll = 0;
        foreach (CvGoldSet::readAll($reader, $extractor, $this->option('only') ?: null) as $name => $case) {
            if ($case['skipped']) {
                $this->warn("… {$name}: skipped — {$case['skipped']}");
                continue;
            }
            $count++;
            $jobsAll += count($case['want']['work'] ?? []);
            $jobsRight += CvGoldSet::jobsRight($case['reading'], $case['want']);
            $problems = CvGoldSet::compare($case['reading'], $case['want']);
            if (! $problems) {
                $full++;
                continue;
            }
            $this->line("<fg=red>✗</> {$name}");
            foreach ($problems as $p) {
                $this->line('    '.$p);
            }
        }
        $this->newLine();
        $this->info("Fully correct: {$full} of {$count} CVs · jobs right: {$jobsRight} of {$jobsAll}");

        return self::SUCCESS;
    }

    private function add(string $path, CvReader $reader, TextExtractor $extractor): int
    {
        $path = trim($path, " \"'");
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! is_file($path) || ! in_array($ext, ['pdf', 'docx'], true)) {
            $this->error('Give the full path of a .pdf or .docx CV, in quotes.');

            return self::FAILURE;
        }
        $x = $extractor->extract($path, $ext);
        if (! empty($x['problem'])) {
            $this->error('This file cannot be read ('.$x['problem'].'). Try `php artisan cv:check` for PDF files.');

            return self::FAILURE;
        }
        $r = $reader->read($x['text'], $x['arabic_ligatures'], $ext === 'pdf');

        $folder = CvGoldSet::folder();
        @mkdir($folder, 0775, true);
        $slug = trim(preg_replace('/[^A-Za-z0-9]+/', '_', pathinfo($path, PATHINFO_FILENAME)) ?? 'cv', '_') ?: 'cv';
        $name = 'real_'.date('Ymd_His').'_'.substr($slug, 0, 40);
        copy($path, "{$folder}/{$name}.{$ext}");

        $want = [
            'about' => 'A real CV added on '.date('Y-m-d').'. CORRECT the values below to what a careful person would type, then save.',
            'work'  => array_map(fn ($j) => [
                'title' => $j['title'], 'employer' => $j['employer'], 'location' => $j['location'] ?? null,
                'from' => $j['from'], 'to' => $j['to'], 'current' => $j['current'],
            ], $r['form']['work_history']),
            'education' => array_map(fn ($e) => ['qualification' => $e['qualification'], 'year' => $e['year']], $r['form']['education']),
            'languages' => array_column($r['form']['languages'], 'code'),
        ];
        file_put_contents("{$folder}/{$name}.json", json_encode($want, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $this->info("Added: tests/Fixtures/cv-gold/{$name}.{$ext}");
        $this->line("Now open tests/Fixtures/cv-gold/{$name}.json in Notepad and correct what the reader got wrong.");
        $this->line('Jobs are listed newest first. Use null for an empty value (no quotes), true / false for "current".');

        return self::SUCCESS;
    }
}
