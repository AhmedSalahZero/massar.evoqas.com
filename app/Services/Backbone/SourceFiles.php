<?php

namespace App\Services\Backbone;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

// ══════════════════════════════════════════════════════════════════
//  Massar — SourceFiles (reads the official backbone files)
//  Location: app/Services/Backbone/SourceFiles.php
//
//  Turns each official file into plain PHP arrays. No database work
//  here — BackboneImporter decides what to store. Each reader checks
//  that the columns it needs are really there and stops with a clear
//  message if a file is not the expected one (wrong file, different
//  layout), instead of importing something half-right.
//
//    isco()        ILO English structure + ILO Arabic titles → 619 groups
//    enoc()        Egypt Occupational Outlook → 426 occupations
//    enocMajors()  Egypt Occupational Outlook, 2nd sheet → 9 major groups
//    esco()        ESCO occupations, English + Arabic CSV, merged by URI
//
//  SkillFiles (Step 5, the ESCO skills files) builds on this class and
//  uses the same CSV and text helpers.
// ══════════════════════════════════════════════════════════════════

class SourceFiles
{
    public function __construct(protected readonly string $dir, protected readonly array $files) {}

    public static function fromConfig(): self
    {
        return new self(rtrim(config('backbone.path'), '/\\'), config('backbone.files'));
    }

