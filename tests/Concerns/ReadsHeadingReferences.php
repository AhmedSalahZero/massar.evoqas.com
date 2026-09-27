<?php

namespace Tests\Concerns;

// ══════════════════════════════════════════════════════════════════
//  Massar — ReadsHeadingReferences (test helper)
//  Location: tests/Concerns/ReadsHeadingReferences.php
//
//  Reads the two reference files "CV Section Title Variations —
//  Parser Reference" (docs/cv-headings/*.md) and returns every heading
//  listed in them, with the part of the file it comes from.
//
//  Taken: the bullet items under the parts that list headings
//  ("English Variations", "Common headings", "Arabic / bilingual
//  variants" …). Left out: the parts that explain rather than list —
//  "Canonical meaning", "Parser notes", examples of CONTENT (degrees,
//  licence names, military values), date patterns, detection signals,
//  the normalisation steps and the list of ambiguous words.
//  "English | عربي" items give both halves.
// ══════════════════════════════════════════════════════════════════

trait ReadsHeadingReferences
{
    /** @return list<array{heading: string, from: string}> */
    protected function referenceHeadings(): array
    {
        $skipParts = [
            'Recommended Canonical Section Taxonomy', 'Section Detection Signals', 'Date Patterns Useful for Section Classification',
            'Parser Normalization Pipeline', 'Ambiguous Terms Requiring Context', 'Final Parser Design Recommendation',
            'Alternative Spelling Variations', 'Common CV Section Ordering', 'Sections That May Be Embedded Without Headings',
            'Capitalization Variations', 'Punctuation Variations', 'Numbered Section Titles', 'Decorative Section Titles',
        ];
        $skipSubs = ['Canonical meaning', 'Parser notes', 'Possible content', 'Examples of content', 'Common Egyptian CV values',
            'Recommended Structure', 'Golden Parser Rule for Responsibilities', 'English', 'Arabic'];

        $out = [];
        foreach (glob(base_path('docs/cv-headings/*.md')) as $file) {
            $top = null;
            $sub = '';
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
                if (preg_match('/^#\s+\d+(?:\.\d+)?\.?\s+(.*)$/u', $line, $m)) {
                    [$top, $sub] = [trim($m[1]), ''];
                    continue;
                }
                if (preg_match('/^#{2,4}\s+(.*)$/u', $line, $m)) {
                    $sub = trim($m[1]);
                    continue;
                }
                if (! $top || ! preg_match('/^\s*[-*]\s+(.*)$/u', $line, $m)) {
                    continue;
                }
                $isDegreePart = $top === 'Education Degree Variations' && in_array($sub, ['English', 'Arabic'], true);
                $isLicenceExample = $top === 'Licenses' && $sub === 'Examples';
                if (in_array($top, $skipParts, true) || (in_array($sub, $skipSubs, true) && $top !== 'Arabic Heading Variations') || $isDegreePart || $isLicenceExample) {
                    continue;
                }
                $item = trim(str_replace(['\\|', '`', '**'], ['|', '', ''], $m[1]));
                $parts = preg_split('/\s+[|\/\-–—]\s+|\s*\|\s*/u', $item, -1, PREG_SPLIT_NO_EMPTY);
                $bilingual = count($parts) === 2 && (bool) preg_match('/\p{Arabic}/u', $parts[0]) !== (bool) preg_match('/\p{Arabic}/u', $parts[1]);
                foreach ($bilingual ? [$item, ...$parts] : [$item] as $h) {
                    $out[] = ['heading' => trim($h), 'from' => basename($file).' › '.$top.($sub ? ' › '.$sub : '')];
                }
            }
        }

        return $out;
    }

    /** The examples in "Capitalization / Punctuation / Numbered / Decorative Section Titles". @return list<string> */
    protected function referenceWritingExamples(): array
    {
        $want = ['Capitalization Variations', 'Punctuation Variations', 'Numbered Section Titles', 'Decorative Section Titles'];
        $out = [];
        foreach (glob(base_path('docs/cv-headings/*.md')) as $file) {
            $top = null;
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
                if (preg_match('/^#\s+\d+(?:\.\d+)?\.?\s+(.*)$/u', $line, $m)) {
                    $top = trim($m[1]);
                    continue;
                }
                if (in_array($top, $want, true) && preg_match('/^\s*[-*]\s+(.*)$/u', $line, $m)) {
                    $out[] = trim(str_replace(['\\|', '`'], ['|', ''], $m[1]));
                }
            }
        }

        return $out;
    }
}
