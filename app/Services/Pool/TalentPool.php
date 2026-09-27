<?php

namespace App\Services\Pool;

use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\Company;
use App\Models\CvDocument;
use App\Models\JobSeeker;
use App\Models\PoolAccessLog;
use App\Models\PoolAddition;
use App\Models\User;
use App\Services\Beneficiaries\BeneficiaryRecorder;
use App\Services\Cv\CvProfileUpdate;
use App\Services\Cv\CvStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// ══════════════════════════════════════════════════════════════════
//  Massar — TalentPool (Step 10 · Public Self-Registration + Talent Pool)
//  Location: app/Services/Pool/TalentPool.php
//
//  Everything that changes a job seeker's account or profile, and a
//  partner adding a job seeker to its workspace. Each is one database
//  transaction.
//
//  JOB SEEKERS (public site)
//    register()      the profile (checked with the registration form's
//                    own rules), the sign-in, the consent, and the CV
//                    they uploaded, if any, attached to the profile
//    update()        "Edit my profile"
//    replaceCv()     a newer CV: only the changes they ticked are made
//                    (CvProfileUpdate, the same as "Update from this CV"
//                    for staff); the new CV replaces the old one
//    setVisible()    join or leave the Talent Pool
//    delete()        the account, the profile and the CV are deleted.
//                    Copies partners already made stay with those
//                    partners (they are the partners' own records).
//
//  PARTNERS
//    add()           the partner gets its OWN copy of the profile and of
//                    the CV, in its own workspace — from then on it is
//                    an ordinary profile there (history: "added from the
//                    Public Talent Pool"). The same mobile or email
//                    already in the workspace asks the usual duplicate
//                    question. Adding twice opens the copy made before.
// ══════════════════════════════════════════════════════════════════

class TalentPool
{
    public function __construct(
        private readonly BeneficiaryRecorder $recorder,
        private readonly CvStorage $storage,
        private readonly CvProfileUpdate $cvUpdate,
    ) {}

    // ── Job seekers ─────────────────────────────────────────────────

    /**
     * @param  array  $profile  validated profile fields (SaveBeneficiaryRequest shape)
     * @param  array  $account  email, password, language, visible, notice_period
     */
    public function register(array $profile, array $account, ?string $cvUuid): JobSeeker
    {
        return DB::transaction(function () use ($profile, $account, $cvUuid) {
            $pool = Company::pool();
            $this->stopIfTaken($account['email'], $profile['phone'] ?? null);

            $doc = $cvUuid ? $this->pendingCv($pool->id, $cvUuid) : null;
            $b = $this->recorder->create($pool, $profile, JobSeeker::actor(), $this->method($doc, $profile), 'self');

            $seeker = new JobSeeker;
            $seeker->forceFill([
                'uuid'           => (string) Str::uuid(),
                'email'          => $account['email'],
                'phone'          => $b->phone,
                'password'       => $account['password'],
                'language'       => $account['language'] === 'en' ? 'en' : 'ar',
                'beneficiary_id' => $b->id,
                'visible'        => (bool) $account['visible'],
                'consented_at'   => now(),
                'notice_period'  => $account['notice_period'] ?? null,
            ])->save();

            if ($doc) {
                $doc->forceFill(['status' => CvDocument::APPROVED, 'beneficiary_id' => $b->id, 'reviewed_at' => now(),
                    'reviewed_by_name' => 'Job seeker · الباحث عن عمل'])->save();
            }

            return $seeker;
        });
    }

    public function update(JobSeeker $seeker, array $profile, ?string $noticePeriod): void
    {
        DB::transaction(function () use ($seeker, $profile, $noticePeriod) {
            $seeker = JobSeeker::query()->lockForUpdate()->findOrFail($seeker->id);
            $b = $this->profileOf($seeker);
            // The email is the sign-in: it is not changed from the profile.
            $profile['email'] = $seeker->email;
            $this->stopIfTaken(null, $profile['phone'] ?? null, $seeker->id);

            $this->recorder->update($b, $profile, JobSeeker::actor(), 'self');
            $seeker->forceFill(['phone' => $b->phone, 'notice_period' => $noticePeriod])->save();
        });
    }

