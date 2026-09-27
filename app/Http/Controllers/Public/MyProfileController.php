<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\App\BeneficiaryController;
use App\Http\Controllers\App\CvFileController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SeekerProfileRequest;
use App\Models\Company;
use App\Models\JobSeeker;
use App\Models\PoolAddition;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Cv\CvIntake;
use App\Services\Cv\CvProfileUpdate;
use App\Services\Cv\CvStorage;
use App\Services\Pool\TalentPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as FileResponse;

// ══════════════════════════════════════════════════════════════════
//  Massar — "My profile" (Step 10)
//  Location: app/Http/Controllers/Public/MyProfileController.php
//  Routes (signed-in job seekers, email confirmed):
//    GET    /me                 (seeker.profile)          the profile, the CV, who added them, privacy
//    GET    /me/edit            (seeker.profile.edit)     the same questions as registering, filled in
//    PATCH  /me                 (seeker.profile.update)
//    PATCH  /me/visibility      (seeker.profile.visibility)  join / leave the Talent Pool
//    GET    /me/cv              (seeker.cv.download)      their own CV file
//    POST   /me/cv              (seeker.cv.upload)        a newer CV → the changes page
//    GET    /me/cv/{uuid}       (seeker.cv.changes)       what the newer CV would change (tick boxes)
//    POST   /me/cv/{uuid}       (seeker.cv.apply)         make the ticked changes; the new CV replaces the old
//    DELETE /me                 (seeker.profile.destroy)  delete everything (password needed)
// ══════════════════════════════════════════════════════════════════

class MyProfileController extends Controller
{
    private const SESSION_CV = 'pool.replacing_cv';

    public function __construct(private readonly TalentPool $pool) {}

    public function show(Request $request, CvProfileUpdate $update): Response
    {
        $seeker = $this->seeker($request);
        $b = $this->pool->profileOf($seeker);
        $cv = $this->pool->cvOf($seeker);

        return Inertia::render('Public/Me', [
            'profile'    => BeneficiaryController::present($b),
            'occupation' => $update->occupationBlock($b),
            'account'    => [
                'email' => $seeker->email, 'visible' => $seeker->visible, 'notice_period' => $seeker->notice_period,
                'since' => $seeker->created_at?->toIso8601String(),
            ],
            'cv'         => $cv ? ['file' => $cv->original_name, 'extension' => $cv->extension, 'size' => $cv->size, 'at' => $cv->created_at?->toIso8601String()] : null,
            'added_by'   => PoolAddition::query()->where('job_seeker_id', $seeker->id)->orderByDesc('id')->get()
                ->map(function (PoolAddition $a) {
                    $c = Company::query()->find($a->company_id);

                    return $c ? ['name' => $c->name, 'name_ar' => $c->name_ar, 'at' => $a->created_at?->toIso8601String()] : null;
                })->filter()->values(),
            'limits'     => ['max_kb' => (int) config('cv.max_kb'), 'extensions' => config('cv.extensions')],
        ]);
    }

    public function edit(Request $request, CvProfileUpdate $update, OccupationCatalog $catalog): Response
    {
        $seeker = $this->seeker($request);
        $b = $this->pool->profileOf($seeker);

        return Inertia::render('Public/Edit', [
            'initial'  => BeneficiaryController::present($b) + ['occupation' => $update->occupationBlock($b), 'notice_period' => $seeker->notice_period],
            'options'  => app(BeneficiaryController::class)->formOptions() + ['notice_periods' => JobSeeker::NOTICE_PERIODS],
            'backbone' => $catalog->loaded(),
            'limits'   => ['max_kb' => (int) config('cv.max_kb'), 'extensions' => config('cv.extensions')],
        ]);
    }

    public function update(SeekerProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->pool->update($this->seeker($request), $data, $data['notice_period'] ?? null);

        return redirect()->route('seeker.profile')->with('success', __('pool.saved'));
    }

    public function visibility(Request $request): RedirectResponse
    {
        $data = $request->validate(['visible' => ['required', 'boolean']]);
        $this->pool->setVisible($this->seeker($request), (bool) $data['visible']);

        return back()->with('success', __($data['visible'] ? 'pool.visible_on' : 'pool.visible_off'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $seeker = $this->seeker($request);
        if (! Hash::check($request->input('password'), $seeker->password)) {
            throw ValidationException::withMessages(['password' => __('profile.password_incorrect')]);
        }

        $this->pool->delete($seeker);
        Auth::guard('seeker')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', __('pool.deleted'));
    }

    // ── The CV ───────────────────────────────────────────────────────

    public function download(Request $request, CvStorage $storage): FileResponse
    {
        $cv = $this->pool->cvOf($this->seeker($request));
        abort_unless($cv && $cv->stored_path && ($contents = $storage->get($cv->stored_path)) !== null, 404);

        return CvFileController::send($cv, $contents, $request->boolean('view'));
    }

    public function upload(Request $request, CvIntake $intake): RedirectResponse
    {
        $doc = JoinController::readUpload($request, $intake);
        if (! $doc->reading) {
            throw ValidationException::withMessages(['file' => __('pool.cv_unread')]);
        }
        $request->session()->put(self::SESSION_CV, $doc->uuid);

        return redirect()->route('seeker.cv.changes', $doc->uuid);
    }

    public function changes(Request $request, string $uuid): Response|RedirectResponse
    {
        $seeker = $this->seeker($request);
        if ($request->session()->get(self::SESSION_CV) !== $uuid) {
            return redirect()->route('seeker.profile');
        }
        $doc = $this->pool->pendingCv(Company::poolId(), $uuid);
        $b = $this->pool->profileOf($seeker);

        return Inertia::render('Public/CvChanges', [
            'doc'     => ['uuid' => $doc->uuid, 'file' => $doc->original_name],
            'profile' => ['name' => $b->displayName(app()->getLocale())],
            'rows'    => $this->pool->cvChanges($seeker, $doc),
        ]);
    }

    public function apply(Request $request, string $uuid): RedirectResponse
    {
        $data = $request->validate(['ticked' => ['present', 'array', 'max:500'], 'ticked.*' => ['string', 'max:40']]);
        abort_unless($request->session()->get(self::SESSION_CV) === $uuid, 404);

        $made = $this->pool->replaceCv($this->seeker($request), $uuid, $data['ticked']);
        $request->session()->forget(self::SESSION_CV);

        return redirect()->route('seeker.profile')->with('success', __('pool.cv_replaced', ['n' => $made]));
    }

    private function seeker(Request $request): JobSeeker
    {
        return $request->user('seeker');
    }
}
