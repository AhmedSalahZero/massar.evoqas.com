<?php

namespace App\Services\Cv;

use App\Http\Requests\App\SaveBeneficiaryRequest;
use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Models\Beneficiary;
use App\Models\CvDocument;
use App\Models\User;
use App\Services\Beneficiaries\BeneficiaryRecorder;
use App\Services\Beneficiaries\OccupationDisplay;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvProfileUpdate ("Update from this CV", Scope v2 §3)
//  Location: app/Services/Cv/CvProfileUpdate.php
//
//  A newer CV of someone already registered. compare() lists what the
//  CV says that the profile does not:
//
//    details       a field the CV fills (profile empty)      → ticked
//                  a field the CV says differently           → NOT ticked
//                  a mobile / email another profile uses     → cannot be ticked
//    occupation    the CV's occupation differs               → NOT ticked
//    jobs          a job the profile does not have           → ticked
//                  duties for a job whose duties are empty   → ticked
//    education     a qualification the profile does not have → ticked
//    skills        each new skill                            → ticked
//    languages     a new language                            → ticked
//                  a different level                         → NOT ticked
//
//  apply() makes only the ticked changes — nothing changes without a
//  person agreeing — checked with the registration form's own rules,
//  and records them in the profile history as "updated from CV …".
// ══════════════════════════════════════════════════════════════════

class CvProfileUpdate
{
    private const DETAILS = ['name_ar', 'name_en', 'gender', 'date_of_birth', 'military_status', 'governorate', 'city', 'phone', 'email', 'education_level'];

    public function __construct(private readonly BeneficiaryRecorder $recorder) {}

    /** @return list<array> the changes, in the order they are shown */
    public function compare(Beneficiary $b, CvDocument $doc): array
    {
        $cv = $doc->reading['form'] ?? [];
        $now = $this->current($b);
        $rows = [];

        foreach (self::DETAILS as $field) {
            $new = $cv[$field] ?? null;
            $old = $now[$field] ?? null;
            if ($new === null || $new === '' || $this->same($old, $new)) {
                continue;
            }
            $blocked = null;
            if (in_array($field, ['phone', 'email'], true)
                && $this->recorder->duplicates($b->company_id, $field === 'phone' ? $new : null, $field === 'email' ? $new : null, $b->id)->isNotEmpty()) {
                $blocked = 'used_by_other';
            }
            $fill = $old === null || $old === '';
            $rows[] = ['id' => 'f:'.$field, 'group' => 'details', 'field' => $field, 'old' => $old, 'new' => $new,
                'kind' => $fill ? 'fill' : 'replace', 'ticked' => $fill && ! $blocked, 'blocked' => $blocked];
        }

        $choice = $doc->reading['occupation']['choice'] ?? null;
        if ($choice && in_array($doc->reading['occupation']['status'] ?? '', ['exact', 'rule'], true)
            && ! ((int) ($choice['esco_id'] ?? 0) === (int) $b->esco_occupation_id && ($choice['unit_code'] ?? null) === $b->isco_code)) {
            $rows[] = ['id' => 'occupation', 'group' => 'occupation', 'old' => $this->occupationBlock($b), 'new' => $choice,
                'kind' => $b->isco_group_id ? 'replace' : 'fill', 'ticked' => ! $b->isco_group_id, 'blocked' => null];
        }

        foreach ($cv['work_history'] ?? [] as $i => $job) {
            $job = array_diff_key($job, ['check' => 1]);
            $k = $this->matchJob($now['work_history'], $job);
            if ($k === null) {
                $rows[] = ['id' => 'job:'.$i, 'group' => 'jobs', 'kind' => 'add', 'new' => $job, 'ticked' => true, 'blocked' => null];
            } elseif (! empty($job['responsibilities']) && empty($now['work_history'][$k]['responsibilities'])) {
                $rows[] = ['id' => 'duties:'.$i.':'.$k, 'group' => 'jobs', 'kind' => 'duties', 'old' => $now['work_history'][$k], 'new' => $job, 'ticked' => true, 'blocked' => null];
            }
        }

        foreach ($cv['education'] ?? [] as $i => $e) {
            if (! $this->hasEducation($now['education'], $e)) {
                $rows[] = ['id' => 'edu:'.$i, 'group' => 'education', 'kind' => 'add', 'new' => $e, 'ticked' => true, 'blocked' => null];
            }
        }

        $haveSkills = array_map(fn ($s) => TextNormalizer::normalize($s), $now['skills']);
        foreach ($cv['skills'] ?? [] as $i => $skill) {
            if (! in_array(TextNormalizer::normalize($skill), $haveSkills, true)) {
                $rows[] = ['id' => 'skill:'.$i, 'group' => 'skills', 'kind' => 'add', 'new' => $skill, 'ticked' => true, 'blocked' => null];
            }
        }

        $levels = collect($now['languages'])->pluck('level', 'code');
        foreach ($cv['languages'] ?? [] as $lang) {
            $code = $lang['code'] ?? null;
            if (! $code) {
                continue;
            }
            if (! $levels->has($code)) {
                $rows[] = ['id' => 'lang:'.$code, 'group' => 'languages', 'kind' => 'add', 'new' => $lang, 'ticked' => true, 'blocked' => null];
            } elseif (($lang['level'] ?? null) && $lang['level'] !== $levels[$code]) {
                $rows[] = ['id' => 'lang:'.$code, 'group' => 'languages', 'kind' => 'replace', 'old' => ['code' => $code, 'level' => $levels[$code]], 'new' => $lang, 'ticked' => false, 'blocked' => null];
            }
        }

        return $rows;
    }

