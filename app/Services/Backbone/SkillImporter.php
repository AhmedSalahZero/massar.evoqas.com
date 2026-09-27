<?php

namespace App\Services\Backbone;

use App\Models\Backbone\BackboneImport;
use App\Models\Backbone\SkillImport;
use App\Support\TextNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — SkillImporter (ESCO skills pillar)
//  Location: app/Services/Backbone/SkillImporter.php
//
//  Loads ESCO's skills and knowledge, the skills tree, which skills
//  each ESCO occupation needs (essential / optional), and the links
//  between skills. Run by `php artisan skills:import` (and by
//  BackboneSeeder on a fresh install), always AFTER backbone:import,
//  because skills are linked to the ESCO occupations already loaded.
//
//  SAFE TO RUN AGAIN, ANY TIME — the same promises as BackboneImporter
//  · All-or-nothing: every check runs BEFORE anything is written, and
//    everything is written inside one database transaction. If
//    anything fails, the skills stay exactly as they were and the
//    failure is recorded with a plain-language reason.
//  · Stable IDs: skills and groups are matched by their permanent ESCO
//    URI and updated in place; those a newer edition drops are marked
//    inactive, never deleted. The link tables (and the search labels)
//    are rebuilt: nothing else points at their rows.
//  · Unchanged files are detected by checksum and skipped (--force to
//    import anyway). A new occupation import always allows a new run.
//
//  CHECKS BEFORE WRITING (the import stops if any fails)
//  · the occupation backbone is loaded
//  · every occupation in the links file exists among the ESCO
//    occupations already loaded (else the files are from a different
//    ESCO edition than the occupations)
//  · every skill and group in the tree and link files exists, every
//    group sits in one of the four pillars, and wording such as
//    "essential"/"optional" is the one this code knows
//  Findings that are facts of the data, not errors (knowledge groups
//  ESCO has not translated into Arabic, skills no occupation uses …)
//  are counted and shown on the Backbone screen.
// ══════════════════════════════════════════════════════════════════

class SkillImporter
{
    /** The four pillars of the skills tree, by the code of their top group. */
    public const PILLARS = ['S', 'K', 'L', 'T'];

    private int $chunk;

    public function __construct(private readonly SkillFiles $files)
    {
        $this->chunk = (int) config('backbone.chunk', 500);
    }

    public static function make(): self
    {
        return new self(SkillFiles::fromConfig());
    }

