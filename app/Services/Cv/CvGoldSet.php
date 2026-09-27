<?php

namespace App\Services\Cv;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvGoldSet (the test CVs with their correct answers)
//  Location: app/Services/Cv/CvGoldSet.php
//
//  A folder of CVs, each with a small .json file that says what a
//  careful person would type into the form. After every change to the
//  reading engine, every CV is read again and compared, so we can see
//  that a change helps and breaks nothing:
//
//      Fully correct: 42 of 50 CVs · jobs right: 118 of 126
//
//  The folder: tests/Fixtures/cv-gold/
//    name.txt   the CV text (a line starting with # = bold in Word)
//    name.pdf / name.docx   OR the real file (read with TextExtractor)
//    name.json  the correct answers — only what is written is compared:
//      {
//        "about": "why this CV is in the set",
//        "pdf": false,                       (a .txt that came from a PDF)
//        "work": [                           (newest first, like the form)
//          {"title": "Chief Accountant", "employer": "Nile Foods",
//           "from": "2019-03", "to": null, "current": true,
//           "location": "Riyadh", "check": true, "duties": 4}
//        ],
//        "work_mark": "check",
//        "education": [{"qualification": "Bachelor of Commerce", "year": 2012}],
//        "no_unknown_headings": true,
//        "languages": ["ar", "en"]
//      }
//
//  Used by `php artisan cv:score` and by tests/Feature/CvGoldSetTest.
// ══════════════════════════════════════════════════════════════════

class CvGoldSet
{
    public static function folder(): string
    {
        return base_path('tests/Fixtures/cv-gold');
    }

    /**
     * Every CV of the set, read with this reader, with its correct answers.
     * A .pdf is skipped (with a note) when Poppler is not ready.
     *
     * @return array<string, array{want: array, reading: ?array, skipped: ?string}>
     */
    public static function readAll(CvReader $reader, ?TextExtractor $extractor = null, ?string $only = null): array
    {
        $out = [];
        foreach (glob(self::folder().'/*.json') ?: [] as $json) {
            $name = basename($json, '.json');
            if ($only !== null && ! str_contains($name, $only)) {
                continue;
            }
            $want = json_decode((string) file_get_contents($json), true) ?: [];
            $base = substr($json, 0, -5);
            $reading = null;
            $skipped = null;
            if (is_file($base.'.txt')) {
                // A line starting with "#" is bold, as in a Word file.
                $text = implode("\n", array_map(
                    fn ($l) => str_starts_with($l, '#') ? CvReader::BOLD.substr($l, 1) : $l,
                    explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($base.'.txt'))),
                ));
                $reading = $reader->read($text, false, (bool) ($want['pdf'] ?? false));
            } else {
                foreach (['docx', 'pdf'] as $ext) {
                    if (! is_file($base.'.'.$ext)) {
                        continue;
                    }
                    $extractor ??= app(TextExtractor::class);
                    if ($ext === 'pdf' && ! $extractor->pdfReaderReady()) {
                        $skipped = 'Poppler (pdftotext) is not ready';
                        break;
                    }
                    $x = $extractor->extract($base.'.'.$ext, $ext);
                    $reading = empty($x['problem']) ? $reader->read($x['text'], $x['arabic_ligatures'], $ext === 'pdf') : null;
                    $skipped = $reading ? null : 'the file could not be read: '.($x['problem'] ?? '?');
                    break;
                }
                $skipped ??= $reading ? null : 'no .txt, .docx or .pdf next to the .json';
            }
            $out[$name] = ['want' => $want, 'reading' => $reading, 'skipped' => $skipped];
        }

        return $out;
    }

    /**
     * What is different from the correct answers (empty = fully correct).
     *
     * @return list<string>
     */
    public static function compare(array $reading, array $want): array
    {
        $out = [];
        $form = $reading['form'] ?? [];
        if (array_key_exists('work', $want)) {
            $jobs = $form['work_history'] ?? [];
            if (count($jobs) !== count($want['work'])) {
                $out[] = 'jobs: '.count($jobs).' read, '.count($want['work']).' expected';
            }
            foreach ($want['work'] as $i => $w) {
                $job = $jobs[$i] ?? null;
                if (! $job) {
                    $out[] = 'job '.($i + 1).' missing: '.($w['title'] ?? '?');
                    continue;
                }
                foreach (self::jobProblems($job, $w) as $p) {
                    $out[] = 'job '.($i + 1).' '.$p;
                }
            }
        }
        if (isset($want['work_mark']) && ($reading['marks']['work_history'] ?? null) !== $want['work_mark']) {
            $out[] = 'work mark: '.($reading['marks']['work_history'] ?? '-').', expected '.$want['work_mark'];
        }
        if (array_key_exists('education', $want)) {
            $edu = $form['education'] ?? [];
            if (count($edu) !== count($want['education'])) {
                $out[] = 'education: '.count($edu).' read, '.count($want['education']).' expected';
            }
            foreach ($want['education'] as $i => $w) {
                foreach ($w as $k => $v) {
                    $got = $edu[$i][$k] ?? null;
                    if (! self::same($got, $v)) {
                        $out[] = 'education '.($i + 1)." {$k}: ".json_encode($got, JSON_UNESCAPED_UNICODE).', expected '.json_encode($v, JSON_UNESCAPED_UNICODE);
                    }
                }
            }
        }
        if (($want['no_unknown_headings'] ?? false) && ($reading['marks_on_text']['unknown'] ?? [])) {
            $out[] = 'unknown headings reported: '.count($reading['marks_on_text']['unknown']);
        }
        if (isset($want['languages'])) {
            $codes = array_column($form['languages'] ?? [], 'code');
            sort($codes);
            $expected = $want['languages'];
            sort($expected);
            if ($codes !== $expected) {
                $out[] = 'languages: '.implode(',', $codes).', expected '.implode(',', $expected);
            }
        }
        if (isset($want['skills_include'])) {
            $have = array_map('mb_strtolower', $form['skills'] ?? []);
            foreach ($want['skills_include'] as $s) {
                if (! in_array(mb_strtolower($s), $have, true)) {
                    $out[] = "skill missing: {$s}";
                }
            }
        }

        return $out;
    }

    /** How many of the expected jobs were read exactly right (same place in the list). */
    public static function jobsRight(array $reading, array $want): int
    {
        $jobs = $reading['form']['work_history'] ?? [];
        $n = 0;
        foreach ($want['work'] ?? [] as $i => $w) {
            if (isset($jobs[$i]) && ! self::jobProblems($jobs[$i], $w)) {
                $n++;
            }
        }

        return $n;
    }

    /** @return list<string> */
    private static function jobProblems(array $job, array $w): array
    {
        $out = [];
        foreach ($w as $k => $v) {
            $got = match ($k) {
                'check'  => ! empty($job['check']),
                'duties' => count($job['responsibilities'] ?? []),
                default  => $job[$k] ?? null,
            };
            if (! self::same($got, $v)) {
                $out[] = "{$k}: ".json_encode($got, JSON_UNESCAPED_UNICODE).', expected '.json_encode($v, JSON_UNESCAPED_UNICODE);
            }
        }

        return $out;
    }

    private static function same(mixed $got, mixed $want): bool
    {
        if (is_string($got) && is_string($want)) {
            return mb_strtolower(trim($got)) === mb_strtolower(trim($want));
        }

        return $got === $want;
    }
}
