<?php

namespace App\Services\Cv;

use App\Http\Requests\App\SaveBeneficiaryRequest;
use App\Models\Beneficiary;
use App\Models\CvBatch;
use App\Models\CvDocument;
use App\Models\User;
use App\Services\Beneficiaries\BeneficiaryRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvIntake (what happens to ONE uploaded CV)
//  Location: app/Services/Cv/CvIntake.php
//  Scope v2 §3 Bulk CV Upload · CV Reading Engine · Duplicate
//  Detection, §7 Secure CV File Storage
//
//  handle($file, $batch, $user) → the CvDocument, with its status:
//
//   1. The original file is encrypted and kept on the private disk
//      (CvStorage), whatever happens next — it stays attached.
//   2. The text is read (TextExtractor). A scan, a locked PDF or an
//      old .doc → "unreadable" (a person can still register it by hand
//      from the review screen).
//   3. The reading engine fills the fields (CvReader) and the
//      occupation is classified (OccupationClassifier).
//   4. The filled profile is checked with EXACTLY the same rules as
//      the registration form (SaveBeneficiaryRequest), so a CV can
//      never create a profile the form would refuse.
//   5. Duplicates are looked for in THIS workspace only: the same
//      file, or the same mobile / email on a profile or on another CV
//      that is waiting for review.
//   6. The result:
//        added      everything needed was found with certainty, the
//                   occupation matched exactly once, nothing is marked
//                   "Check" and there is no duplicate → the profile is
//                   created at once (history: "created", by the uploader)
//        duplicate  possibly the same person / file → review queue
//        review     anything else → review queue
//      config('cv.auto_add') = false sends every readable CV to review.
// ══════════════════════════════════════════════════════════════════

class CvIntake
{
    /** Without these, a profile cannot be saved (same as the form). */
    public const REQUIRED = ['name', 'gender', 'governorate'];

    public function __construct(
        private readonly TextExtractor $extractor,
        private readonly CvReader $reader,
        private readonly OccupationClassifier $classifier,
        private readonly LearnedRuleBook $rules,
        private readonly CvStorage $storage,
        private readonly BeneficiaryRecorder $recorder,
    ) {}

    /**
     * @param  bool  $autoAdd  false for the Guided Intake: a person is filling the profile with
     *                         the beneficiary right now, so it is never added automatically
     */
    public function handle(UploadedFile $file, CvBatch $batch, User $user, bool $autoAdd = true): CvDocument
    {
        $doc = $this->keepAndRead($file, $batch->company_id, $batch->id, $user->id, $user->name);
        $batch->increment('files_received');

        if ($autoAdd && $doc->status === CvDocument::REVIEW && $this->canAddAutomatically($doc)) {
            $this->addAutomatically($doc, $batch, $user);
        }

        return $doc;
    }

    /**
     * A CV a job seeker uploads on the public site (Step 10): kept and read
     * the same way, in the Talent Pool workspace. Never added
     * automatically: the job seeker checks and saves their own profile.
     * Until they do, it waits (status review / duplicate / unreadable)
     * and is removed after a day by `pool:prune` if they never finish.
     */
    public function handlePublic(UploadedFile $file, int $poolId): CvDocument
    {
        return $this->keepAndRead($file, $poolId, null, null, 'Public site · الموقع العام');
    }

    /** Steps 1–5 for a new file. */
    private function keepAndRead(UploadedFile $file, int $companyId, ?int $batchId, ?int $userId, string $userName): CvDocument
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $path = $file->getRealPath();
        $contents = (string) file_get_contents($path);

        $doc = new CvDocument;
        $doc->forceFill([
            'uuid'             => (string) Str::uuid(),
            'company_id'       => $companyId,
            'batch_id'         => $batchId,
            'uploaded_by'      => $userId,
            'uploaded_by_name' => mb_substr($userName, 0, 100),
            'original_name'    => mb_substr($this->cleanName($file->getClientOriginalName()), 0, 200),
            'extension'        => mb_substr($extension, 0, 5),
            'size'             => strlen($contents),
            'sha256'           => hash('sha256', $contents),
            'status'           => CvDocument::REVIEW,
        ]);

        // 1. Keep the original (encrypted), before anything else can fail.
        $doc->stored_path = $this->storage->put($companyId, $doc->uuid, $contents);

        try {
            $this->read($doc, $path, $extension);
        } catch (Throwable $e) {
            report($e);
            $doc->status = CvDocument::UNREADABLE;
            $doc->problem = 'broken';
        }

        $doc->save();