    public function run(bool $force = false, ?int $userId = null, string $via = 'console'): SkillImport
    {
        if (! DB::table('esco_occupations')->exists()) {
            throw new RuntimeException('The occupation backbone is empty. Run `php artisan backbone:import` first, then run this again.');
        }
        $this->raiseMemoryLimit();

        $fingerprints = $this->files->fingerprints();   // throws if a file is missing
        $edition = (string) config('backbone.editions.esco');
        $backboneId = BackboneImport::lastCompleted()?->id;

        $last = SkillImport::lastCompleted();
        if (! $force && $last && $last->files == $fingerprints && $last->edition === $edition && $last->backbone_import_id === $backboneId) {
            return SkillImport::create([
                'status'             => SkillImport::UNCHANGED,
                'edition'            => $edition,
                'files'              => $fingerprints,
                'backbone_import_id' => $backboneId,
                'counts'             => $last->counts,
                'message'            => 'The files are the same as in skills import #'.$last->id.'. Nothing was changed.',
                'user_id'            => $userId,
                'triggered_via'      => $via,
                'started_at'         => now(),
                'finished_at'        => now(),
            ]);
        }

        $import = SkillImport::create([
            'status'             => SkillImport::RUNNING,
            'edition'            => $edition,
            'files'              => $fingerprints,
            'backbone_import_id' => $backboneId,
            'user_id'            => $userId,
            'triggered_via'      => $via,
            'started_at'         => now(),
        ]);

        try {
            $skills = $this->files->skills();
            $groups = $this->files->groups();
            $tree = $this->tree($skills['rows'], $groups, $this->files->broader());

            $occupationIds = DB::table('esco_occupations')->pluck('id', 'uri')->all();
            $links = $this->checkLinks($skills['rows'], $occupationIds);

            $counts = DB::transaction(fn () => $this->write($import->id, $edition, $skills['rows'], $groups, $tree, $occupationIds));
            $counts['skills_duplicate_rows_skipped'] = $skills['duplicates'];
            $counts['occupation_links_repeated'] = $links['repeated'];

            $import->update([
                'status'      => SkillImport::COMPLETED,
                'counts'      => $counts,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $import->update([
                'status'      => SkillImport::FAILED,
                'message'     => mb_substr($e->getMessage(), 0, 2000),
                'finished_at' => now(),
            ]);

            throw $e;
        }

        return $import->refresh();
    }

    // ── Checks ───────────────────────────────────────────────────────

    /**
     * Works out where every group and skill sits, and checks it.
     *
     * @return array{group_parent: array<string, ?string>, group_level: array<string, int>, group_pillar: array<string, string>,
     *               skill_groups: array<string, list<string>>, skill_broader: array<string, list<string>>}
     */
    private function tree(array $skills, array $groups, array $broader): array
    {
        if (count($skills) === 0 || count($groups) === 0) {
            throw new RuntimeException('The ESCO skills files look empty. Nothing was imported.');
        }

        $problems = [];
        foreach ($skills as $s) {
            if ($s['title_en'] === null) {
                $problems[] = "skill without an English name: {$s['uri']}";
            }
            if ($s['type'] === null && $s['raw_type'] !== '') {
                $problems[] = "unknown skill type \"{$s['raw_type']}\"";
            }
            if ($s['reuse'] === null && $s['raw_reuse'] !== '') {
                $problems[] = "unknown reuse level \"{$s['raw_reuse']}\"";
            }
        }
        foreach ($groups as $g) {
            if ($g['title_en'] === null || $g['code'] === '') {
                $problems[] = "skill group without a name or code: {$g['uri']}";
            }
            if (isset($skills[$g['uri']])) {
                $problems[] = "{$g['uri']} is listed both as a skill and as a skill group";
            }
        }
        $this->stopIf($problems, 'The ESCO skills files do not look right');

        $groupParent = array_fill_keys(array_keys($groups), null);
        $skillGroups = [];
        $skillBroader = [];
        foreach ($broader as [$concept, $parent]) {
            $parentIsGroup = isset($groups[$parent]);
            if (! $parentIsGroup && ! isset($skills[$parent])) {
                $problems[] = "unknown broader concept {$parent}";
                continue;
            }
            if (isset($groups[$concept])) {
                if (! $parentIsGroup) {
                    $problems[] = "skill group {$groups[$concept]['code']} sits under a skill";
                } elseif ($groupParent[$concept] !== null && $groupParent[$concept] !== $parent) {
                    $problems[] = "skill group {$groups[$concept]['code']} has two parent groups";
                } else {
                    $groupParent[$concept] = $parent;
                }
            } elseif (isset($skills[$concept])) {
                if ($parentIsGroup) {
                    $skillGroups[$concept][] = $parent;
                } elseif ($parent !== $concept) {
                    $skillBroader[$concept][] = $parent;
                }
            } else {
                $problems[] = "unknown concept {$concept}";
            }
        }
        $this->stopIf($problems, 'The ESCO skills tree file does not match the skills files');

        // Each group's level and pillar, by walking up to its top group.
        $groupLevel = [];
        $groupPillar = [];
        foreach ($groups as $uri => $g) {
            $level = 0;
            $top = $uri;
            while ($groupParent[$top] !== null) {
                $top = $groupParent[$top];
                if (++$level > 10) {
                    $problems[] = "skill group {$g['code']} is inside a loop";
                    continue 2;
                }
            }
            $pillar = $groups[$top]['code'];
            if (! in_array($pillar, self::PILLARS, true)) {
                $problems[] = "skill group {$g['code']} does not sit in one of the four pillars (S, K, L, T) but under \"{$pillar}\"";
                continue;
            }
            $groupLevel[$uri] = $level;
            $groupPillar[$uri] = $pillar;
        }
        $this->stopIf($problems, 'The ESCO skills tree does not look right');

        return [
            'group_parent'  => $groupParent,
            'group_level'   => $groupLevel,
            'group_pillar'  => $groupPillar,
            'skill_groups'  => array_map(fn ($l) => array_values(array_unique($l)), $skillGroups),
            'skill_broader' => array_map(fn ($l) => array_values(array_unique($l)), $skillBroader),
        ];
    }

    /** Reads both link files once, before anything is written. */
    private function checkLinks(array $skills, array $occupationIds): array
    {
        $unknownOccupations = [];
        $unknownSkills = [];
        $seen = [];
        $repeated = 0;

        foreach ($this->files->occupationLinks() as [$occupation, $skill, $type]) {
            SkillFiles::isEssential($type);   // throws on unknown wording
            if (! isset($occupationIds[$occupation])) {
                $unknownOccupations[$occupation] = true;
            }
            if (! isset($skills[$skill])) {
                $unknownSkills[$skill] = true;
            }
            $key = $occupation.'|'.$skill;
            if (isset($seen[$key])) {
                $repeated++;
            }
            $seen[$key] = true;
        }
        unset($seen);

        if ($unknownOccupations) {
            throw new RuntimeException(sprintf(
                '%d ESCO occupation(s) in %s are not in the occupation backbone, for example %s. The skills files are probably from a different ESCO edition than the occupation files. Nothing was imported.',
                count($unknownOccupations), $this->files->name('occupations'), implode(', ', array_slice(array_keys($unknownOccupations), 0, 3)),
            ));
        }

        foreach ($this->files->relatedLinks() as [$skill, $related, $type]) {
            SkillFiles::isEssential($type);
            foreach ([$skill, $related] as $uri) {
                if (! isset($skills[$uri])) {
                    $unknownSkills[$uri] = true;
                }
            }
        }
        if ($unknownSkills) {
            throw new RuntimeException(sprintf(
                '%d skill(s) in the link files are not in %s, for example %s. Nothing was imported.',
                count($unknownSkills), $this->files->name('skills_en'), implode(', ', array_slice(array_keys($unknownSkills), 0, 3)),
            ));
        }

        return ['repeated' => $repeated];
    }

    private function stopIf(array $problems, string $what): void
    {
        if ($problems) {
            $problems = array_values(array_unique($problems));
            throw new RuntimeException($what.': '.implode('; ', array_slice($problems, 0, 10))
                .(count($problems) > 10 ? ' (and '.(count($problems) - 10).' more)' : '').'. Nothing was imported.');
        }
    }

    // ── Writing (inside the transaction) ─────────────────────────────

    private function write(int $importId, string $edition, array $skills, array $groups, array $tree, array $occupationIds): array
    {
        $now = Carbon::now();

        // 1 · The skills tree — rows first, then the parent links.
        $groupRow = fn (array $g, ?int $parentId) => [
            'uri' => $g['uri'], 'code' => mb_substr($g['code'], 0, 20), 'pillar' => $tree['group_pillar'][$g['uri']],
            'level' => $tree['group_level'][$g['uri']], 'parent_id' => $parentId, 'sort_key' => self::sortKey($g['code']),
            'title_en' => mb_substr($g['title_en'], 0, 255), 'title_ar' => $g['title_ar'] ? mb_substr($g['title_ar'], 0, 255) : null,
            'description_en' => $g['description_en'], 'description_ar' => $g['description_ar'], 'scope_note_en' => $g['scope_note_en'],
            'is_active' => true, 'last_import_id' => $importId, 'created_at' => $now, 'updated_at' => $now,
        ];
        $groupUpdate = ['code', 'pillar', 'level', 'sort_key', 'title_en', 'title_ar', 'description_en', 'description_ar',
            'scope_note_en', 'is_active', 'last_import_id', 'updated_at'];

        $this->upsert('esco_skill_groups', array_map(fn ($g) => $groupRow($g, null), $groups), ['uri'], $groupUpdate);
        $groupIds = DB::table('esco_skill_groups')->pluck('id', 'uri')->all();
        $this->upsert('esco_skill_groups', array_map(
            fn ($g) => $groupRow($g, ($p = $tree['group_parent'][$g['uri']]) ? $groupIds[$p] : null),
            $groups,
        ), ['uri'], [...$groupUpdate, 'parent_id']);
        $this->retire('esco_skill_groups', $importId, $now);

        // 2 · Skills and knowledge.
        $version = preg_match('/v?(\d+(?:\.\d+)+)/', $edition, $m) ? $m[1] : null;
        $this->upsert('esco_skills', array_map(fn ($s) => [
            'uri' => $s['uri'], 'type' => $s['type'], 'reuse_level' => $s['reuse'],
            'title_en' => mb_substr($s['title_en'], 0, 255), 'title_ar' => $s['title_ar'] ? mb_substr($s['title_ar'], 0, 255) : null,
            'description_en' => $s['description_en'], 'description_ar' => $s['description_ar'], 'scope_note_en' => $s['scope_note_en'],
            'esco_version' => $version, 'esco_modified_at' => $s['modified'] ? Carbon::parse($s['modified']) : null,
            'is_active' => true, 'last_import_id' => $importId, 'created_at' => $now, 'updated_at' => $now,
        ], $skills), ['uri'], ['type', 'reuse_level', 'title_en', 'title_ar', 'description_en', 'description_ar', 'scope_note_en',
            'esco_version', 'esco_modified_at', 'is_active', 'last_import_id', 'updated_at']);
        $this->retire('esco_skills', $importId, $now);
        $skillIds = DB::table('esco_skills')->where('last_import_id', $importId)->pluck('id', 'uri')->all();

        // 3 · Where each skill sits (rebuilt).
        DB::table('esco_skill_parents')->delete();
        $parents = [];
        foreach ($tree['skill_groups'] as $uri => $list) {
            foreach ($list as $g) {
                $parents[] = ['skill_id' => $skillIds[$uri], 'group_id' => $groupIds[$g], 'broader_skill_id' => null];
            }
        }
        foreach ($tree['skill_broader'] as $uri => $list) {
            foreach ($list as $b) {
                $parents[] = ['skill_id' => $skillIds[$uri], 'group_id' => null, 'broader_skill_id' => $skillIds[$b]];
            }
        }
        $this->insert('esco_skill_parents', $parents);

        // 4 · Occupation ↔ skill (rebuilt, streamed). A pair listed twice
        //     is stored once; "essential" wins over "optional".
        DB::table('esco_occupation_skills')->delete();
        $pairs = [];
        foreach ($this->files->occupationLinks() as [$occupation, $skill, $type]) {
            $key = $occupationIds[$occupation].'|'.$skillIds[$skill];
            $pairs[$key] = ($pairs[$key] ?? false) || SkillFiles::isEssential($type);
        }
        $essential = 0;
        $usedSkills = [];
        $linkedOccupations = [];
        $buffer = [];
        foreach ($pairs as $key => $isEssential) {
            [$occupationId, $skillId] = explode('|', $key);
            $buffer[] = ['esco_occupation_id' => (int) $occupationId, 'skill_id' => (int) $skillId, 'is_essential' => $isEssential];
            $essential += $isEssential ? 1 : 0;
            $usedSkills[$skillId] = true;
            $linkedOccupations[$occupationId] = true;
            if (count($buffer) >= $this->chunk) {
                DB::table('esco_occupation_skills')->insert($buffer);
                $buffer = [];
            }
        }
        $this->insert('esco_occupation_skills', $buffer);
        $linkCount = count($pairs);
        unset($pairs, $buffer);

        // 5 · Skill ↔ skill (rebuilt).
        DB::table('esco_skill_relations')->delete();
        $related = [];
        $selfLinks = 0;
        foreach ($this->files->relatedLinks() as [$skill, $other, $type]) {
            if ($skill === $other) {
                $selfLinks++;   // ESCO v1.2.1 lists one skill as related to itself
            } else {
                $key = $skillIds[$skill].'|'.$skillIds[$other];
                $related[$key] = ($related[$key] ?? false) || SkillFiles::isEssential($type);
            }
        }
        $this->insert('esco_skill_relations', array_map(function ($key, $isEssential) {
            [$a, $b] = explode('|', $key);

            return ['skill_id' => (int) $a, 'related_skill_id' => (int) $b, 'is_essential' => $isEssential];
        }, array_keys($related), $related));

        // 6 · Search labels (rebuilt, written as they are made).
        DB::table('skill_labels')->delete();
        $labels = ['en' => 0, 'ar' => 0];
        $buffer = [];
        foreach ($skills as $uri => $s) {
            $seen = [];
            $add = function (string $lang, string $kind, ?string $label) use (&$buffer, &$seen, &$labels, $skillIds, $uri) {
                $label = $label === null ? '' : trim($label);
                $normalized = TextNormalizer::normalize($label);
                if ($normalized === '' || isset($seen[$lang.'|'.$normalized])) {
                    return;
                }
                $seen[$lang.'|'.$normalized] = true;
                $labels[$lang]++;
                $buffer[] = ['skill_id' => $skillIds[$uri], 'lang' => $lang, 'kind' => $kind,
                    'label' => mb_substr($label, 0, 255), 'normalized' => mb_substr($normalized, 0, 255)];
            };
            $add('en', 'preferred', $s['title_en']);
            $add('ar', 'preferred', $s['title_ar']);
            foreach ($s['alt_en'] as $alt) {
                $add('en', 'alt', $alt);
            }
            foreach ($s['alt_ar'] as $alt) {
                $add('ar', 'alt', $alt);
            }
            foreach ($s['hidden_en'] as $alt) {
                $add('en', 'hidden', $alt);
            }
            if (count($buffer) >= $this->chunk) {
                DB::table('skill_labels')->insert($buffer);
                $buffer = [];
            }
        }
        $this->insert('skill_labels', $buffer);

        // 7 · What was loaded, and the facts found on the way.
        $activeOccupations = DB::table('esco_occupations')->where('is_active', true)->pluck('code', 'id')->all();
        $withoutSkills = array_diff_key($activeOccupations, $linkedOccupations);
        asort($withoutSkills);

        $types = array_count_values(array_map(fn ($s) => $s['type'] ?? 'none', $skills));
        $reuse = array_count_values(array_map(fn ($s) => $s['reuse'] ?? 'none', $skills));
        $pillarGroups = array_count_values($tree['group_pillar']);
        $pillarNoArabic = array_count_values(array_map(fn ($g) => $tree['group_pillar'][$g['uri']], array_filter($groups, fn ($g) => ! $g['title_ar'])));
        $sameAsEnglish = array_filter($skills, fn ($s) => $s['title_ar'] !== null && ! preg_match('/\p{Arabic}/u', $s['title_ar']));

        return [
            'skills'                     => count($skills),
            'skills_by_type'             => $types,
            'skills_by_reuse'            => $reuse,
            'skills_without_type'        => array_values(array_map(fn ($s) => $s['title_en'], array_filter($skills, fn ($s) => $s['type'] === null))),
            'skills_without_arabic'      => count(array_filter($skills, fn ($s) => ! $s['title_ar'])),
            'skills_arabic_in_latin'     => count($sameAsEnglish),
            'skills_with_arabic_alt'     => count(array_filter($skills, fn ($s) => $s['alt_ar'])),
            'skills_without_group'       => count(array_diff_key($skills, $tree['skill_groups'])),
            'skills_without_occupation'  => count($skills) - count($usedSkills),
            'groups'                     => count($groups),
            'groups_by_pillar'           => $pillarGroups,
            'groups_without_arabic'      => array_sum($pillarNoArabic),
            'groups_without_arabic_by_pillar' => $pillarNoArabic,
            'placements'                 => count($parents),
            'occupation_links'           => $linkCount,
            'occupation_links_essential' => $essential,
            'occupation_links_optional'  => $linkCount - $essential,
            'occupations_with_skills'    => count($linkedOccupations),
            'occupations_without_skills' => array_values(array_slice($withoutSkills, 0, 50)),
            'occupations_without_skills_count' => count($withoutSkills),
            'related_links'              => count($related),
            'related_self_links_skipped' => $selfLinks,
            'labels'                     => $labels['en'] + $labels['ar'],
            'labels_en'                  => $labels['en'],
            'labels_ar'                  => $labels['ar'],
        ];
    }

    /** Rows this import did not touch belong to an older edition: marked inactive, never deleted. */
    private function retire(string $table, int $importId, Carbon $now): void
    {
        DB::table($table)
            ->where(fn ($q) => $q->where('last_import_id', '<>', $importId)->orWhereNull('last_import_id'))
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    /** 'S1.12' → 'S0001.0012' so text order is natural order. ISCED codes ('0715') already sort correctly. */
    public static function sortKey(string $code): string
    {
        if (! preg_match('/^[A-Z]/', $code)) {
            return mb_substr($code, 0, 60);
        }

        return mb_substr(preg_replace_callback('/\d+/', fn ($m) => str_pad($m[0], 4, '0', STR_PAD_LEFT), $code), 0, 60);
    }

    /** Big imports need more than PHP's default 128 MB. Never lowers a higher limit. */
    private function raiseMemoryLimit(): void
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '-1') {
            return;
        }
        $bytes = (int) $limit * match (strtoupper(substr($limit, -1))) { 'G' => 1 << 30, 'M' => 1 << 20, 'K' => 1 << 10, default => 1 };
        if ($bytes < 1 << 30) {
            @ini_set('memory_limit', '1G');
        }
    }

    private function upsert(string $table, array $rows, array $by, array $update): void
    {
        foreach (array_chunk(array_values($rows), $this->chunk) as $chunk) {
            DB::table($table)->upsert($chunk, $by, $update);
        }
    }

    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, $this->chunk) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
