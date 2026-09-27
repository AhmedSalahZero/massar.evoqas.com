<?php

namespace App\Services\Cv;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

// ══════════════════════════════════════════════════════════════════
//  Massar — TextExtractor
//  Location: app/Services/Cv/TextExtractor.php
//  Scope v2 §3 CV Reading Engine · §7 CV Processing Pipeline
//
//  Gets the plain text out of one CV file. No AI, no internet.
//
//      ->extract($path, 'pdf')  → ['text' => '…', 'problem' => null]
//      ->extract($path, 'doc')  → ['text' => '',  'problem' => 'old_word']
//
//  PDF   read with Poppler's pdftotext (config/cv.php → pdftotext),
//        from a plainly-named copy in storage/app/private/cv-work, with the
//        text written to a file there (both deleted straight after).
//        If Poppler fails, its own message is kept in $lastError and in
//        the log, and `php artisan cv:check` shows it.
//        Two-column English CVs are also read in Poppler's "raw" mode and
//        the better-structured reading is kept (see pdf()).
//  DOCX  a .docx file is a zip; the text is in word/document.xml.
//        Paragraphs and table cells become lines; headers and text
//        boxes are included.
//  DOC   the old Word format (before 2007) cannot be read → the person
//        is asked to save it as .docx or PDF.
//
//  'problem' is one of:
//      scanned        almost no text: a picture of a CV (needs OCR, later)
//      locked         the PDF is password-protected
//      old_word       an old .doc file
//      broken         the file is damaged or not really a PDF/Word file
//      no_pdf_reader  pdftotext is not installed / PDFTOTEXT_PATH is wrong
//
//  Arabic PDFs: many PDF makers store Arabic with invisible direction
//  marks, and some write the letter pair "لا" backwards ("الميلاد"
//  comes out as "الميالد"). The marks are removed here; the safe
//  cases of the "لا" problem are repaired (see ArabicRepair), and
//  'arabic_ligatures' => true tells the reader to ask a person to
//  check Arabic names.
// ══════════════════════════════════════════════════════════════════

class TextExtractor
{
    /** @return array{text: string, problem: ?string, arabic_ligatures: bool} */
    public function extract(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        $result = match ($extension) {
            'pdf'   => $this->pdf($path),
            'docx'  => $this->docx($path),
            'doc'   => ['', 'old_word'],
            default => ['', 'broken'],
        };
        [$text, $problem] = $result;

        $text = $this->tidy($text);
        [$text, $ligatures] = ArabicRepair::repair($text);

        if ($problem === null && $this->letters($text) < (int) config('cv.min_letters', 60)) {
            $problem = $extension === 'pdf' ? 'scanned' : 'broken';
        }

        return ['text' => $problem ? '' : $text, 'problem' => $problem, 'arabic_ligatures' => $ligatures];
    }

    /** True when pdftotext can be run (used by the upload page to warn early). */
    public function pdfReaderReady(): bool
    {
        try {
            $r = Process::timeout(10)->run([$this->binary(), '-v']);

            // "pdftotext version 26.09.0" — not just the word "pdftotext":
            // Windows' "'pdftotext' is not recognized…" contains that word too.
            return str_contains(strtolower($r->output().$r->errorOutput()), 'pdftotext version');
        } catch (Throwable) {
            return false;
        }
    }

    // ── PDF ──────────────────────────────────────────────────────────

    /** What went wrong with the last PDF, in Poppler's own words (for the log and `php artisan cv:check`). */
    public ?string $lastError = null;

