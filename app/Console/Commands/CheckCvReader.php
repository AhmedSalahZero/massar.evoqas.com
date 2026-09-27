<?php

namespace App\Console\Commands;

use App\Services\Cv\TextExtractor;
use Dompdf\Dompdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — cv:check
//  Location: app/Console/Commands/CheckCvReader.php
//
//      php artisan cv:check
//
//  Checks, in plain words, that PDF CVs can be read on this computer:
//    1. which pdftotext program is set (PDFTOTEXT_PATH in .env)
//    2. that it starts (its version)
//    3. that it reads a small sample CV made on the spot
//  If step 3 fails it prints Poppler's own message — send that line
//  to the developer.
// ══════════════════════════════════════════════════════════════════

class CheckCvReader extends Command
{
    protected $signature = 'cv:check';

    protected $description = 'Check that PDF CVs can be read on this computer (Poppler pdftotext)';

    public function handle(TextExtractor $extractor): int
    {
        $bin = (string) config('cv.pdftotext');
        $this->line('1. pdftotext set to: <comment>'.$bin.'</comment>');
        if ($bin === 'pdftotext') {
            $this->warn('   PDFTOTEXT_PATH is not set in .env (or `php artisan config:clear` was not run after adding it).');
            $this->warn('   Add this line at the end of .env:  PDFTOTEXT_PATH=C:/poppler/Library/bin/pdftotext.exe');
        }
        if (DIRECTORY_SEPARATOR === '\\' && ! str_contains($bin, ':')) {
            $this->warn('   This is not a full path. Put the full path in .env, e.g. PDFTOTEXT_PATH=C:/poppler/Library/bin/pdftotext.exe');
        }
        if (str_contains($bin, ':') && ! is_file(str_replace('/', DIRECTORY_SEPARATOR, $bin))) {
            $this->error('   No file at that path. Check the folder and the spelling in .env, then run: php artisan config:clear');

            return self::FAILURE;
        }

        try {
            $v = Process::timeout(10)->run([str_replace('/', DIRECTORY_SEPARATOR, $bin), '-v']);
            $version = trim(strtok($v->errorOutput().$v->output(), "\n") ?: '');
        } catch (Throwable $e) {
            $version = '';
            $this->error('2. It could not be started: '.$e->getMessage());

            return self::FAILURE;
        }
        if (! str_contains(strtolower($version), 'pdftotext version')) {
            $this->error('2. It does not start: '.($version ?: '(no answer)'));
            $this->line('   Check PDFTOTEXT_PATH in .env, then run: php artisan config:clear');

            return self::FAILURE;
        }
        $this->line('2. It starts: <info>'.$version.'</info>');

        $dompdf = new Dompdf;
        $dompdf->loadHtml('<p>Ahmed Hassan Mahmoud</p><p>Senior Accountant</p><p>Cairo | 01005551234 | ahmed@example.com</p>'
            .'<p>WORK EXPERIENCE</p><p>Accountant - Delta Trading | Mar 2021 - Present</p><p>Prepared the monthly financial statements for the company</p>');
        $dompdf->render();
        $sample = tempnam(sys_get_temp_dir(), 'cvchk');
        file_put_contents($sample, $dompdf->output());

        $result = $extractor->extract($sample, 'pdf');
        @unlink($sample);

        if ($result['problem'] === null && str_contains($result['text'], 'Ahmed Hassan Mahmoud')) {
            $this->line('3. It reads a sample PDF CV: <info>yes ✓</info>');
            $this->newLine();
            $this->info('PDF CVs can be read on this computer.');

            return self::SUCCESS;
        }

        $this->error('3. It could NOT read the sample PDF CV (result: '.($result['problem'] ?? 'text not found').').');
        $this->line('   Poppler said: '.($extractor->lastError ?: '(nothing)'));
        $this->line('   Please send these lines to the developer.');

        return self::FAILURE;
    }
}
