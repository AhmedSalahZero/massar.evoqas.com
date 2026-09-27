<?php

namespace App\Services\Cv;

use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Services\Beneficiaries\OccupationDisplay;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — OccupationClassifier
//  Location: app/Services/Cv/OccupationClassifier.php
//  Scope v2 §3 Occupation Auto-Classification
//
//  Classifies the job titles found on a CV, in the Scope's fixed order:
//
//   1. EXACT MATCH — the title, written exactly like an ENOC title, an
//      ESCO title or alternative title, or an ISCO-08 name, in Arabic
//      or English (spelling differences like أ/ا, ة/ه, "ال" and upper /
//      lower case do not count). "Senior" / "Junior" / "أول" in front
//      of a title are ignored ("Senior Accountant" = accountant).
//      Official titles come before alternative titles, so "accountant"
//      is the ESCO job accountant, not "financial controller" (which
//      lists "accountant" only as an alternative name).
//      → exactly ONE occupation at the best level: chosen automatically.
//      → several occupations equally ("sales executive": sales
//        representative / sales manager): flagged for a person, never
//        guessed.
//   2. LEARNED RULES — titles a person decided before, in this
//      workspace or promoted to Massar (LearnedRuleBook). Used when no
//      title matched ONE occupation exactly — so a rule also settles a
//      title that matched several ("Sales Executive"). A rule is a
//      person's decision: the CV can be added automatically (status
//      'rule', occupation method cv_rule).
//   3. CLOSE MATCHES — the occupation search's best results, each with
//      a match score, shown to the reviewer to choose from.
//   4. Otherwise the reviewer searches the full list (the picker).
//
//  The titles are tried in the order the reader found them: the
//  "Job title:" line, the line under the name, then the jobs, newest
//  first. The first exact single match wins — but if an earlier title
//  was unclear, only when the match is one of its possibilities.
//
//  Result:
//    status      exact | ambiguous | close | none
//    title       the CV title it is about
//    choice      the chosen occupation (exact only), as the form's block
//    candidates  [{block, score (0–100), match}] for the reviewer
// ══════════════════════════════════════════════════════════════════

class OccupationClassifier
{
    /** Label kinds, best first. */
    private const TIERS = [['preferred', 'male', 'female'], ['alt'], ['hidden']];

    public function __construct(private readonly OccupationDisplay $display) {}

    /**
     * @param  list<string>  $titles
     * @param  array<string, array{id: int, esco_id: ?int, unit: ?string}>  $rules  LearnedRuleBook::forReading()['titles']
     */
    public function classify(array $titles, ?string $gender = null, array $rules = []): array
    {
        $titles = array_values(array_filter($titles, fn ($t) => TextNormalizer::normalize($t) !== ''));
        if (! $titles || ! DB::table('occupation_labels')->exists()) {
            return ['status' => 'none', 'title' => $titles[0] ?? null, 'choice' => null, 'candidates' => []];
        }

        $firstAmbiguous = null;
        foreach ($titles as $title) {
            $result = $this->exact($title, $gender);
            if ($result && $result['status'] === 'exact') {
                // An earlier, more important title was unclear: the exact match only
                // counts if it is one of that title's possible occupations (the CV
                // agrees with itself). Otherwise a person decides.
                if ($firstAmbiguous && ! $this->among($result['choice'], $firstAmbiguous['candidates'])) {
                    return $firstAmbiguous;
                }

                return $result;
            }
            $firstAmbiguous ??= $result;
        }
        // ── 2. Learned rules ──────────────────────────────────────────
        if ($rules) {
            foreach ($titles as $title) {
                foreach ([TextNormalizer::normalize(self::withoutNoise($title)), TextNormalizer::normalize($title)] as $key) {
                    if (($rule = $rules[$key] ?? null) && ($block = $this->ruleBlock($rule, $gender))) {
                        return ['status' => 'rule', 'title' => $title, 'choice' => $block, 'rule_id' => $rule['id'],
                            'candidates' => [['block' => $block, 'match' => $title, 'score' => 100]]];
                    }
                }
            }
        }
        if ($firstAmbiguous) {
            return $firstAmbiguous;
        }

        // ── 3. Close matches for the first title ──────────────────────
        $title = $titles[0];
        $candidates = [];
        foreach ($this->display->pick(self::withoutNoise($title), $gender, 5) as $hit) {
            $candidates[] = ['block' => $hit['block'], 'match' => $hit['match'], 'score' => $this->closeScore($title, (string) $hit['match'])];
        }

        return ['status' => $candidates ? 'close' : 'none', 'title' => $title, 'choice' => null, 'candidates' => $candidates];
    }

