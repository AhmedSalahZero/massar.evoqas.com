<?php

namespace App\Services\Backbone;

use App\Models\Backbone\EscoSkill;
use App\Models\Backbone\EscoSkillGroup;
use App\Models\Backbone\SkillImport;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — SkillCatalog (ESCO skills on the screens)
//  Location: app/Services/Backbone/SkillCatalog.php
//
//  Everything a screen needs to show the ESCO skills, in one place, so
//  the Super Admin screens and the partner Occupations pages always
//  show the same thing:
//    · forOccupation()  the skills of one ESCO job, essential / optional
//    · forUnit()        the skills most ESCO jobs in a 4-digit group
//                       (= one ENOC occupation) need
//    · skillPage()      one skill: descriptions, where it sits in the
//                       skills tree, related skills, the jobs that need it
//    · search()         find skills by any name, Arabic or English
//    · summary()        what is loaded + the data checks (Super Admin)
//  Every method returns null / empty when the skills are not loaded
//  yet, so the pages keep working before `skills:import` is run.
// ══════════════════════════════════════════════════════════════════

class SkillCatalog
{
    /** Jobs listed on a skill page, per kind (essential / optional). */
    private const OCCUPATIONS_SHOWN = 200;

    /** Skills listed for a 4-digit group. */
    private const UNIT_SKILLS_SHOWN = 30;

    public function __construct(private readonly SkillSearch $search) {}

    public function loaded(): bool
    {
        return DB::table('esco_skills')->exists();
    }

    /** @return array{essential: list<array>, optional: list<array>}|null */
    public function forOccupation(int $escoOccupationId): ?array
    {
        if (! $this->loaded()) {
            return null;
        }

        $rows = DB::table('esco_occupation_skills')
            ->join('esco_skills', 'esco_skills.id', '=', 'esco_occupation_skills.skill_id')
            ->where('esco_occupation_skills.esco_occupation_id', $escoOccupationId)
            ->where('esco_skills.is_active', true)
            ->orderBy('esco_skills.title_en')
            ->get(['esco_skills.id', 'esco_skills.title_en', 'esco_skills.title_ar', 'esco_skills.type', 'esco_skills.reuse_level', 'esco_occupation_skills.is_essential']);

        return [
            'essential' => $rows->where('is_essential', true)->map(fn ($r) => $this->brief($r))->values()->all(),
            'optional'  => $rows->where('is_essential', false)->map(fn ($r) => $this->brief($r))->values()->all(),
        ];
    }

    /**
     * The skills that most ESCO jobs in this group list as essential.
     *
     * @return array{jobs: int, skills: list<array>, total: int}|null
     */
    public function forUnit(int $unitId): ?array
    {
        if (! $this->loaded()) {
            return null;
        }

        $jobs = DB::table('esco_occupations')->where('isco_group_id', $unitId)->where('is_active', true)->count();
        if ($jobs === 0) {
            return ['jobs' => 0, 'skills' => [], 'total' => 0];
        }

        $base = DB::table('esco_occupation_skills')
            ->join('esco_occupations', 'esco_occupations.id', '=', 'esco_occupation_skills.esco_occupation_id')
            ->join('esco_skills', 'esco_skills.id', '=', 'esco_occupation_skills.skill_id')
            ->where('esco_occupations.isco_group_id', $unitId)
            ->where('esco_occupations.is_active', true)
            ->where('esco_skills.is_active', true)
            ->where('esco_occupation_skills.is_essential', true);

        $total = (clone $base)->distinct()->count('esco_occupation_skills.skill_id');
        $rows = (clone $base)
            ->groupBy('esco_skills.id', 'esco_skills.title_en', 'esco_skills.title_ar', 'esco_skills.type', 'esco_skills.reuse_level')
            ->orderByDesc('jobs')->orderBy('esco_skills.title_en')
            ->limit(self::UNIT_SKILLS_SHOWN)
            ->get(['esco_skills.id', 'esco_skills.title_en', 'esco_skills.title_ar', 'esco_skills.type', 'esco_skills.reuse_level', DB::raw('count(*) as jobs')]);

        return [
            'jobs'   => $jobs,
            'total'  => $total,
            'skills' => $rows->map(fn ($r) => $this->brief($r) + ['jobs' => (int) $r->jobs])->all(),
        ];
    }

