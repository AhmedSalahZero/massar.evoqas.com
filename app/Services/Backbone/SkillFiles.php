<?php

namespace App\Services\Backbone;

use RuntimeException;

// ══════════════════════════════════════════════════════════════════
//  Massar — SkillFiles (reads the official ESCO skills files)
//  Location: app/Services/Backbone/SkillFiles.php
//
//  Turns the ESCO skills pillar CSV files (config backbone.skills)
//  into plain PHP arrays. No database work here — SkillImporter
//  decides what to store. Like SourceFiles (which it builds on), each
//  reader checks that the columns it needs are there and stops with a
//  clear message if a file is not the expected one.
//
//    skills()        skills_en + skills_ar, merged by URI → ~14,000
//    groups()        skillGroups_en + _ar, merged by URI  → 640
//    broader()       where each skill and group sits (its parents)
//    occupationLinks()  occupation ↔ skill, essential / optional (streamed:
//                       ~126,000 rows are never all held in memory)
//    relatedLinks()     skill ↔ skill (streamed)
//
//  DUPLICATE ROWS
//  ESCO v1.2.1 lists 21 skills twice, identical except for the
//  "modified" date. The newest row is kept and the others are counted.
// ══════════════════════════════════════════════════════════════════

class SkillFiles extends SourceFiles
{
    /** ESCO's skillType → what Massar stores. */
    private const TYPES = ['skill/competence' => 'skill', 'knowledge' => 'knowledge'];

    private const REUSE = ['transversal', 'cross-sector', 'sector-specific', 'occupation-specific'];

    public static function fromConfig(): self
    {
        return new self(rtrim(config('backbone.path'), '/\\'), config('backbone.skills.files'));
    }

    /** The file name behind a key, for messages ('esco/skills_en.csv'). */
    public function name(string $key): string
    {
        return $this->files[$key];
    }

    // ── Skills ───────────────────────────────────────────────────────

    /**
     * @return array{rows: array<string, array>, duplicates: int}
     */
    public function skills(): array
    {
        $rows = [];
        $duplicates = 0;

        foreach ($this->csv('skills_en', ['conceptUri', 'skillType', 'reuseLevel', 'preferredLabel', 'altLabels', 'description']) as $r) {
            $uri = trim($r['conceptUri']);
            if ($uri === '') {
                continue;
            }
            $modified = $this->text($r['modifiedDate'] ?? null);
            if (isset($rows[$uri])) {
                $duplicates++;
                if (($modified ?? '') <= ($rows[$uri]['modified'] ?? '')) {
                    continue;   // keep the newest copy
                }
            }
            $type = trim($r['skillType']);
            $reuse = trim($r['reuseLevel']);
            $rows[$uri] = [
                'uri'            => $uri,
                'type'           => self::TYPES[$type] ?? null,
                'raw_type'       => $type,
                'reuse'          => in_array($reuse, self::REUSE, true) ? $reuse : null,
                'raw_reuse'      => $reuse,
                'title_en'       => $this->text($r['preferredLabel']),
                'alt_en'         => $this->lines($r['altLabels']),
                'hidden_en'      => $this->lines($r['hiddenLabels'] ?? null),
                'description_en' => $this->text($r['description']),
                'scope_note_en'  => $this->text($r['scopeNote'] ?? null),
                'modified'       => $modified,
                'title_ar'       => null,
                'alt_ar'         => [],
                'description_ar' => null,
            ];
        }

        $arModified = [];
        foreach ($this->csv('skills_ar', ['conceptUri', 'preferredLabel']) as $r) {
            $uri = trim($r['conceptUri']);
            if (! isset($rows[$uri])) {
                continue;
            }
            $modified = $this->text($r['modifiedDate'] ?? null) ?? '';
            if (isset($arModified[$uri]) && $modified <= $arModified[$uri]) {
                continue;   // same rule as English: the newest copy wins
            }
            $arModified[$uri] = $modified;
            $rows[$uri]['title_ar'] = $this->text($r['preferredLabel']);
            $rows[$uri]['alt_ar'] = $this->lines($r['altLabels'] ?? null);
            $rows[$uri]['description_ar'] = $this->text($r['description'] ?? null);
        }

        return ['rows' => $rows, 'duplicates' => $duplicates];
    }

    // ── Skill groups (the tree) ──────────────────────────────────────

    /** @return array<string, array> uri => group */
    public function groups(): array
    {
        $rows = [];
        foreach ($this->csv('groups_en', ['conceptUri', 'preferredLabel', 'code']) as $r) {
            $uri = trim($r['conceptUri']);
            if ($uri === '' || isset($rows[$uri])) {
                continue;
            }
            $rows[$uri] = [
                'uri'            => $uri,
                'code'           => trim($r['code']),
                'title_en'       => $this->text($r['preferredLabel']),
                'description_en' => $this->text($r['description'] ?? null),
                'scope_note_en'  => $this->text($r['scopeNote'] ?? null),
                'title_ar'       => null,
                'description_ar' => null,
            ];
        }

        foreach ($this->csv('groups_ar', ['conceptUri', 'preferredLabel']) as $r) {
            $uri = trim($r['conceptUri']);
            if (isset($rows[$uri]) && $rows[$uri]['title_ar'] === null) {
                $rows[$uri]['title_ar'] = $this->text($r['preferredLabel']);
                $rows[$uri]['description_ar'] = $this->text($r['description'] ?? null);
            }
        }

        return $rows;
    }

    // ── Where things sit ─────────────────────────────────────────────

    /**
     * One row per "concept sits under broader" line.
     *
     * @return list<array{0: string, 1: string}> [conceptUri, broaderUri]
     */
    public function broader(): array
    {
        $out = [];
        foreach ($this->csv('broader', ['conceptType', 'conceptUri', 'broaderType', 'broaderUri']) as $r) {
            $concept = trim($r['conceptUri']);
            $broader = trim($r['broaderUri']);
            if ($concept !== '' && $broader !== '') {
                $out[] = [$concept, $broader];
            }
        }

        return $out;
    }

    // ── Links (streamed) ─────────────────────────────────────────────

    /** @return \Generator<array{0: string, 1: string, 2: string}> [occupationUri, skillUri, relationType] */
    public function occupationLinks(): \Generator
    {
        foreach ($this->csv('occupations', ['occupationUri', 'relationType', 'skillUri']) as $r) {
            yield [trim($r['occupationUri']), trim($r['skillUri']), trim($r['relationType'])];
        }
    }

    /** @return \Generator<array{0: string, 1: string, 2: string}> [skillUri, relatedSkillUri, relationType] */
    public function relatedLinks(): \Generator
    {
        foreach ($this->csv('related', ['originalSkillUri', 'relationType', 'relatedSkillUri']) as $r) {
            yield [trim($r['originalSkillUri']), trim($r['relatedSkillUri']), trim($r['relationType'])];
        }
    }

    /** 'essential' → true, 'optional' → false, anything else → an error the import reports. */
    public static function isEssential(string $relationType): bool
    {
        return match ($relationType) {
            'essential' => true,
            'optional'  => false,
            default     => throw new RuntimeException("Unknown relation type \"{$relationType}\" in the ESCO skills files (expected essential or optional)."),
        };
    }
}