    public function path(string $key): string
    {
        return $this->dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->files[$key]);
    }

    /** @return array<string, array{name: string, sha256: string, bytes: int}> */
    public function fingerprints(): array
    {
        $out = [];
        foreach (array_keys($this->files) as $key) {
            $path = $this->path($key);
            if (! is_file($path)) {
                throw new RuntimeException("File missing: {$this->files[$key]}. Put it in database/data/backbone and run the import again.");
            }
            $out[$key] = ['name' => $this->files[$key], 'sha256' => hash_file('sha256', $path), 'bytes' => filesize($path)];
        }

        return $out;
    }

    // ── ISCO-08 ──────────────────────────────────────────────────────

    /**
     * @return array<string, array{code:string, level:int, title_en:string, title_ar:?string,
     *   definition_en:?string, tasks_en:?string, included_en:?string, excluded_en:?string, notes_en:?string}>
     */
    public function isco(): array
    {
        $groups = [];

        // English: header row, then one row per group.
        $rows = $this->sheet('isco_en', 0);
        $col = $this->columns($rows[0] ?? [], [
            'level' => 'Level', 'code' => 'ISCO 08 Code', 'title' => 'Title EN', 'definition' => 'Definition',
            'tasks' => 'Tasks include', 'included' => 'Included occupations', 'excluded' => 'Excluded occupations', 'notes' => 'Notes',
        ], 'isco_en');

        foreach (array_slice($rows, 1) as $r) {
            $level = (int) $this->cell($r[$col['level']] ?? null);
            $code = $this->code($r[$col['code']] ?? null, $level);
            if ($level < 1 || $level > 4 || $code === null) {
                continue;
            }
            $groups[$code] = [
                'code'          => $code,
                'level'         => $level,
                'title_en'      => $this->text($r[$col['title']] ?? null) ?? $code,
                'title_ar'      => null,
                'definition_en' => $this->text($r[$col['definition']] ?? null),
                'tasks_en'      => $this->text($r[$col['tasks']] ?? null),
                'included_en'   => $this->text($r[$col['included']] ?? null),
                'excluded_en'   => $this->text($r[$col['excluded']] ?? null),
                'notes_en'      => $this->text($r[$col['notes']] ?? null),
            ];
        }

        // Arabic: columns A–D hold the code in the column of its level,
        // E the English name, F the Arabic name.
        $rows = $this->sheet('isco_ar', 'ISCO-08 Structure', 1);
        foreach (array_slice($rows, 1) as $r) {
            for ($i = 0; $i < 4; $i++) {
                $code = $this->code($r[$i] ?? null, $i + 1);
                if ($code !== null) {
                    if (isset($groups[$code])) {
                        $groups[$code]['title_ar'] = $this->text($r[5] ?? null);
                    }
                    break;
                }
            }
        }

        // Structure check: all four levels present, and every group's
        // parent exists (catches a wrong or truncated file).
        $levels = array_unique(array_column($groups, 'level'));
        if (count($levels) !== 4) {
            throw new RuntimeException('The ISCO-08 English file does not look right: it should contain all four levels of groups.');
        }
        foreach ($groups as $code => $g) {
            if ($g['level'] > 1 && ! isset($groups[substr($code, 0, -1)])) {
                throw new RuntimeException("The ISCO-08 English file does not look right: group {$code} has no parent group ".substr($code, 0, -1).'.');
            }
        }

        ksort($groups, SORT_STRING);

        return $groups;
    }

    // ── ENOC ─────────────────────────────────────────────────────────

    /** @return array<string, array{code:string, title_ar:string, description_ar:?string}> */
    public function enoc(): array
    {
        $rows = $this->sheet('enoc', 0);
        $header = implode(' ', array_map(fn ($v) => (string) $v, $rows[1] ?? []));
        if (! str_contains($header, 'ENOC')) {
            throw new RuntimeException('The Egypt Occupational Outlook file does not look right: the ENOC code column was not found on the first sheet.');
        }

        $out = [];
        foreach (array_slice($rows, 2) as $r) {
            $code = $this->code($r[0] ?? null, 4);
            $title = $this->text($r[1] ?? null);
            if ($code === null || $title === null) {
                continue;
            }
            $out[$code] = ['code' => $code, 'title_ar' => $title, 'description_ar' => $this->text($r[2] ?? null)];
        }

        ksort($out, SORT_STRING);

        return $out;
    }

    /** @return array<string, string> major digit → Egyptian Arabic name */
    public function enocMajors(): array
    {
        $out = [];
        foreach (array_slice($this->sheet('enoc', 1), 1) as $r) {
            $title = $this->text($r[0] ?? null);
            $min = $this->cell($r[1] ?? null);
            if ($title !== null && is_numeric($min)) {
                $out[(string) intdiv((int) $min, 1000)] = $title;
            }
        }
        ksort($out, SORT_STRING);

        return $out;
    }

    // ── ESCO ─────────────────────────────────────────────────────────

    /**
     * English and Arabic files merged by ESCO URI. Duplicate rows in
     * the files are counted and dropped.
     *
     * @return array{rows: array<string, array>, duplicates: int}
     */
    public function esco(): array
    {
        $rows = [];
        $duplicates = 0;

        foreach ($this->csv('esco_en', ['conceptUri', 'iscoGroup', 'preferredLabel', 'code']) as $r) {
            $uri = trim($r['conceptUri']);
            if ($uri === '') {
                continue;
            }
            if (isset($rows[$uri])) {
                $duplicates++;
                continue;
            }
            $rows[$uri] = [
                'uri'            => $uri,
                'code'           => trim($r['code']),
                'isco_code'      => $this->code($r['iscoGroup'], 4),
                'title_en'       => $this->text($r['preferredLabel']),
                'alt_en'         => $this->lines($r['altLabels'] ?? null),
                'hidden_en'      => $this->lines($r['hiddenLabels'] ?? null),
                'description_en' => $this->text($r['description'] ?? null),
                'definition_en'  => $this->text($r['definition'] ?? null),
                'scope_note_en'  => $this->text($r['scopeNote'] ?? null),
                'is_regulated'   => str_ends_with(trim($r['regulatedProfessionNote'] ?? ''), '/regulated'),
                'nace_codes'     => $this->nace($r['naceCode'] ?? null),
                'modified'       => $this->text($r['modifiedDate'] ?? null),
                'title_ar'       => null,
                'alt_ar'         => [],
                'description_ar' => null,
            ];
        }

        foreach ($this->csv('esco_ar', ['conceptUri', 'preferredLabel']) as $r) {
            $uri = trim($r['conceptUri']);
            if (isset($rows[$uri]) && $rows[$uri]['title_ar'] === null) {
                $rows[$uri]['title_ar'] = $this->text($r['preferredLabel']);
                $rows[$uri]['alt_ar'] = $this->lines($r['altLabels'] ?? null);
                $rows[$uri]['description_ar'] = $this->text($r['description'] ?? null);
            }
        }

        return ['rows' => $rows, 'duplicates' => $duplicates];
    }

    /**
     * 'المدير الفني / المديرة الفنية' → ['المدير الفني', 'المديرة الفنية'].
     * A title without the " / " pair is masculine only.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function splitArabicTitle(?string $title): array
    {
        if ($title === null || $title === '') {
            return [null, null];
        }
        $parts = array_values(array_filter(array_map('trim', preg_split('~\s*/\s*~u', $title)), fn ($p) => $p !== ''));

        return count($parts) === 2 ? [$parts[0], $parts[1]] : [trim($title), null];
    }

    // ── Low-level helpers ────────────────────────────────────────────

    /** Rows of one sheet as arrays of raw cell values. $which = index, or a name fragment with an index fallback. */
    private function sheet(string $key, int|string $which, int $fallback = 0): array
    {
        $reader = IOFactory::createReaderForFile($this->path($key));
        $reader->setReadDataOnly(true);
        $book = $reader->load($this->path($key));

        $sheet = null;
        if (is_string($which)) {
            foreach ($book->getWorksheetIterator() as $ws) {
                if (str_contains($ws->getTitle(), $which)) {
                    $sheet = $ws;
                    break;
                }
            }
            $sheet ??= $book->getSheet($fallback);
        } else {
            if ($which >= $book->getSheetCount()) {
                throw new RuntimeException("The file {$this->files[$key]} has fewer sheets than expected.");
            }
            $sheet = $book->getSheet($which);
        }

        $rows = $sheet->toArray(null, false, false, false);
        $book->disconnectWorksheets();

        return $rows;
    }

    /** @return \Generator<array<string, string>> */
    protected function csv(string $key, array $required): \Generator
    {
        $handle = fopen($this->path($key), 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open {$this->files[$key]}.");
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! $header) {
                throw new RuntimeException("The file {$this->files[$key]} is empty.");
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $header = array_map('trim', $header);

            $missing = array_diff($required, $header);
            if ($missing) {
                throw new RuntimeException("The file {$this->files[$key]} is missing the column(s): ".implode(', ', $missing).'.');
            }

            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                yield array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            }
        } finally {
            fclose($handle);
        }
    }

    private function columns(array $header, array $wanted, string $key): array
    {
        $map = [];
        $clean = array_map(fn ($h) => trim((string) $h), $header);
        foreach ($wanted as $name => $title) {
            $i = array_search($title, $clean, true);
            if ($i === false) {
                throw new RuntimeException("The file {$this->files[$key]} is missing the column \"{$title}\".");
            }
            $map[$name] = $i;
        }

        return $map;
    }

    private function cell(mixed $v): mixed
    {
        if (is_float($v) && floor($v) === $v) {
            return (int) $v;
        }

        return is_string($v) ? trim($v) : $v;
    }

    /** A classification code, left-padded with zeros to its level ('110' at level 4 → '0110'). */
    private function code(mixed $v, int $length): ?string
    {
        $v = $this->cell($v);
        if ($v === null || $v === '' || ! preg_match('/^\d+$/', (string) $v)) {
            return null;
        }
        $code = str_pad((string) $v, $length, '0', STR_PAD_LEFT);

        return strlen($code) === $length ? $code : null;
    }

    /** Trimmed text with tidy spaces and line breaks; null when empty. */
    protected function text(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = str_replace(["\r\n", "\r", '_x000D_'], "\n", (string) $v);
        $v = preg_replace('/[ \t\x{00A0}]+/u', ' ', $v);
        $v = preg_replace('/ *\n */', "\n", $v);
        $v = trim($v);

        return $v === '' ? null : $v;
    }

    /** @return list<string> */
    protected function lines(?string $v): array
    {
        $v = $this->text($v);

        return $v === null ? [] : array_values(array_unique(array_filter(array_map('trim', explode("\n", $v)))));
    }

    private function nace(?string $v): ?string
    {
        $v = $this->text($v);
        if ($v === null) {
            return null;
        }
        $codes = array_map(fn ($u) => basename(trim($u)), preg_split('/[\n,]+/', $v));

        return mb_substr(implode(',', array_unique(array_filter($codes))), 0, 250) ?: null;
    }
}