    /** What a newer CV would change (the rows the job seeker ticks). The email is the sign-in: never offered. */
    public function cvChanges(JobSeeker $seeker, CvDocument $doc): array
    {
        return array_values(array_filter(
            $this->cvUpdate->compare($this->profileOf($seeker), $doc),
            fn ($row) => ($row['field'] ?? null) !== 'email',
        ));
    }

    /** @return int how many changes were made */
    public function replaceCv(JobSeeker $seeker, string $uuid, array $ticked): int
    {
        return DB::transaction(function () use ($seeker, $uuid, $ticked) {
            $seeker = JobSeeker::query()->lockForUpdate()->findOrFail($seeker->id);
            $b = $this->profileOf($seeker);
            $doc = $this->pendingCv($b->company_id, $uuid);
            $ticked = array_values(array_filter($ticked, fn ($id) => $id !== 'f:email'));

            $made = $this->cvUpdate->apply($b, $doc, $ticked, JobSeeker::actor());
            $b->refresh();
            $this->stopIfTaken(null, $b->phone, $seeker->id);
            $seeker->forceFill(['phone' => $b->phone])->save();

            // The new CV replaces the old one: only one CV is kept.
            foreach ($this->cvsOf($b) as $old) {
                $this->storage->delete($old->stored_path);
                $old->delete();
            }
            $doc->forceFill(['status' => CvDocument::ATTACHED, 'beneficiary_id' => $b->id, 'reviewed_at' => now(),
                'reviewed_by_name' => 'Job seeker · الباحث عن عمل'])->save();

            return $made;
        });
    }

    public function setVisible(JobSeeker $seeker, bool $visible): void
    {
        $seeker->forceFill(['visible' => $visible, 'consented_at' => $visible ? now() : $seeker->consented_at])->save();
    }

    public function delete(JobSeeker $seeker): void
    {
        DB::transaction(function () use ($seeker) {
            $b = $seeker->profile;
            if ($b) {
                $docs = CvDocument::query()->withoutGlobalScopes()->where('company_id', $b->company_id)->where('beneficiary_id', $b->id)->get();
                foreach ($docs as $d) {
                    $this->storage->delete($d->stored_path);
                    $d->delete();
                }
            }
            $seeker->delete();        // pool_additions keep the partners' copies; access logs go with it
            $b?->delete();            // its history goes with it (cascade)
        });
    }

    /** The job seeker's CV (the one on the profile), or null. */
    public function cvOf(JobSeeker $seeker): ?CvDocument
    {
        $b = $seeker->profile;

        return $b ? $this->cvsOf($b)->first() : null;
    }

    // ── Partners ────────────────────────────────────────────────────

    /** @return array{0: Beneficiary, 1: bool} the partner's copy, and whether it was made now */
    public function add(JobSeeker $seeker, User $by, bool $confirmDuplicate = false): array
    {
        $companyId = (int) $by->company_id;

        return DB::transaction(function () use ($seeker, $by, $companyId, $confirmDuplicate) {
            $seeker = JobSeeker::query()->inPool()->lockForUpdate()->findOrFail($seeker->id);
            $pool = $this->profileOf($seeker);

            $addition = PoolAddition::query()->where('job_seeker_id', $seeker->id)->where('company_id', $companyId)->lockForUpdate()->first();
            if ($addition && $addition->beneficiary_id
                && ($copy = Beneficiary::query()->withoutGlobalScopes()->inWorkspace($companyId)->find($addition->beneficiary_id))) {
                return [$copy, false];
            }

            $data = $this->cvUpdate->current($pool);
            if (! $confirmDuplicate) {
                $same = $this->recorder->duplicates($companyId, $data['phone'] ?? null, $data['email'] ?? null);
                if ($same->isNotEmpty()) {
                    $names = $same->map(fn (Beneficiary $d) => '#'.$d->number.' '.$d->displayName(app()->getLocale()))->implode('، ');
                    throw ValidationException::withMessages(['duplicate' => __('beneficiaries.v.duplicate', ['names' => $names])]);
                }
            }

            $method = in_array($pool->occupation_method, BeneficiaryRecorder::METHODS, true) ? $pool->occupation_method : 'self';
            $company = Company::query()->findOrFail($companyId);
            $copy = $this->recorder->create($company, $data, $by, $method, 'pool');
            BeneficiaryChange::query()->withoutGlobalScopes()->where('beneficiary_id', $copy->id)
                ->update(['changes' => json_encode(['_from_pool' => true])]);

            if ($cv = $this->cvsOf($pool)->first()) {
                $this->copyCv($cv, $copy, $by);
            }

            PoolAddition::query()->updateOrCreate(
                ['job_seeker_id' => $seeker->id, 'company_id' => $companyId],
                ['beneficiary_id' => $copy->id, 'added_by' => $by->id, 'added_by_name' => mb_substr($by->name, 0, 100), 'created_at' => now()],
            );
            PoolAccessLog::write($seeker, $by, 'add');

            return [$copy, true];
        });
    }

