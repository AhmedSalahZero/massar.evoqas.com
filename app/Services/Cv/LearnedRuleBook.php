<?php

namespace App\Services\Cv;

use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Models\LearnedRule;
use App\Services\Beneficiaries\OccupationDisplay;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — LearnedRuleBook (Scope v2 §3 Learned Rules)
//  Location: app/Services/Cv/LearnedRuleBook.php
//
//  The one place that knows how rules are written, found and used:
//
//    key($kind, $phrase)       how a rule is matched ("Career Path" and
//                              "CAREER PATHS:" are the same heading)
//    forReading($companyId)    the rules a CV of this workspace is read
//                              with — the workspace's own rules first,
//                              then the Massar rules (a workspace rule
//                              for the same words wins)
//    teach(…)                  a person decided: save the rule in the
//                              workspace (the same words again = the
//                              rule is changed to the new decision)
//    used($ids)                count the CVs read with each rule
//
//  Order when reading a CV: workspace rule → Massar rule → the
//  built-in word list (CvDictionary). For occupations the Scope's
//  order stays: 1 exact match, 2 learned rules, 3 close matches.
// ══════════════════════════════════════════════════════════════════

class LearnedRuleBook
{
    public function __construct(private readonly CvReader $reader) {}

    public function key(string $kind, string $phrase): string
    {
        return match ($kind) {
            'heading' => $this->reader->headingRuleKey($phrase),
            'title'   => TextNormalizer::normalize(OccupationClassifier::withoutNoise($phrase)),
            default   => TextNormalizer::normalize($phrase),
        };
    }

