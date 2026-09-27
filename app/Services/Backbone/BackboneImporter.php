<?php

namespace App\Services\Backbone;

use App\Models\Backbone\BackboneImport;
use App\Support\TextNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — BackboneImporter
//  Location: app/Services/Backbone/BackboneImporter.php
//
//  Loads ISCO-08, ENOC and ESCO from the official files into the
//  backbone tables, linked together. Run by `php artisan
//  backbone:import` (and by BackboneSeeder on a fresh install).
//
//  SAFE TO RUN AGAIN, ANY TIME
//  · All-or-nothing: everything is written inside one database
//    transaction. If anything fails, the backbone stays exactly as it
//    was and the failure is recorded with a plain-language reason.
//  · Stable IDs: rows are matched by their official code (ISCO, ENOC)
//    or permanent URI (ESCO) and updated in place, so links from CVs,
//    beneficiaries and jobs never break. Occupations that disappear
//    from a newer edition are marked inactive, never deleted.
//  · Unchanged files are detected by checksum and skipped (use
//    --force to import anyway).
//
//  CHECKS BEFORE WRITING (the import stops if any fails)
//  · every ENOC code exists as an ISCO-08 unit group
//  · every ESCO occupation points to an existing ISCO-08 unit group
//  Findings that are facts of the data, not errors (unit groups with
//  no ENOC occupation, or with no ESCO occupation), are counted and
//  shown on the Backbone screen.
// ══════════════════════════════════════════════════════════════════

class BackboneImporter
{
    private int $chunk;

    public function __construct(private readonly SourceFiles $files)
    {
        $this->chunk = (int) config('backbone.chunk', 500);
    }

    public static function make(): self
    {
        return new self(SourceFiles::fromConfig());
    }