    /**
     * Make the ticked changes. Returns how many were made.
     *
     * @param  list<string>  $ticked  row ids from compare()
     */
    public function apply(Beneficiary $b, CvDocument $doc, array $ticked, User $by): int
    {
        $rows = collect($this->compare($b, $doc))->keyBy('id');
        $chosen = $rows->only($ticked)->reject(fn ($r) => $r['blocked'] !== null);
        $data = $this->current($b);

        $method = 'manual';
        foreach ($chosen as $row) {
            switch ($row['group']) {
                case 'details':
                    $data[$row['field']] = $row['new'];
                    break;
                case 'occupation':
                    $data['esco_occupation_id'] = $row['new']['esco_id'] ?? null;
                    $data['occupation_unit'] = ($row['new']['group_only'] ?? false) ? $row['new']['unit_code'] : null;
                    $method = 'cv_review';
                    break;
                case 'jobs':
                    if ($row['kind'] === 'add') {
                        $data['work_history'][] = array_diff_key($row['new'], ['check' => 1]);
                    } else {
                        $k = (int) explode(':', $row['id'])[2];
                        $data['work_history'][$k]['responsibilities'] = $row['new']['responsibilities'];
                    }
                    break;
                case 'education':
                    $data['education'][] = $row['new'];
                    break;
                case 'skills':
                    $data['skills'][] = $row['new'];
                    break;
                case 'languages':
                    $data['languages'] = array_values(array_filter($data['languages'], fn ($l) => ($l['code'] ?? null) !== $row['new']['code']));
                    $data['languages'][] = ['code' => $row['new']['code'], 'level' => $row['new']['level'] ?? null];
                    break;
            }
        }
        if ($data['gender'] !== 'male') {
            $data['military_status'] = null;
        }
        $data['work_history'] = array_map(fn ($j) => $j + ['responsibilities' => []], $data['work_history']);

        // The same rules as the registration form: a combination the form would refuse is refused here.
        $request = new SaveBeneficiaryRequest;
        $v = Validator::make($data, $request->rules(), $request->messages());
        if ($v->fails()) {
            throw ValidationException::withMessages(['update' => __('cv.update_invalid', ['errors' => implode(' ', array_map(fn ($m) => $m[0], $v->errors()->toArray()))])]);
        }
        if ($chosen->isNotEmpty()) {
            $this->recorder->update($b, $data, $by, $method, $doc->original_name);
        }

        return $chosen->count();
    }

    /** The profile as the form saves it. */
    public function current(Beneficiary $b): array
    {
        $out = [];
        foreach (BeneficiaryRecorder::FIELDS as $field) {
            $v = $b->{$field};
            $out[$field] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
        }
        foreach (['education', 'work_history', 'skills', 'languages'] as $list) {
            $out[$list] = is_array($out[$list]) ? array_values($out[$list]) : [];
        }
        $out['esco_occupation_id'] = $b->esco_occupation_id;
        $out['occupation_unit'] = $b->esco_occupation_id ? null : $b->isco_code;

        return $out;
    }

    public function occupationBlock(Beneficiary $b): ?array
    {
        if ($b->esco_occupation_id && ($e = EscoOccupation::query()->with('iscoGroup.enoc')->find($b->esco_occupation_id))) {
            return OccupationDisplay::block($e->iscoGroup, $e, $b->gender);
        }
        if ($b->isco_code && ($u = IscoGroup::query()->units()->with('enoc')->where('code', $b->isco_code)->first())) {
            return OccupationDisplay::block($u, null, $b->gender);
        }

        return null;
    }

    private function same($a, $b): bool
    {
        return TextNormalizer::normalize((string) $a) === TextNormalizer::normalize((string) $b);
    }

    /** The profile job that is this CV job: the same start month, and the same title or employer. */
    private function matchJob(array $jobs, array $job): ?int
    {
        foreach ($jobs as $k => $j) {
            $sameStart = ($j['from'] ?? null) === ($job['from'] ?? null);
            $sameTitle = $this->same($j['title'] ?? '', $job['title'] ?? '');
            $sameEmployer = ($j['employer'] ?? null) && $this->same($j['employer'], $job['employer'] ?? '');
            if (($sameStart && ($sameTitle || $sameEmployer)) || ($sameTitle && $sameEmployer)) {
                return $k;
            }
        }

        return null;
    }

    private function hasEducation(array $list, array $e): bool
    {
        foreach ($list as $x) {
            if ($this->same($x['qualification'] ?? '', $e['qualification'] ?? '')
                || (($x['institution'] ?? null) && $this->same($x['institution'], $e['institution'] ?? '') && ($x['year'] ?? null) == ($e['year'] ?? null))) {
                return true;
            }
        }

        return false;
    }
}
