<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\IntakeRequest;
use App\Models\Beneficiary;
use App\Models\CvBatch;
use App\Models\CvDocument;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Beneficiaries\BeneficiaryRecorder;
use App\Services\Cv\CvIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — Guided Intake (Scope v2 §3 · Step 9)
//  Location: app/Http/Controllers/App/IntakeController.php
//  Routes:  GET  /app/intake      (intake.index)  the journey
//           POST /app/intake/cv   (intake.cv)     read the person's CV first
//           POST /app/intake      (intake.store)  save the profile
//
//  Two doors into the same profile:
//    · WITH a CV — the CV is read by the CV Reading Engine (no AI), the
//      journey is filled from it and asks ONLY what the CV did not
//      answer (or answered with doubt). The CV is kept (encrypted) and
//      attached to the new profile. It is never added automatically:
//      a person is filling the profile right now. If the journey is
//      left unfinished, the CV simply waits in the Review Queue.
//    · WITHOUT a CV — short, simple questions, a few at a time.
//  Both end with the same checks as the registration form
//  (SaveBeneficiaryRequest) and the same duplicate question.
// ══════════════════════════════════════════════════════════════════

class IntakeController extends Controller
{
    public function __construct(
        private readonly BeneficiaryRecorder $recorder,
        private readonly OccupationCatalog $catalog,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('App/Intake/Journey', [
            'options'    => app(BeneficiaryController::class)->formOptions(),
            'backbone'   => $this->catalog->loaded(),
            'can_upload' => $request->user()->can('cv.upload'),
            'limits'     => ['max_kb' => (int) config('cv.max_kb'), 'extensions' => config('cv.extensions')],
        ]);
    }

    /** The person's CV, read before the questions. */
    public function readCv(Request $request, CvIntake $intake): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.config('cv.max_kb')]], [
            'file.max'      => __('cv.too_big', ['mb' => round(config('cv.max_kb') / 1024)]),
            'file.uploaded' => __('cv.upload_failed'),
            'file.required' => __('cv.upload_failed'),
        ]);
        $file = $request->file('file');
        if (! in_array(strtolower($file->getClientOriginalExtension()), config('cv.extensions'), true)) {
            throw ValidationException::withMessages(['file' => __('cv.wrong_type')]);
        }
        $user = $request->user();

        $batch = new CvBatch;
        $batch->forceFill([
            'company_id' => $user->company_id, 'client_id' => 'intake-'.Str::uuid(), 'user_id' => $user->id,
            'user_name' => mb_substr($user->name, 0, 100), 'files_expected' => 1,
        ])->save();
        $doc = $intake->handle($file, $batch, $user, autoAdd: false);

        $reading = $doc->reading ?? [];
        $form = $reading['form'] ?? [];
        $occupation = $reading['occupation'] ?? null;

        return response()->json([
            'cv' => [
                'uuid'    => $doc->uuid,
                'file'    => $doc->original_name,
                'read'    => $doc->status !== CvDocument::UNREADABLE,
                'problem' => $doc->problem,
            ],
            'initial' => $form ? array_merge($form, [
                'occupation'   => $occupation['choice'] ?? null,
                'education'    => $form['education'] ?? [],
                'work_history' => $form['work_history'] ?? [],
                'skills'       => $form['skills'] ?? [],
                'languages'    => $form['languages'] ?? [],
            ]) : null,
            'marks' => $reading['marks'] ?? [],
            'notes' => $reading['notes'] ?? [],
            'occupation' => $occupation ? [
                'status'     => $occupation['status'],
                'title'      => $occupation['title'],
                'candidates' => array_values(array_filter($occupation['candidates'] ?? [], fn ($c) => ($c['score'] ?? 0) > 0)),
            ] : null,
        ]);
    }

    public function store(IntakeRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! ($data['confirm_duplicate'] ?? false)) {
            $same = $this->recorder->duplicates((int) $user->company_id, $data['phone'] ?? null, $data['email'] ?? null);
            if ($same->isNotEmpty()) {
                $names = $same->map(fn (Beneficiary $b) => '#'.$b->number.' '.$b->displayName(app()->getLocale()))->implode('، ');
                throw ValidationException::withMessages(['duplicate' => __('beneficiaries.v.duplicate', ['names' => $names])]);
            }
        }

        $b = DB::transaction(function () use ($data, $user) {
            $doc = null;
            if (! empty($data['cv'])) {
                $doc = CvDocument::query()->inWorkspace((int) $user->company_id)->where('uuid', $data['cv'])->lockForUpdate()->first();
                if (! $doc || ! $doc->isOpen()) {
                    throw ValidationException::withMessages(['cv' => __('intake.cv_gone')]);
                }
            }

            // How the occupation was chosen: kept as the CV's own when the person did not change it.
            $method = 'manual';
            if ($doc) {
                $engine = $doc->reading['occupation'] ?? [];
                $same = in_array($engine['status'] ?? '', ['exact', 'rule'], true)
                    && (int) ($engine['choice']['esco_id'] ?? 0) === (int) ($data['esco_occupation_id'] ?? 0)
                    && (($engine['choice']['group_only'] ?? false) ? $engine['choice']['unit_code'] : null) === ($data['occupation_unit'] ?? null);
                $method = $same ? (($engine['status'] ?? '') === 'rule' ? 'cv_rule' : 'cv_exact') : 'cv_review';
            }

            $b = $this->recorder->create($user->company()->firstOrFail(), $data, $user, $method, 'intake');

            if ($doc) {
                $doc->forceFill([
                    'status' => CvDocument::APPROVED, 'beneficiary_id' => $b->id,
                    'reviewed_by' => $user->id, 'reviewed_by_name' => mb_substr($user->name, 0, 100), 'reviewed_at' => now(),
                ])->save();
            }

            return $b;
        });

        return redirect()->route('app.beneficiaries.show', $b->number)
            ->with('success', __('intake.saved', ['number' => $b->number]));
    }
}