    /**
     * @return array{
     *   headings: list<array{id: int, phrase: string, section: string}>,
     *   titles: array<string, array{id: int, esco_id: ?int, unit: ?string}>,
     *   skills: list<array{id: int, key: string, name: string, phrase: string}>,
     *   employers: list<array{id: int, key: string}>,
     *   known_employers: list<string>
     * }
     */
    public function forReading(?int $companyId): array
    {
        $book = ['headings' => [], 'titles' => [], 'skills' => [], 'employers' => [], 'known_employers' => $this->knownEmployers($companyId)];
        try {
            $rows = LearnedRule::query()->forReading($companyId)
                ->orderByRaw('CASE WHEN company_id IS NULL THEN 0 ELSE 1 END')   // Massar first, the workspace overwrites
                ->orderBy('id')->get();
        } catch (\Throwable) {
            return $book;           // the table does not exist yet (before `php artisan migrate`)
        }
        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r->kind.'|'.$r->normalized] = $r;
        }
        foreach ($byKey as $r) {
            match ($r->kind) {
                'heading' => $book['headings'][] = ['id' => $r->id, 'phrase' => $r->phrase, 'section' => $r->section],
                'title'   => $book['titles'][$r->normalized] = ['id' => $r->id, 'esco_id' => $r->esco_occupation_id, 'unit' => $r->occupation_unit],
                'skill'   => $book['skills'][] = ['id' => $r->id, 'key' => $r->normalized, 'name' => $r->skill_name ?: $r->phrase, 'phrase' => $r->phrase],
                'employer' => $book['employers'][] = ['id' => $r->id, 'key' => $r->normalized],
                default   => null,
            };
        }

        return $book;
    }

    /**
     * The employers already typed in this workspace's profiles (the latest
     * 3,000 profiles): each CV a person approved teaches the reader its
     * employers. Kept for 10 minutes, so a batch of 50 CVs asks once.
     *
     * @return list<string>
     */
    public function knownEmployers(?int $companyId): array
    {
        if (! $companyId) {
            return [];
        }
        try {
            return Cache::remember("cv.known_employers.{$companyId}", 600, function () use ($companyId) {
                $names = [];
                $rows = DB::table('beneficiaries')->where('company_id', $companyId)
                    ->whereNotNull('work_history')->orderByDesc('id')->limit(3000)->pluck('work_history');
                foreach ($rows as $json) {
                    foreach ((is_string($json) ? json_decode($json, true) : $json) ?: [] as $job) {
                        $name = trim((string) ($job['employer'] ?? ''));
                        if (mb_strlen($name) >= 2 && mb_strlen($name) <= 100) {
                            $names[mb_strtolower($name)] = $name;
                        }
                    }
                }

                return array_values($names);
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Save what a person decided, in their workspace.
     *
     * @param  array{section?: ?string, esco_occupation_id?: ?int, occupation_unit?: ?string, skill_name?: ?string}  $target
     */
    public function teach(int $companyId, User $by, string $kind, string $phrase, array $target, string $source = 'page'): LearnedRule
    {
        $phrase = trim(preg_replace('/\s+/u', ' ', $phrase) ?? $phrase);
        if ($kind === 'heading') {
            $phrase = trim(rtrim($phrase, " :：-–—|")) ?: $phrase;     // "Career Path:" → "Career Path"
        }
        $key = $this->key($kind, $phrase);
        $values = [
            'phrase'             => mb_substr($phrase, 0, 120),
            'section'            => $kind === 'heading' ? ($target['section'] ?? null) : null,
            'esco_occupation_id' => $kind === 'title' ? ($target['esco_occupation_id'] ?? null) : null,
            'occupation_unit'    => $kind === 'title' && empty($target['esco_occupation_id']) ? ($target['occupation_unit'] ?? null) : null,
            'skill_name'         => $kind === 'skill' ? (trim((string) ($target['skill_name'] ?? '')) ?: null) : null,
        ];

        return DB::transaction(function () use ($companyId, $by, $kind, $key, $values, $source) {
            $rule = LearnedRule::query()->inWorkspace($companyId)->where('kind', $kind)->where('normalized', $key)->lockForUpdate()->first();
            if ($rule) {
                $changed = $rule->section !== $values['section'] || (int) $rule->esco_occupation_id !== (int) $values['esco_occupation_id']
                    || $rule->occupation_unit !== $values['occupation_unit'] || $rule->skill_name !== $values['skill_name'];
                // The same words: the rule keeps the way it was first written.
                $rule->fill(array_diff_key($values, ['phrase' => 1]));
                // A new meaning is a new decision: a proposal about the old one no longer stands.
                if ($changed && $rule->proposal_status === LearnedRule::PENDING) {
                    $rule->proposal_status = null;
                }
                $rule->save();

                return $rule;
            }

            return LearnedRule::query()->create($values + [
                'company_id' => $companyId, 'kind' => $kind, 'normalized' => $key, 'source' => $source,
                'created_by' => $by->id, 'created_by_name' => mb_substr($by->name, 0, 100),
            ]);
        });
    }

    /** One rule as the Learned Rules and Rule Requests screens show it. */
    public function present(LearnedRule $r, bool $forAdmin = false): array
    {
        $occupation = null;
        if ($r->kind === 'title') {
            if ($r->esco_occupation_id && ($e = EscoOccupation::query()->with('iscoGroup.enoc')->find($r->esco_occupation_id))) {
                $occupation = OccupationDisplay::block($e->iscoGroup, $e, null);
            } elseif ($r->occupation_unit && ($u = IscoGroup::query()->units()->with('enoc')->where('code', $r->occupation_unit)->first())) {
                $occupation = OccupationDisplay::block($u, null, null);
            }
        }

        return [
            'id'         => $r->id,
            'kind'       => $r->kind,
            'phrase'     => $r->phrase,
            'section'    => $r->section,
            'occupation' => $occupation,
            'skill_name' => $r->skill_name,
            'massar'     => $r->isMassar(),
            'source'     => $r->source,
            'uses'       => $r->uses,
            'last_used_at' => $r->last_used_at?->toIso8601String(),
            'by'         => $r->created_by_name,
            'at'         => $r->created_at?->toIso8601String(),
            'proposal'   => $r->proposal_status ? [
                'status' => $r->proposal_status, 'by' => $r->proposed_by_name, 'at' => $r->proposed_at?->toIso8601String(),
                'decided_by' => $r->decided_by_name, 'decided_at' => $r->decided_at?->toIso8601String(), 'note' => $r->decision_note,
            ] : null,
        ] + ($forAdmin ? [
            'company' => $r->company?->name ?? $r->promoted_from_company,
        ] : []);
    }

    /** A Massar rule for the same words, if there is one. */
    public function massarTwin(LearnedRule $r): ?LearnedRule
    {
        return LearnedRule::query()->massar()->where('kind', $r->kind)->where('normalized', $r->normalized)->first();
    }

    /** Two rules mean the same thing. */
    public static function sameMeaning(LearnedRule $a, LearnedRule $b): bool
    {
        return $a->section === $b->section && (int) $a->esco_occupation_id === (int) $b->esco_occupation_id
            && $a->occupation_unit === $b->occupation_unit && $a->skill_name === $b->skill_name;
    }

    /** @param list<int> $ids */
    public function used(array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids) {
            LearnedRule::query()->whereIn('id', $ids)->update(['uses' => DB::raw('uses + 1'), 'last_used_at' => now()]);
        }
    }
}