        return $doc;
    }

    /**
     * Read a waiting CV again, from its original file — after a person
     * taught a Learned Rule, or once the PDF reader is set up for a PDF
     * that came in before it was. It stays in the review queue (review,
     * duplicate or unreadable); nothing is added automatically while a
     * reviewer may be looking at it.
     */
    public function reread(CvDocument $doc): bool
    {
        if (! $doc->isOpen() || ! $doc->stored_path || ! ($contents = $this->storage->get($doc->stored_path))) {
            return false;
        }
        $path = tempnam(sys_get_temp_dir(), 'cv').'.'.$doc->extension;
        try {
            file_put_contents($path, $contents);
            $this->read($doc, $path, $doc->extension);
            $doc->save();
        } finally {
            @unlink($path);
        }

        return true;
    }

    /**
     * The other waiting CVs of this workspace whose text contains these
     * words, read again (at most $limit). Returns how many.
     */
    public function rereadContaining(int $companyId, string $phrase, int $exceptId, int $limit = 100): int
    {
        $needle = \App\Support\TextNormalizer::normalize($phrase);
        if ($needle === '') {
            return 0;
        }
        $n = 0;
        $docs = CvDocument::query()->inWorkspace($companyId)->whereIn('status', [CvDocument::REVIEW, CvDocument::DUPLICATE])
            ->whereKeyNot($exceptId)->whereNotNull('text')->orderByDesc('id')->limit(500)->get();
        foreach ($docs as $doc) {
            if ($n >= $limit) {
                break;
            }
            if (str_contains(' '.\App\Support\TextNormalizer::normalize($doc->text).' ', $needle) && $this->reread($doc)) {
                $n++;
            }
        }

        return $n;
    }

    /** Steps 2–5. Leaves the document as review, duplicate or unreadable. */
    private function read(CvDocument $doc, string $path, string $extension): void
    {
        $extracted = $this->extractor->extract($path, $extension);
        if ($extracted['problem']) {
            $doc->status = CvDocument::UNREADABLE;
            $doc->problem = $extracted['problem'];
            $doc->duplicates = $this->sameFile($doc) ?: null;

            return;
        }

        [$text] = CvReader::withoutBoldMarks($extracted['text']);
        // The workspace's Learned Rules (and Massar's) are used in every reading.
        $book = $this->rules->forReading($doc->company_id);
        $reading = $this->reader->withRules($book)->read($extracted['text'], $extracted['arabic_ligatures'], $extension === 'pdf');
        $occupation = $this->classifier->classify($reading['titles'], $reading['form']['gender'], $book['titles']);
        $reading['occupation'] = $occupation;
        // Step 10.5: each job's country (from its place), company and sector.
        $reading['form']['work_history'] = app(\App\Services\Employers\EmployerBook::class)
            ->enrich($reading['form']['work_history'] ?? [], $doc->company_id);
        foreach ($reading['form']['work_history'] as $job) {
            if (in_array('employer', $job['check'] ?? [], true) && ($reading['marks']['work_history'] ?? '') === 'found') {
                $reading['marks']['work_history'] = 'check';     // two companies with that name: a person picks
            }
        }
        $this->rules->used([...($reading['rules_used'] ?? []), $occupation['rule_id'] ?? null]);
        $reading['marks']['occupation'] = match ($occupation['status']) {
            'exact', 'rule' => 'found',
            'ambiguous', 'close' => 'check',
            default     => 'missing',
        };

        // 4. The same rules as the registration form.
        $errors = $this->formErrors($reading['form'], $occupation);
        foreach ($errors as $field => $message) {
            $key = in_array($field, ['name_ar', 'name_en'], true) ? 'name' : explode('.', $field)[0];
            if (($reading['marks'][$key] ?? 'missing') === 'found') {
                $reading['marks'][$key] = 'check';
            }
            $reading['notes'][$key] ??= 'form_rule';
        }
        $reading['attention'] = $this->attention($reading);

        $doc->text = $text;
        $doc->problem = null;       // e.g. a PDF that came in before the PDF reader was set up
        $doc->language = $reading['language'];
        $doc->phone = $reading['form']['phone'];
        $doc->email = $reading['form']['email'];
        $doc->reading = $reading;

        // 5. Duplicates, in this workspace only.
        $duplicates = [...$this->sameFile($doc), ...$this->samePerson($doc->company_id, $doc->phone, $doc->email, $doc->id)];
        $doc->duplicates = $duplicates ?: null;
        $doc->status = $duplicates ? CvDocument::DUPLICATE : CvDocument::REVIEW;
    }

    /** The fields a reviewer has to look at: required but missing, or marked "check". */
    private function attention(array $reading): array
    {
        $marks = $reading['marks'];
        $out = [];
        foreach (self::REQUIRED as $field) {
            if (($marks[$field] ?? 'missing') !== 'found') {
                $out[] = $field;
            }
        }
        if (($marks['phone'] ?? '') !== 'found' && ($marks['email'] ?? '') !== 'found') {
            $out[] = 'contact';
        }
        foreach ($marks as $field => $mark) {
            if ($mark === 'check' && ! in_array($field, $out, true)) {
                $out[] = $field;
            }
        }
        if (($marks['occupation'] ?? 'missing') !== 'found' && ! in_array('occupation', $out, true)) {
            $out[] = 'occupation';
        }

        return $out;
    }

    private function canAddAutomatically(CvDocument $doc): bool
    {
        return (bool) config('cv.auto_add', true)
            && ($doc->reading['attention'] ?? ['?']) === []
            && in_array($doc->reading['occupation']['status'] ?? '', ['exact', 'rule'], true);
    }

    private function addAutomatically(CvDocument $doc, CvBatch $batch, User $user): void
    {
        DB::transaction(function () use ($doc, $user) {
            $data = self::profileData($doc->reading['form'], $doc->reading['occupation']['choice'] ?? null);
            // Checked again inside the transaction: someone may have registered the same person meanwhile.
            if ($this->recorder->duplicates($doc->company_id, $data['phone'], $data['email'])->isNotEmpty()) {
                return;
            }
            $method = ($doc->reading['occupation']['status'] ?? '') === 'rule' ? 'cv_rule' : 'cv_exact';
            $b = $this->recorder->create($user->company()->firstOrFail(), $data, $user, $method, 'cv_upload');
            $doc->forceFill(['status' => CvDocument::ADDED, 'beneficiary_id' => $b->id])->save();
        });
    }

    /** The reading, in the shape BeneficiaryRecorder and the form use. */
    public static function profileData(array $form, ?array $choice): array
    {
        // The reader's per-job "check" notes are for the review screen only.
        if (isset($form['work_history']) && is_array($form['work_history'])) {
            $form['work_history'] = array_map(fn ($j) => is_array($j) ? array_diff_key($j, ['check' => 1]) : $j, $form['work_history']);
        }

        return array_merge(array_intersect_key($form, array_flip(BeneficiaryRecorder::FIELDS)), [
            'expected_salary'    => $form['expected_salary'] ?? null,
            'job_type'           => $form['job_type'] ?? null,
            'esco_occupation_id' => $choice['esco_id'] ?? null,
            'occupation_unit'    => ($choice && ($choice['group_only'] ?? false)) ? $choice['unit_code'] : null,
        ]);
    }

    /** @return array<string, string> field => message, using the form's own rules */
    private function formErrors(array $form, array $occupation): array
    {
        $request = new SaveBeneficiaryRequest;
        $v = Validator::make(self::profileData($form, $occupation['choice'] ?? null), $request->rules(), $request->messages());

        return array_map(fn ($m) => $m[0], $v->errors()->toArray());
    }

    // ── Duplicates (this workspace only) ─────────────────────────────

    private function sameFile(CvDocument $doc): array
    {
        return CvDocument::query()->withoutGlobalScopes()->inWorkspace($doc->company_id)
            ->where('sha256', $doc->sha256)->where('status', '!=', CvDocument::REJECTED)
            ->when($doc->exists, fn ($q) => $q->whereKeyNot($doc->id))     // read again: not itself
            ->orderBy('id')->limit(3)->get()
            ->map(fn (CvDocument $d) => ['type' => 'file', 'uuid' => $d->uuid, 'file' => $d->original_name, 'status' => $d->status,
                'number' => $d->beneficiary_id ? Beneficiary::query()->withoutGlobalScopes()->whereKey($d->beneficiary_id)->value('number') : null])
            ->all();
    }

    /** Profiles and waiting CVs with the same mobile or email. */
    public function samePerson(int $companyId, ?string $phone, ?string $email, ?int $exceptDocId = null): array
    {
        $out = $this->recorder->duplicates($companyId, $phone, $email)
            ->map(fn (Beneficiary $b) => ['type' => 'profile', 'number' => $b->number, 'name' => $b->displayName()])->all();

        if ($phone || $email) {
            $waiting = CvDocument::query()->withoutGlobalScopes()->inWorkspace($companyId)
                ->whereIn('status', [CvDocument::REVIEW, CvDocument::DUPLICATE])
                ->when($exceptDocId, fn ($q) => $q->whereKeyNot($exceptDocId))
                ->where(fn ($q) => $q->when($phone, fn ($q) => $q->orWhere('phone', $phone))->when($email, fn ($q) => $q->orWhere('email', $email)))
                ->orderBy('id')->limit(3)->get();
            foreach ($waiting as $d) {
                $out[] = ['type' => 'cv', 'uuid' => $d->uuid, 'file' => $d->original_name, 'name' => $d->foundName()];
            }
        }

        return $out;
    }

    private function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $name) ?? 'cv';

        return trim($name) !== '' ? trim($name) : 'cv';
    }
}