    /** Page data for one skill. */
    public function skillPage(EscoSkill $skill): array
    {
        $labels = DB::table('skill_labels')->where('skill_id', $skill->id)->where('kind', 'alt')->orderBy('label')->get(['lang', 'label']);

        $groups = EscoSkillGroup::query()
            ->whereIn('id', DB::table('esco_skill_parents')->where('skill_id', $skill->id)->whereNotNull('group_id')->select('group_id'))
            ->orderBy('sort_key')->get();

        $skillsOf = fn ($ids) => DB::table('esco_skills')->whereIn('id', $ids)->where('is_active', true)->orderBy('title_en')
            ->get(['id', 'title_en', 'title_ar', 'type', 'reuse_level'])->map(fn ($r) => $this->brief($r))->all();

        $relatedOf = fn (string $from, string $to) => DB::table('esco_skill_relations')
            ->join('esco_skills', 'esco_skills.id', '=', "esco_skill_relations.{$to}")
            ->where("esco_skill_relations.{$from}", $skill->id)->where('esco_skills.is_active', true)
            ->orderByDesc('esco_skill_relations.is_essential')->orderBy('esco_skills.title_en')
            ->get(['esco_skills.id', 'esco_skills.title_en', 'esco_skills.title_ar', 'esco_skills.type', 'esco_skills.reuse_level', 'esco_skill_relations.is_essential'])
            ->map(fn ($r) => $this->brief($r) + ['essential' => (bool) $r->is_essential])->all();

        $occupations = function (bool $essential) use ($skill) {
            $q = DB::table('esco_occupation_skills')
                ->join('esco_occupations', 'esco_occupations.id', '=', 'esco_occupation_skills.esco_occupation_id')
                ->where('esco_occupation_skills.skill_id', $skill->id)
                ->where('esco_occupation_skills.is_essential', $essential)
                ->where('esco_occupations.is_active', true);

            return [
                'total' => (clone $q)->count(),
                'rows'  => $q->orderBy('esco_occupations.sort_key')->limit(self::OCCUPATIONS_SHOWN)
                    ->get(['esco_occupations.id', 'esco_occupations.code', 'esco_occupations.isco_code', 'esco_occupations.title_en', 'esco_occupations.title_ar_male', 'esco_occupations.title_ar_female'])
                    ->map(fn ($o) => ['id' => $o->id, 'code' => $o->code, 'isco_code' => $o->isco_code, 'title_en' => $o->title_en, 'title_ar' => $o->title_ar_male, 'title_ar_f' => $o->title_ar_female])
                    ->all(),
            ];
        };

        return [
            'skill' => [
                'id'             => $skill->id,
                'uri'            => $skill->uri,
                'type'           => $skill->type,
                'reuse'          => $skill->reuse_level,
                'title_en'       => $skill->title_en,
                'title_ar'       => $skill->title_ar,
                'description_en' => $skill->description_en,
                'description_ar' => $skill->description_ar,
                'scope_note_en'  => $skill->scope_note_en,
                'active'         => $skill->is_active,
                'version'        => $skill->esco_version,
                'alt_en'         => $labels->where('lang', 'en')->pluck('label')->values(),
                'alt_ar'         => $labels->where('lang', 'ar')->pluck('label')->values(),
            ],
            'paths'    => $groups->map(fn (EscoSkillGroup $g) => array_map(fn (EscoSkillGroup $n) => $this->groupBrief($n), $g->lineage()))->all(),
            'broader'  => $skillsOf(DB::table('esco_skill_parents')->where('skill_id', $skill->id)->whereNotNull('broader_skill_id')->pluck('broader_skill_id')),
            'narrower' => $skillsOf(DB::table('esco_skill_parents')->where('broader_skill_id', $skill->id)->pluck('skill_id')),
            'needs'    => $relatedOf('skill_id', 'related_skill_id'),
            'needed_by'=> $relatedOf('related_skill_id', 'skill_id'),
            'occupations' => [
                'essential' => $occupations(true),
                'optional'  => $occupations(false),
                'shown'     => self::OCCUPATIONS_SHOWN,
            ],
        ];
    }

    /** Up to $limit skills for a search text, best first. */
    public function search(string $text, int $limit = 40): array
    {
        if (! $this->loaded() || trim($text) === '') {
            return [];
        }
        $ranked = array_slice($this->search->skills($text), 0, $limit, true);
        if (! $ranked) {
            return [];
        }
        $rows = DB::table('esco_skills')->whereIn('id', array_keys($ranked))->get(['id', 'title_en', 'title_ar', 'type', 'reuse_level'])->keyBy('id');

        $out = [];
        foreach ($ranked as $id => $m) {
            if (isset($rows[$id])) {
                $out[] = $this->brief($rows[$id]) + ['match' => ['label' => $m['label'], 'lang' => $m['lang'], 'kind' => $m['kind']]];
            }
        }

        return $out;
    }

    /** What is loaded and what the last import found (Super Admin). */
    public function summary(): array
    {
        $last = SkillImport::lastCompleted();

        return [
            'loaded'  => $this->loaded(),
            'counts'  => [
                'skills'      => DB::table('esco_skills')->where('is_active', true)->count(),
                'knowledge'   => DB::table('esco_skills')->where('is_active', true)->where('type', EscoSkill::KNOWLEDGE)->count(),
                'groups'      => DB::table('esco_skill_groups')->where('is_active', true)->count(),
                'links'       => DB::table('esco_occupation_skills')->count(),
                'essential'   => DB::table('esco_occupation_skills')->where('is_essential', true)->count(),
                'labels'      => DB::table('skill_labels')->count(),
            ],
            'last'    => $last ? [
                'id'      => $last->id,
                'at'      => $last->finished_at?->toIso8601String(),
                'edition' => $last->edition,
                'checks'  => array_intersect_key($last->counts ?? [], array_flip([
                    'skills_duplicate_rows_skipped', 'groups_without_arabic', 'skills_arabic_in_latin', 'skills_with_arabic_alt',
                    'skills_without_occupation', 'occupations_without_skills', 'occupations_without_skills_count',
                    'skills_without_type', 'related_self_links_skipped',
                ])),
            ] : null,
            'imports' => SkillImport::query()->with('user:id,name')->latest('id')->limit(5)->get()->map(fn (SkillImport $i) => [
                'id'         => $i->id,
                'status'     => $i->status,
                'message'    => $i->message,
                'skills'     => $i->counts['skills'] ?? null,
                'links'      => $i->counts['occupation_links'] ?? null,
                'by'         => $i->user?->name,
                'started_at' => $i->started_at?->toIso8601String(),
                'seconds'    => $i->started_at && $i->finished_at ? $i->started_at->diffInSeconds($i->finished_at) : null,
            ])->all(),
        ];
    }

    private function brief(object $r): array
    {
        return ['id' => (int) $r->id, 'title_en' => $r->title_en, 'title_ar' => $r->title_ar, 'type' => $r->type, 'reuse' => $r->reuse_level];
    }

    private function groupBrief(EscoSkillGroup $g): array
    {
        return ['code' => $g->code, 'level' => $g->level, 'pillar' => $g->pillar, 'title_en' => $g->title_en, 'title_ar' => $g->title_ar];
    }
}