    public function run(bool $force = false, ?int $userId = null, string $via = 'console'): BackboneImport
    {
        $fingerprints = $this->files->fingerprints();   // throws if a file is missing
        $editions = config('backbone.editions');

        $last = BackboneImport::lastCompleted();
        if (! $force && $last && $last->files == $fingerprints && $last->editions == $editions) {
            return BackboneImport::create([
                'status'        => BackboneImport::UNCHANGED,
                'editions'      => $editions,
                'files'         => $fingerprints,
                'counts'        => $last->counts,
                'message'       => 'The files are the same as in import #'.$last->id.'. Nothing was changed.',
                'user_id'       => $userId,
                'triggered_via' => $via,
                'started_at'    => now(),
                'finished_at'   => now(),
            ]);
        }

        $import = BackboneImport::create([
            'status'        => BackboneImport::RUNNING,
            'editions'      => $editions,
            'files'         => $fingerprints,
            'user_id'       => $userId,
            'triggered_via' => $via,
            'started_at'    => now(),
        ]);

        try {
            $isco = $this->files->isco();
            $enoc = $this->files->enoc();
            $majors = $this->files->enocMajors();
            $esco = $this->files->esco();

            $this->validate($isco, $enoc, $esco['rows']);

            $counts = DB::transaction(fn () => $this->write($import->id, $isco, $enoc, $majors, $esco['rows']));
            $counts['esco_duplicate_rows_skipped'] = $esco['duplicates'];

            $import->update([
                'status'      => BackboneImport::COMPLETED,
                'counts'      => $counts,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $import->update([
                'status'      => BackboneImport::FAILED,
                'message'     => mb_substr($e->getMessage(), 0, 2000),
                'finished_at' => now(),
            ]);

            throw $e;
        }

        return $import->refresh();
    }

    // ── Checks ───────────────────────────────────────────────────────

    private function validate(array $isco, array $enoc, array $esco): void
    {
        $units = array_keys(array_filter($isco, fn ($g) => $g['level'] === 4));
        $units = array_flip($units);

        $badEnoc = array_keys(array_diff_key($enoc, $units));
        if ($badEnoc) {
            throw new RuntimeException('These ENOC codes do not exist in ISCO-08: '.implode(', ', array_slice($badEnoc, 0, 20)).'. Nothing was imported.');
        }

        $badEsco = [];
        foreach ($esco as $row) {
            if ($row['isco_code'] === null || ! isset($units[$row['isco_code']]) || $row['title_en'] === null) {
                $badEsco[] = $row['code'] ?: $row['uri'];
            }
        }
        if ($badEsco) {
            throw new RuntimeException('These ESCO occupations have no valid ISCO-08 unit group or title: '.implode(', ', array_slice($badEsco, 0, 20)).'. Nothing was imported.');
        }
    }

    // ── Writing (inside the transaction) ─────────────────────────────

    private function write(int $importId, array $isco, array $enoc, array $majors, array $esco): array
    {
        $now = Carbon::now();

        // 1 · ISCO-08 groups — first the rows, then the parent links
        //     (a parent's id is only known once it exists).
        $iscoRow = fn (array $g, ?int $parentId) => [
            'code' => $g['code'], 'level' => $g['level'], 'parent_id' => $parentId, 'major_code' => $g['code'][0],
            'title_en' => $g['title_en'], 'title_ar' => $g['title_ar'], 'definition_en' => $g['definition_en'],
            'tasks_en' => $g['tasks_en'], 'included_en' => $g['included_en'], 'excluded_en' => $g['excluded_en'],
            'notes_en' => $g['notes_en'], 'last_import_id' => $importId, 'created_at' => $now, 'updated_at' => $now,
        ];
        $iscoUpdate = ['level', 'major_code', 'title_en', 'title_ar', 'definition_en', 'tasks_en', 'included_en', 'excluded_en', 'notes_en', 'last_import_id', 'updated_at'];

        $this->upsert('isco_groups', array_map(fn ($g) => $iscoRow($g, null), $isco), ['code'], $iscoUpdate);
        $iscoIds = DB::table('isco_groups')->pluck('id', 'code')->all();
        $this->upsert('isco_groups', array_map(
            fn ($g) => $iscoRow($g, $g['level'] > 1 ? ($iscoIds[substr($g['code'], 0, $g['level'] - 1)] ?? null) : null),
            $isco,
        ), ['code'], [...$iscoUpdate, 'parent_id']);

        // 2 · ENOC major groups and occupations.
        $this->upsert('enoc_major_groups', array_map(
            fn ($code, $title) => ['code' => (string) $code, 'title_ar' => $title, 'created_at' => $now, 'updated_at' => $now],
            array_keys($majors), $majors,
        ), ['code'], ['title_ar', 'updated_at']);

        $this->upsert('enoc_occupations', array_map(fn ($o) => [
            'code' => $o['code'], 'isco_group_id' => $iscoIds[$o['code']], 'major_code' => $o['code'][0],
            'title_ar' => $o['title_ar'], 'description_ar' => $o['description_ar'], 'is_active' => true,
            'last_import_id' => $importId, 'created_at' => $now, 'updated_at' => $now,
        ], $enoc), ['code'], ['isco_group_id', 'major_code', 'title_ar', 'description_ar', 'is_active', 'last_import_id', 'updated_at']);

        DB::table('enoc_occupations')
            ->where(fn ($q) => $q->where('last_import_id', '<>', $importId)->orWhereNull('last_import_id'))
            ->update(['is_active' => false, 'updated_at' => $now]);

        // 3 · ESCO occupations — rows first, then parent links by code
        //     (2654.1.7 → 2654.1 when that is an ESCO occupation).
        $escoVersion = preg_match('/v?(\d+(?:\.\d+)+)/', (string) config('backbone.editions.esco'), $m) ? $m[1] : null;
        $escoRow = function (array $r, ?int $parentId) use ($iscoIds, $importId, $now, $escoVersion) {
            [$male, $female] = SourceFiles::splitArabicTitle($r['title_ar']);

            return [
                'uri' => $r['uri'], 'code' => $r['code'], 'sort_key' => self::sortKey($r['code']), 'isco_group_id' => $iscoIds[$r['isco_code']], 'isco_code' => $r['isco_code'],
                'parent_id' => $parentId, 'depth' => max(1, substr_count($r['code'], '.')),
                'title_en' => mb_substr($r['title_en'], 0, 255), 'title_ar' => $r['title_ar'] ? mb_substr($r['title_ar'], 0, 255) : null,
                'title_ar_male' => $male ? mb_substr($male, 0, 255) : null, 'title_ar_female' => $female ? mb_substr($female, 0, 255) : null,
                'description_en' => $r['description_en'], 'description_ar' => $r['description_ar'],
                'definition_en' => $r['definition_en'], 'scope_note_en' => $r['scope_note_en'],
                'is_regulated' => $r['is_regulated'], 'nace_codes' => $r['nace_codes'], 'esco_version' => $escoVersion,
                'esco_modified_at' => $r['modified'] ? Carbon::parse($r['modified']) : null,
                'is_active' => true, 'last_import_id' => $importId, 'created_at' => $now, 'updated_at' => $now,
            ];
        };
        $escoUpdate = ['code', 'sort_key', 'isco_group_id', 'isco_code', 'depth', 'title_en', 'title_ar', 'title_ar_male', 'title_ar_female',
            'description_en', 'description_ar', 'definition_en', 'scope_note_en', 'is_regulated', 'nace_codes', 'esco_version',
            'esco_modified_at', 'is_active', 'last_import_id', 'updated_at'];

        $this->upsert('esco_occupations', array_map(fn ($r) => $escoRow($r, null), $esco), ['uri'], $escoUpdate);

        $escoIds = DB::table('esco_occupations')->where('last_import_id', $importId)->pluck('id', 'uri')->all();
        $idByCode = [];
        foreach ($esco as $r) {
            $idByCode[$r['code']] ??= $escoIds[$r['uri']];
        }
        $this->upsert('esco_occupations', array_map(function ($r) use ($escoRow, $idByCode) {
            $parentCode = str_contains($r['code'], '.') ? substr($r['code'], 0, strrpos($r['code'], '.')) : null;

            return $escoRow($r, $parentCode ? ($idByCode[$parentCode] ?? null) : null);
        }, $esco), ['uri'], [...$escoUpdate, 'parent_id']);

        DB::table('esco_occupations')
            ->where(fn ($q) => $q->where('last_import_id', '<>', $importId)->orWhereNull('last_import_id'))
            ->update(['is_active' => false, 'updated_at' => $now]);

        // 4 · Search labels — rebuilt from scratch.
        $labels = $this->labels($isco, $enoc, $esco, $iscoIds, $escoIds);
        DB::table('occupation_labels')->delete();
        foreach (array_chunk($labels, $this->chunk) as $chunk) {
            DB::table('occupation_labels')->insert($chunk);
        }

        return $this->counts($isco, $enoc, $majors, $esco, $labels);
    }

    /** Every title a person might type, normalised, one row per distinct form per occupation. */
    private function labels(array $isco, array $enoc, array $esco, array $iscoIds, array $escoIds): array
    {
        $out = [];
        $seen = [];
        $add = function (string $source, int $unitId, ?int $escoId, string $lang, string $kind, ?string $label) use (&$out, &$seen) {
            $label = $label === null ? '' : trim($label);
            $normalized = TextNormalizer::normalize($label);
            if ($normalized === '') {
                return;
            }
            $key = ($escoId ? "e{$escoId}" : "u{$unitId}")."|{$lang}|{$normalized}";
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = [
                'isco_group_id' => $unitId, 'esco_occupation_id' => $escoId, 'source' => $source, 'lang' => $lang, 'kind' => $kind,
                'label' => mb_substr($label, 0, 255), 'normalized' => mb_substr($normalized, 0, 255),
            ];
        };

        // ENOC first, so the official Egyptian Arabic wording wins when
        // it matches the ILO Arabic title.
        foreach ($enoc as $o) {
            $add('enoc', $iscoIds[$o['code']], null, 'ar', 'preferred', $o['title_ar']);
        }
        foreach ($isco as $g) {
            if ($g['level'] === 4) {
                $add('isco', $iscoIds[$g['code']], null, 'en', 'preferred', $g['title_en']);
                $add('isco', $iscoIds[$g['code']], null, 'ar', 'preferred', $g['title_ar']);
            }
        }
        foreach ($esco as $r) {
            $unitId = $iscoIds[$r['isco_code']];
            $escoId = $escoIds[$r['uri']];
            [$male, $female] = SourceFiles::splitArabicTitle($r['title_ar']);

            $add('esco', $unitId, $escoId, 'en', 'preferred', $r['title_en']);
            $add('esco', $unitId, $escoId, 'ar', $female ? 'male' : 'preferred', $male);
            $add('esco', $unitId, $escoId, 'ar', 'female', $female);
            foreach ($r['alt_en'] as $alt) {
                $add('esco', $unitId, $escoId, 'en', 'alt', $alt);
            }
            foreach ($r['alt_ar'] as $alt) {
                $add('esco', $unitId, $escoId, 'ar', 'alt', $alt);
            }
            foreach ($r['hidden_en'] as $alt) {
                $add('esco', $unitId, $escoId, 'en', 'hidden', $alt);
            }
        }

        return $out;
    }

    private function counts(array $isco, array $enoc, array $majors, array $esco, array $labels): array
    {
        $levels = array_count_values(array_column($isco, 'level'));
        // Codes as text: PHP turns numeric array keys ('3221') into integers.
        $units = array_map('strval', array_keys(array_filter($isco, fn ($g) => $g['level'] === 4)));
        $enocCodes = array_map('strval', array_keys($enoc));
        $escoUnits = array_flip(array_column($esco, 'isco_code'));

        $byKind = [];
        foreach ($labels as $l) {
            $k = $l['lang'].'_'.$l['kind'];
            $byKind[$k] = ($byKind[$k] ?? 0) + 1;
        }

        return [
            'isco_major'                => $levels[1] ?? 0,
            'isco_sub_major'            => $levels[2] ?? 0,
            'isco_minor'                => $levels[3] ?? 0,
            'isco_unit'                 => $levels[4] ?? 0,
            'isco_without_arabic'       => count(array_filter($isco, fn ($g) => ! $g['title_ar'])),
            'enoc_major'                => count($majors),
            'enoc_occupations'          => count($enoc),
            'esco_occupations'          => count($esco),
            'esco_without_arabic'       => count(array_filter($esco, fn ($r) => ! $r['title_ar'])),
            'esco_with_arabic_alt'      => count(array_filter($esco, fn ($r) => $r['alt_ar'])),
            'units_without_enoc'        => array_values(array_diff($units, $enocCodes)),
            'units_without_esco'        => array_values(array_filter($units, fn ($u) => ! isset($escoUnits[$u]))),
            'enoc_without_esco'         => count(array_filter($enocCodes, fn ($c) => ! isset($escoUnits[$c]))),
            'labels'                    => count($labels),
            'labels_by_kind'            => $byKind,
        ];
    }

    /** '2654.1.10' → '2654.0001.0010', so text order is natural order. */
    public static function sortKey(string $code): string
    {
        $parts = explode('.', $code);
        $head = array_shift($parts);

        return mb_substr(implode('.', [$head, ...array_map(fn ($p) => str_pad($p, 4, '0', STR_PAD_LEFT), $parts)]), 0, 80);
    }

    private function upsert(string $table, array $rows, array $by, array $update): void
    {
        foreach (array_chunk(array_values($rows), $this->chunk) as $chunk) {
            DB::table($table)->upsert($chunk, $by, $update);
        }
    }
}