    /** Step 1 for one title, or null when no title matches exactly. */
    private function exact(string $title, ?string $gender): ?array
    {
        $forms = array_values(array_unique(array_filter([
            TextNormalizer::normalize($title),
            TextNormalizer::normalize(self::withoutNoise($title)),
        ])));
        $rows = DB::table('occupation_labels as l')
            ->leftJoin('esco_occupations as e', 'e.id', '=', 'l.esco_occupation_id')
            ->whereIn('l.normalized', $forms)
            ->where(fn ($q) => $q->whereNull('l.esco_occupation_id')->orWhere('e.is_active', true))
            ->get(['l.isco_group_id', 'l.esco_occupation_id', 'l.kind', 'l.label']);
        if ($rows->isEmpty()) {
            return null;
        }

        foreach (self::TIERS as $kinds) {
            $tier = $rows->whereIn('kind', $kinds);
            if ($tier->isEmpty()) {
                continue;
            }
            $escoIds = $tier->pluck('esco_occupation_id')->filter()->unique()->values();
            $unitIds = $tier->pluck('isco_group_id')->unique()->values();

            // One ESCO job, and any group-level titles point at its own group.
            if ($escoIds->count() === 1) {
                $esco = EscoOccupation::query()->with('iscoGroup.enoc')->find($escoIds[0]);
                if ($esco && $unitIds->diff([$esco->isco_group_id])->isEmpty()) {
                    return $this->chosen($title, OccupationDisplay::block($esco->iscoGroup, $esco, $gender), $tier->first()->label);
                }
            }
            // One group, named by its group title ("محاسبون", "Accountants").
            if ($escoIds->isEmpty() && $unitIds->count() === 1) {
                $unit = IscoGroup::query()->with('enoc')->find($unitIds[0]);
                $detailed = EscoOccupation::query()->where('isco_group_id', $unit->id)->where('is_active', true);
                if (! $detailed->exists()) {
                    // ESCO does not detail this group: it is stored at group level.
                    return $this->chosen($title, OccupationDisplay::block($unit, null, $gender), $tier->first()->label);
                }
                // The group is certain, the detailed job is not: offer its jobs.
                $candidates = [];
                foreach ($detailed->with('iscoGroup.enoc')->orderBy('code')->limit(8)->get() as $e) {
                    $candidates[] = ['block' => OccupationDisplay::block($e->iscoGroup, $e, $gender), 'match' => $tier->first()->label, 'score' => 80];
                }

                return ['status' => 'ambiguous', 'title' => $title, 'choice' => null, 'candidates' => $candidates];
            }

            // Several occupations equally → a person decides.
            $candidates = [];
            foreach (EscoOccupation::query()->with('iscoGroup.enoc')->whereIn('id', $escoIds)->get() as $e) {
                $candidates[] = ['block' => OccupationDisplay::block($e->iscoGroup, $e, $gender), 'match' => $tier->firstWhere('esco_occupation_id', $e->id)?->label, 'score' => 100];
            }
            $groupOnly = $tier->whereNull('esco_occupation_id')->pluck('isco_group_id')->unique();
            foreach (IscoGroup::query()->with('enoc')->whereIn('id', $groupOnly)->get() as $u) {
                if (! EscoOccupation::query()->where('isco_group_id', $u->id)->where('is_active', true)->exists()) {
                    $candidates[] = ['block' => OccupationDisplay::block($u, null, $gender), 'match' => $tier->firstWhere('isco_group_id', $u->id)?->label, 'score' => 100];
                }
            }

            return ['status' => 'ambiguous', 'title' => $title, 'choice' => null, 'candidates' => array_slice($candidates, 0, 8)];
        }

        return null;
    }

    /** The occupation a title rule points at (null when it is no longer in the backbone). */
    private function ruleBlock(array $rule, ?string $gender): ?array
    {
        if ($rule['esco_id']) {
            $esco = EscoOccupation::query()->with('iscoGroup.enoc')->where('is_active', true)->find($rule['esco_id']);

            return $esco ? OccupationDisplay::block($esco->iscoGroup, $esco, $gender) : null;
        }
        if ($rule['unit']) {
            $unit = IscoGroup::query()->units()->with('enoc')->where('code', $rule['unit'])->first();

            return $unit ? OccupationDisplay::block($unit, null, $gender) : null;
        }

        return null;
    }

    private function among(?array $block, array $candidates): bool
    {
        foreach ($candidates as $c) {
            if (($c['block']['esco_id'] ?? null) === ($block['esco_id'] ?? null) && ($c['block']['unit_code'] ?? null) === ($block['unit_code'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function chosen(string $title, ?array $block, ?string $label): array
    {
        return ['status' => $block ? 'exact' : 'none', 'title' => $title, 'choice' => $block,
            'candidates' => $block ? [['block' => $block, 'match' => $label, 'score' => 100]] : []];
    }

    /** "Senior Accountant" → "Accountant", "محاسب أول" → "محاسب". */
    public static function withoutNoise(string $title): string
    {
        $noise = array_map([TextNormalizer::class, 'normalize'], CvDictionary::TITLE_NOISE);
        $words = array_filter(preg_split('/\s+/u', trim($title)), fn ($w) => ! in_array(TextNormalizer::normalize($w), $noise, true));

        return implode(' ', $words) ?: $title;
    }

    /** A rough 0–100 score: how many of the words are shared. */
    private function closeScore(string $title, string $match): int
    {
        $a = TextNormalizer::tokens(self::withoutNoise($title));
        $b = TextNormalizer::tokens($match);
        if (! $a || ! $b) {
            return 0;
        }
        $shared = count(array_intersect($a, $b));

        return (int) round(100 * (2 * $shared) / (count($a) + count($b)));
    }
}