    /** @return array{0: string, 1: ?string} */
    private function pdf(string $path): array
    {
        $this->lastError = null;
        $head = @file_get_contents($path, false, null, 0, 1024) ?: '';
        if (! str_contains($head, '%PDF')) {
            $this->lastError = 'The file does not start like a PDF.';

            return ['', 'broken'];
        }

        // Poppler is given a copy with a plain name in the app's own private
        // folder, and writes the text to a file next to it (not to a pipe):
        // this avoids Windows problems with unusual temp-folder names,
        // spaces, and reading Arabic through a console pipe.
        $dir = storage_path('app/private/cv-work');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $base = $dir.DIRECTORY_SEPARATOR.Str::random(20);
        $in = $base.'.pdf';
        if (! @copy($path, $in)) {
            $this->lastError = 'Could not make a working copy in '.$dir.' (check that the folder can be written).';

            return ['', 'broken'];
        }

        try {
            // -enc UTF-8  Arabic comes out as real letters
            // -nopgbrk    no page-break characters
            // -q          no warnings mixed into the text
            // no -layout  keeps two-column CVs in reading order
            $normal = $this->runPoppler(['-q', '-enc', 'UTF-8', '-nopgbrk'], $in, $base.'.txt');
            if ($normal['text'] === null) {
                return ['', $this->classify($normal)];
            }
            $text = $normal['text'];

            // Two-column CVs: Poppler's normal mode can mix the columns line by
            // line. Its "raw" mode keeps each column together, but reverses
            // Arabic word order — so it is only tried on mostly-Latin CVs, and
            // kept only when its sections come out clearly better.
            if (preg_match_all('/\p{Arabic}/u', $text) < preg_match_all('/[A-Za-z]/', $text) * 0.3) {
                $raw = $this->runPoppler(['-q', '-raw', '-enc', 'UTF-8', '-nopgbrk'], $in, $base.'-raw.txt');
                $reader = app(CvReader::class);
                if ($raw['text'] !== null && $reader->structureScore($raw['text']) > $reader->structureScore($text)) {
                    $text = $raw['text'];
                }
            }

            return [$text, null];
        } finally {
            foreach ([$in, $base.'.txt', $base.'-raw.txt'] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
        }
    }

    /** @return array{text: ?string, code: ?int, error: string, crashed: bool} */
    private function runPoppler(array $options, string $in, string $out): array
    {
        try {
            $r = Process::timeout((int) config('cv.timeout', 30))
                ->path(dirname($in))
                ->run([$this->binary(), ...$options, $in, $out]);
        } catch (Throwable $e) {
            return $this->failed(['text' => null, 'code' => null, 'error' => $e->getMessage(), 'crashed' => true]);
        }
        $text = is_file($out) ? (string) file_get_contents($out) : null;
        // Some Windows builds report a warning code although the text was written.
        if ($text !== null && ($r->successful() || trim($text) !== '')) {
            return ['text' => $text, 'code' => $r->exitCode(), 'error' => '', 'crashed' => false];
        }

        return $this->failed(['text' => null, 'code' => $r->exitCode(), 'error' => trim($r->errorOutput().' '.$r->output()), 'crashed' => false]);
    }

    private function failed(array $result): array
    {
        $this->lastError = trim('pdftotext '.($result['crashed'] ? 'could not be started' : 'stopped with code '.$result['code']).': '.$result['error']);
        Log::warning('cv.pdf_read_failed', ['binary' => $this->binary(), 'detail' => $this->lastError]);

        return $result;
    }

    private function classify(array $r): string
    {
        $err = strtolower($r['error']);

        // A missing reader fails differently on each system and for each
        // mistake (no such file, no such folder, not a program), so
        // pdftotext is simply asked for its version: if that fails too, the
        // reader is missing and the file is not to blame.
        return match (true) {
            str_contains($err, 'password') || str_contains($err, 'encrypt') => 'locked',
            ! $this->pdfReaderReady()                                       => 'no_pdf_reader',
            default                                                         => 'broken',
        };
    }

    private function binary(): string
    {
        $bin = trim((string) config('cv.pdftotext', 'pdftotext'), " \"'");

        return DIRECTORY_SEPARATOR === '\\' ? str_replace('/', '\\', $bin) : $bin;
    }

    // ── Word (.docx) ─────────────────────────────────────────────────

    /** @return array{0: string, 1: ?string} */
    private function docx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return ['', 'broken'];
        }

