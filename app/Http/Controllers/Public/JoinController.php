<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\App\BeneficiaryController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\JoinRequest;
use App\Models\Company;
use App\Models\CvDocument;
use App\Models\JobSeeker;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Cv\CvIntake;
use App\Services\Pool\SeekerEmailCode;
use App\Services\Pool\TalentPool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — a job seeker registers (Step 10)
//  Location: app/Http/Controllers/Public/JoinController.php
//  Routes:  GET  /join       (seeker.join)       the journey (?door=cv | questions)
//           POST /join/cv    (seeker.join.cv)    read their CV first (JSON)
//           POST /join       (seeker.join.store) save: profile + sign-in
//
//  The same journey as the staff Guided Intake (Step 9): with a CV, it
//  is read by the CV Reading Engine and only what the CV did not answer
//  is asked; without, short questions. Then preferences, consent and
//  the sign-in (email + password). After saving, the job seeker is
//  signed in and asked for the 6-digit code sent to their email; the
//  profile shows in the Talent Pool only once the email is confirmed.
//
//  A CV uploaded here can only be used by the same browser session
//  that uploaded it (its id is kept in the session).
// ══════════════════════════════════════════════════════════════════

class JoinController extends Controller
{
    private const SESSION_CVS = 'pool.pending_cvs';

    public function index(Request $request, OccupationCatalog $catalog): Response
    {
        return Inertia::render('Public/Join', [
            'door'           => in_array($request->query('door'), ['cv', 'questions'], true) ? $request->query('door') : 'cv',
            'options'        => app(BeneficiaryController::class)->formOptions() + ['notice_periods' => JobSeeker::NOTICE_PERIODS],
            'backbone'       => $catalog->loaded(),
            'limits'         => ['max_kb' => (int) config('cv.max_kb'), 'extensions' => config('cv.extensions')],
        ]);
    }

    public function readCv(Request $request, CvIntake $intake): JsonResponse
    {
        $doc = self::readUpload($request, $intake);
        $pending = array_slice([...$request->session()->get(self::SESSION_CVS, []), $doc->uuid], -5);
        $request->session()->put(self::SESSION_CVS, $pending);

        return response()->json(self::readingFor($doc));
    }

    public function store(JoinRequest $request, TalentPool $pool, SeekerEmailCode $codes): RedirectResponse
    {
        $data = $request->validated();
        $cv = $data['cv'] ?? null;
        if ($cv && ! in_array($cv, $request->session()->get(self::SESSION_CVS, []), true)) {
            throw ValidationException::withMessages(['cv' => __('pool.cv_gone')]);
        }

        $seeker = $pool->register($data, [
            'email'         => $data['email'],
            'password'      => $data['password'],
            'language'      => app()->getLocale(),
            'visible'       => $data['visible'] ?? false,
            'notice_period' => $data['notice_period'] ?? null,
        ], $cv);
        $request->session()->forget(self::SESSION_CVS);

        Auth::guard('seeker')->login($seeker);
        $request->session()->regenerate();
        try {
            $codes->send($seeker, force: true);
        } catch (\Throwable $e) {
            report($e);    // the verify page offers "Send a new code"
        }

        return redirect()->route('seeker.verify');
    }

    // ── Shared with "Replace my CV" (MyProfileController) ────────────

    /** Check the upload and read it (Talent Pool workspace). */
    public static function readUpload(Request $request, CvIntake $intake): CvDocument
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

        return $intake->handlePublic($file, Company::poolId());
    }

    /** The reading, in the shape the journey uses (the same as the staff Guided Intake). */
    public static function readingFor(CvDocument $doc): array
    {
        $reading = $doc->reading ?? [];
        $form = $reading['form'] ?? [];
        $occupation = $reading['occupation'] ?? null;

        return [
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
        ];
    }
}
