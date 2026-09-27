<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\Company;
use App\Models\JobSeeker;
use App\Models\PoolAccessLog;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Beneficiaries\SalaryCheck;
use App\Services\Cv\CvBankIndex;
use App\Services\Cv\CvBankSearch;
use App\Services\Cv\CvProfileUpdate;
use App\Services\Cv\CvStorage;
use App\Services\Pool\TalentPool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as FileResponse;

// ══════════════════════════════════════════════════════════════════
//  Massar — Public Talent Pool, for partners (Scope v2 §3 · Step 10)
//  Location: app/Http/Controllers/App/PoolController.php
//  Routes:  GET  /app/pool              (pool.index)  search (pool.view)
//           GET  /app/pool/{uuid}       (pool.show)   one job seeker (pool.view)
//           GET  /app/pool/{uuid}/cv    (pool.cv)     their CV (pool.view + cv.download)
//           POST /app/pool/{uuid}/add   (pool.add)    add to my workspace (pool.add)
//
//  Only job seekers who confirmed their email AND chose to be seen
//  (consent) are listed. The search is the Searchable CV Bank's own
//  (every word in the CV and profile, occupation synonyms, occupation
//  at any level, governorate, years), run on the Talent Pool.
//  Opening a profile, downloading its CV and adding it are logged.
//  "Add to my workspace" gives the partner its own copy of the profile
//  and CV (TalentPool::add). Many partners can add the same person;
//  the person is not notified, and sees the partners in "My profile".
// ══════════════════════════════════════════════════════════════════

class PoolController extends Controller
{
    public function __construct(private readonly TalentPool $pool, private readonly CvBankSearch $search) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($companyId, 404);
        $locale = app()->getLocale();
        $f = $this->filters($request);
        $poolId = Company::poolId();

        $visible = fn (Builder $q) => $q->whereIn('beneficiaries.id', JobSeeker::query()->inPool()->select('beneficiary_id'));
        $list = CvBankIndex::ready() ? $this->search->search($poolId, $f, $locale, $visible) : null;

        if ($list) {
            // Addresses use the job seeker's id (uuid), never the pool's numbers.
            $numbers = collect($list->items())->pluck('number');
            $seekers = JobSeeker::query()->join('beneficiaries', 'beneficiaries.id', '=', 'job_seekers.beneficiary_id')
                ->where('beneficiaries.company_id', $poolId)->whereIn('beneficiaries.number', $numbers)
                ->get(['job_seekers.id', 'job_seekers.uuid', 'job_seekers.notice_period', 'beneficiaries.number']);
            $added = \App\Models\PoolAddition::query()->where('company_id', $companyId)->whereIn('job_seeker_id', $seekers->pluck('id'))
                ->whereNotNull('beneficiary_id')->pluck('job_seeker_id')->all();
            $list->through(function (array $r) use ($seekers, $added) {
                $s = $seekers->firstWhere('number', $r['number']);
                unset($r['number']);

                return $r + ['uuid' => $s?->uuid, 'notice_period' => $s?->notice_period, 'added' => $s && in_array($s->id, $added, true)];
            });
        }

        return Inertia::render('App/Pool/Index', [
            'filters'      => $f,
            'occupation'   => $f['occ'] ? $this->search->occupationLabel($f['occ'], $locale) : null,
            'list'         => $list,
            'total'        => JobSeeker::query()->inPool()->count(),
            'ready'        => CvBankIndex::ready(),
            'governorates' => config('beneficiaries.governorates'),
            'sectors'      => \App\Models\Sector::tree(),
        ]);
    }

    public function show(Request $request, string $uuid, CvProfileUpdate $update, OccupationCatalog $catalog): Response
    {
        $user = $request->user();
        $seeker = $this->find($uuid);
        $b = $this->pool->profileOf($seeker);
        $b->load(['unit.enoc', 'escoOccupation']);
        PoolAccessLog::write($seeker, $user, 'view');

        $market = $b->isco_group_id ? $catalog->market($b->isco_group_id, forPartners: true) : null;
        $copy = $this->pool->copyIn($seeker, (int) $user->company_id);
        $cv = $this->pool->cvOf($seeker);

        return Inertia::render('App/Pool/Show', [
            'uuid'         => $seeker->uuid,
            'profile'      => BeneficiaryController::present($b) + ['notice_period' => $seeker->notice_period, 'since' => $seeker->created_at?->toIso8601String()],
            'occupation'   => $update->occupationBlock($b),
            'market'       => $market,
            'salary'       => SalaryCheck::compare($b->expected_salary, (bool) $b->isco_group_id, $market),
            'cv'           => $cv ? ['file' => $cv->original_name, 'extension' => $cv->extension, 'size' => $cv->size] : null,
            'copy'         => $copy ? ['number' => $copy->number] : null,
            'can_add'      => $user->can('pool.add') && $user->can('beneficiaries.create'),
            'can_download' => $user->can('cv.download'),
        ]);
    }

    public function cv(Request $request, string $uuid, CvStorage $storage): FileResponse
    {
        $seeker = $this->find($uuid);
        $cv = $this->pool->cvOf($seeker);
        abort_unless($cv && $cv->stored_path && ($contents = $storage->get($cv->stored_path)) !== null, 404);
        PoolAccessLog::write($seeker, $request->user(), 'cv');

        return CvFileController::send($cv, $contents, $request->boolean('view'));
    }

    public function add(Request $request, string $uuid): RedirectResponse
    {
        $seeker = $this->find($uuid);
        [$copy, $made] = $this->pool->add($seeker, $request->user(), $request->boolean('confirm_duplicate'));

        return redirect()->route('app.beneficiaries.show', $copy->number)
            ->with('success', __($made ? 'pool.added' : 'pool.already_added', ['number' => $copy->number]));
    }

    /** A job seeker in the Talent Pool (confirmed email, visible), or 404. */
    private function find(string $uuid): JobSeeker
    {
        return JobSeeker::query()->inPool()->where('uuid', $uuid)->firstOrFail();
    }

    private function filters(Request $request): array
    {
        $occ = (string) $request->query('occ', '');

        return [
            'q'           => mb_substr(trim((string) $request->query('q', '')), 0, 120),
            'occ'         => preg_match('/^(isco|enoc|esco):[\d.]{1,40}$/', $occ) ? $occ : '',
            'governorate' => in_array($request->query('governorate'), config('beneficiaries.governorates'), true) ? $request->query('governorate') : '',
            'min_years'   => max(0, min(40, (int) $request->query('min_years', 0))),
            'cv_lang'     => in_array($request->query('cv_lang'), ['ar', 'en'], true) ? $request->query('cv_lang') : '',
            'sector'      => preg_match('/^(IND|TRD|SRV|[ITS]\d{2})$/', (string) $request->query('sector')) ? (string) $request->query('sector') : '',
        ];
    }
}