        $parts = [];
        // Headers first (many CVs put the name and contact details there).
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^word/header\d*\.xml$#', $name)) {
                $parts[] = $zip->getFromIndex($i);
            }
        }
        $body = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($body === false) {
            return ['', 'broken'];
        }
        $parts[] = $body;

        return [implode("\n", array_map(fn ($xml) => $this->wordXmlToText((string) $xml), $parts)), null];
    }

    /**
     * Paragraphs, line breaks, tabs and table cells → plain lines.
     * A short paragraph that is all bold (or uses a Heading style) is
     * marked with CvReader::BOLD, because in Word CVs that is how
     * section headings look; the reader removes the mark.
     */
    private function wordXmlToText(string $xml): string
    {
        // Deleted text (tracked changes) is not part of the CV.
        $xml = preg_replace('#<w:del\b.*?</w:del>#s', '', $xml) ?? $xml;
        $chunks = preg_split('#(</w:p>|</w:tc>)#', $xml, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        $line = '';
        foreach ($chunks as $chunk) {
            if ($chunk === '</w:p>') {
                $out .= $line."\n";
                $line = '';
                continue;
            }
            if ($chunk === '</w:tc>') {
                continue;   // each cell's paragraphs are already lines
            }
            $text = $this->runs($chunk);
            if (trim($text) === '') {
                continue;
            }
            $heading = preg_match('#<w:pStyle w:val="(?:Heading|Title|heading)[^"]*"#', $chunk) || $this->allBold($chunk);
            $line .= ($heading && mb_strlen(trim($text)) <= 50 && $line === '' ? CvReader::BOLD : '').$text;
        }

        return html_entity_decode($out.$line, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** The text of one paragraph: runs, tabs, line breaks. */
    private function runs(string $xml): string
    {
        // Double quotes: \t and \n must be a REAL tab and line break. (In single
        // quotes PHP keeps them as the two characters \ and t, so a tabbed
        // heading like "<tab>Education" was read as "\tEducation" and never matched.)
        $xml = preg_replace('#<w:(tab|ptab)\b[^>]*/>#', "<w:t>\t</w:t>", $xml) ?? $xml;
        $xml = preg_replace('#<w:(br|cr)\b[^>]*/>#', "<w:t>\n</w:t>", $xml) ?? $xml;
        preg_match_all('#<w:t(?:\s[^>]*)?>(.*?)</w:t>#s', $xml, $m);

        return str_replace("\t", ' | ', implode('', $m[1]));
    }

    private function allBold(string $paragraph): bool
    {
        preg_match_all('#<w:r\b.*?</w:r>#s', $paragraph, $runs);
        $withText = array_filter($runs[0], fn ($r) => preg_match('#<w:t(?:\s[^>]*)?>[^<]*\S#', $r));
        if (! $withText) {
            return false;
        }
        foreach ($withText as $r) {
            if (! preg_match('#<w:b(?:\s+w:val="(?:1|true|on)")?\s*/>#', $r)) {
                return false;
            }
        }

        return true;
    }

    // ── Tidying ──────────────────────────────────────────────────────

    private function tidy(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        // Invisible direction marks, zero-width characters, soft hyphens.
        $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{00AD}]/u', '', $text) ?? $text;
        // Arabic presentation forms (ﻣﺤﺎﺳﺐ) → normal letters.
        if (class_exists(\Normalizer::class) && preg_match('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        }
        $text = str_replace(["\r\n", "\r", "\f", "\u{00A0}"], ["\n", "\n", "\n", ' '], $text);
        $text = str_replace(CvReader::BOLD.' ', CvReader::BOLD, $text);
        // "الميلاد12/03" → "الميلاد 12/03": Arabic letters and digits glued together.
        // (Arabic LETTERS only: Arabic-Indic digits ٠-٩ count as "Arabic" too, and must stay together.)
        $text = preg_replace('/([\x{0621}-\x{064A}])([0-9٠-٩])|([0-9٠-٩])([\x{0621}-\x{064A}])/u', '$1$3 $2$4', $text) ?? $text;
        // The many dash characters (‐ ‑ ‒ −) → "-", so dates like "15‐8‐2017" are read.
        $text = strtr($text, ["\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2212}" => '-']);
        $lines = array_map(fn ($l) => trim(preg_replace('/[ \t]+/u', ' ', $l) ?? $l, " |\t"), explode("\n", $text));
        // A Word tab in front of a bold heading: "<bold>| | Work Experience" → "<bold>Work Experience".
        $lines = array_map(fn ($l) => preg_replace('/^'.CvReader::BOLD.'[\s|]+/u', CvReader::BOLD, $l) ?? $l, $lines);
        $lines = array_map(fn ($l) => $l === CvReader::BOLD ? '' : $l, $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '';

        return trim($text);
    }

    private function letters(string $text): int
    {
        return preg_match_all('/\p{L}/u', $text);
    }
}