    /** The copy a partner made of this job seeker, if it still exists. */
    public function copyIn(JobSeeker $seeker, int $companyId): ?Beneficiary
    {
        $id = PoolAddition::query()->where('job_seeker_id', $seeker->id)->where('company_id', $companyId)->value('beneficiary_id');

        return $id ? Beneficiary::query()->withoutGlobalScopes()->inWorkspace($companyId)->find($id) : null;
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function profileOf(JobSeeker $seeker): Beneficiary
    {
        return Beneficiary::query()->withoutGlobalScopes()->inWorkspace(Company::poolId())->findOrFail($seeker->beneficiary_id);
    }

    /** A CV uploaded on the public site and not saved on a profile yet. */
    public function pendingCv(int $poolId, string $uuid): CvDocument
    {
        $doc = CvDocument::query()->withoutGlobalScopes()->inWorkspace($poolId)->where('uuid', $uuid)
            ->whereNull('beneficiary_id')->lockForUpdate()->first();
        if (! $doc || ! $doc->isOpen()) {
            throw ValidationException::withMessages(['cv' => __('pool.cv_gone')]);
        }

        return $doc;
    }

    private function cvsOf(Beneficiary $b)
    {
        return CvDocument::query()->withoutGlobalScopes()->where('company_id', $b->company_id)->where('beneficiary_id', $b->id)
            ->whereIn('status', CvDocument::ON_PROFILE)->orderByDesc('id')->get();
    }

    /** How the occupation was chosen: the CV's own when the job seeker kept it. */
    private function method(?CvDocument $doc, array $data): string
    {
        if (! $doc) {
            return 'self';
        }
        $engine = $doc->reading['occupation'] ?? [];
        $same = in_array($engine['status'] ?? '', ['exact', 'rule'], true)
            && (int) ($engine['choice']['esco_id'] ?? 0) === (int) ($data['esco_occupation_id'] ?? 0)
            && (($engine['choice']['group_only'] ?? false) ? $engine['choice']['unit_code'] : null) === ($data['occupation_unit'] ?? null);

        return $same ? (($engine['status'] ?? '') === 'rule' ? 'cv_rule' : 'cv_exact') : 'self';
    }

    /** One account per email and per mobile. */
    private function stopIfTaken(?string $email, ?string $phone, ?int $exceptId = null): void
    {
        $q = fn () => JobSeeker::query()->when($exceptId, fn ($w) => $w->whereKeyNot($exceptId));
        if ($email && $q()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('pool.email_taken')]);
        }
        if ($phone && $q()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => __('pool.phone_taken')]);
        }
    }

    private function copyCv(CvDocument $cv, Beneficiary $copy, User $by): void
    {
        $contents = $cv->stored_path ? $this->storage->get($cv->stored_path) : null;
        if ($contents === null) {
            return;
        }
        $doc = new CvDocument;
        $doc->forceFill([
            'uuid'             => (string) Str::uuid(),
            'company_id'       => $copy->company_id,
            'uploaded_by'      => $by->id,
            'uploaded_by_name' => mb_substr($by->name, 0, 100),
            'original_name'    => $cv->original_name,
            'extension'        => $cv->extension,
            'size'             => $cv->size,
            'sha256'           => $cv->sha256,
            'status'           => CvDocument::ATTACHED,
            'language'         => $cv->language,
            'text'             => $cv->text,
            'reading'          => $cv->reading,
            'phone'            => $cv->phone,
            'email'            => $cv->email,
            'beneficiary_id'   => $copy->id,
            'reviewed_by'      => $by->id,
            'reviewed_by_name' => mb_substr($by->name, 0, 100),
            'reviewed_at'      => now(),
        ]);
        $doc->stored_path = $this->storage->put($copy->company_id, $doc->uuid, $contents);
        $doc->save();
    }
}
