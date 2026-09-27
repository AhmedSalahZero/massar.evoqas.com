<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\SaveBeneficiaryRequest;
use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\CvDocument;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Beneficiaries\BeneficiaryRecorder;
use App\Services\Beneficiaries\OccupationDisplay;
use App\Services\Beneficiaries\SalaryCheck;
use App\Support\TextNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\BeneficiaryController (Scope v2 §3 Module B)
//  Location: app/Http/Controllers/App/BeneficiaryController.php
//
//  GET    /app/beneficiaries                 index   list, search, filters      beneficiaries.view
//  GET    /app/beneficiaries/create          create  empty form                 beneficiaries.create
//  POST   /app/beneficiaries                 store   register                   beneficiaries.create
//  GET    /app/beneficiaries/{number}        show    profile + market panel     beneficiaries.view
//                                                    + salary check + history
//                                                    + eligibility, matches and
//                                                    suggestions (Steps 11–13)
//  GET    /app/beneficiaries/{number}/edit   edit    filled form                beneficiaries.edit
//  PATCH  /app/beneficiaries/{number}        update  save changes               beneficiaries.edit
//  GET    /app/occupation-picker?q=…         pick    occupation search (JSON)   beneficiaries.view
//
//  The profile page also lists the original CV files attached to the
//  person (uploaded, approved or attached in the CV Bank).
//
//  {number} is the partner's own running number, looked up INSIDE the
//  signed-in person's workspace only. Another partner's beneficiary
//  is simply "not found" (404) — the page never says it exists.
//
//  Saving always goes through BeneficiaryRecorder (profile + history
//  in one transaction). Before registering someone, or changing a
//  mobile or email, the same workspace is checked for a person with
//  the same mobile or email; the case worker then confirms it is a
//  different person, or opens the existing profile instead.
// ══════════════════════════════════════════════════════════════════

class BeneficiaryController extends Controller
{
    public function __construct(
        private readonly BeneficiaryRecorder $recorder,
        private readonly OccupationCatalog $catalog,
    ) {}

    // ── List ─────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $companyId = $request->user()->company_id;
        $f = $this->filters($request);

        $query = Beneficiary::query()->inWorkspace($companyId);

        foreach (TextNormalizer::tokens($f['q']) as $token) {
            // A short number ("12" or "#12") is a beneficiary number; a longer
            // one can also be part of a mobile number.
            if (ctype_digit($token) && strlen($token) < 4) {
                $query->where('number', (int) $token);
            } else {
                $query->where(fn ($q) => $q->where('search_text', 'like', '%'.$token.'%')
                    ->when(ctype_digit($token) && strlen($token) <= 7, fn ($q) => $q->orWhere('number', (int) $token)));
            }
        }
        $query
            ->when($f['governorate'] !== '', fn ($q) => $q->where('governorate', $f['governorate']))
            ->when($f['gender'] !== '', fn ($q) => $q->where('gender', $f['gender']))
            ->when($f['education'] !== '', fn ($q) => $q->where('education_level', $f['education']))
            ->when($f['major'] === 'none', fn ($q) => $q->whereNull('isco_group_id'))
            ->when(preg_match('/^\d$/', $f['major']), fn ($q) => $q->where('isco_code', 'like', $f['major'].'%'))
            ->when($f['min_years'] > 0, fn ($q) => $q->where('experience_months', '>=', $f['min_years'] * 12));

        $list = $query->with(['unit.enoc', 'escoOccupation'])
            ->orderByDesc('number')
            ->paginate((int) config('beneficiaries.per_page', 25))
            ->withQueryString()
            ->through(fn (Beneficiary $b) => [
                'number'      => $b->number,
                'name_ar'     => $b->name_ar,
                'name_en'     => $b->name_en,
                'initials'    => $b->initials(),
                'gender'      => $b->gender,
                'age'         => $b->age(),
                'governorate' => $b->governorate,
                'phone'       => $b->phone,
                'experience'  => $b->experience_months,
                'occupation'  => OccupationDisplay::block($b->unit, $b->escoOccupation, $b->gender),
                'created_at'  => $b->created_at?->toIso8601String(),
            ]);

        return Inertia::render('App/Beneficiaries/Index', [
            'list'    => $list,
            'filters' => $f,
            'total'   => Beneficiary::query()->inWorkspace($companyId)->count(),
            'options' => $this->options(),
            'majors'  => $this->catalog->loaded() ? $this->catalog->majors() : [],
        ]);
    }

    // ── Register ─────────────────────────────────────────────────────

    public function create(): Response
    {
        return Inertia::render('App/Beneficiaries/Form', [
            'beneficiary' => null,
            'options'     => $this->options(),
            'backbone'    => $this->catalog->loaded(),
        ]);
    }

    public function store(SaveBeneficiaryRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $this->stopOnDuplicates($data, $user->company_id);

        $b = $this->recorder->create($user->company, $data, $user);

        return redirect()->route('app.beneficiaries.show', $b->number)
            ->with('success', __('beneficiaries.registered', ['number' => $b->number]));
    }

    // ── Profile ──────────────────────────────────────────────────────

    public function show(Request $request, int $number): Response
    {
        $b = $this->find($request, $number);
        $b->load(['unit.enoc', 'escoOccupation', 'createdBy:id,name', 'updatedBy:id,name', 'occupationSetBy:id,name']);

        $market = $b->isco_group_id ? $this->catalog->market($b->isco_group_id, forPartners: true) : null;

        return Inertia::render('App/Beneficiaries/Show', [
            'beneficiary' => self::present($b) + [
                'created_by' => $b->createdBy?->name,
                'updated_by' => $b->updatedBy?->name,
                'created_at' => $b->created_at?->toIso8601String(),
                'updated_at' => $b->updated_at?->toIso8601String(),
            ],
            'occupation'  => $b->unit ? OccupationDisplay::block($b->unit, $b->escoOccupation, $b->gender) + [
                'method' => $b->occupation_method,
                'set_by' => $b->occupationSetBy?->name,
                'set_at' => $b->occupation_set_at?->toIso8601String(),
            ] : null,
            'market'      => $market,
            'salary'      => SalaryCheck::compare($b->expected_salary, (bool) $b->isco_group_id, $market),
            // The original CV files attached to this profile (Scope v2 §3: "the original CV file").
            'cvs'         => CvDocument::query()->inWorkspace($b->company_id)->where('beneficiary_id', $b->id)
                ->whereIn('status', CvDocument::ON_PROFILE)->orderByDesc('id')->get()
                ->map(fn (CvDocument $d) => [
                    'uuid' => $d->uuid, 'file' => $d->original_name, 'extension' => $d->extension, 'size' => $d->size,
                    'status' => $d->status, 'by' => $d->uploaded_by_name, 'at' => $d->created_at?->toIso8601String(),
                    'reviewed_by' => $d->reviewed_by_name,
                ]),
            'can_download' => $request->user()->can('cv.download'),
            // Step 11/12: the jobs and trainings this person was checked against, newest first,
            // and the open ones to check against.
            'eligibility' => $request->user()->can('opportunities.view')
                ? \App\Models\EligibilityAssessment::query()->inWorkspace($b->company_id)->where('beneficiary_id', $b->id)
                    ->with('opportunity')->orderByDesc('checked_at')->get()
                    ->map(fn ($a) => \App\Services\Eligibility\Assessor::present($a))->values()
                : null,
            'opportunities' => $request->user()->can('eligibility.check')
                ? \App\Models\Opportunity::query()->inWorkspace($b->company_id)->open()->orderBy('kind')->orderBy('title')->get()
                    ->map(fn ($o) => $o->brief())->values()
                : [],
            // Step 13: every job and training the person was referred to, with its timeline,
            // and the open ones whose occupations fit the person.
            'matches'     => $request->user()->can('matches.view')
                ? app(\App\Services\Matches\MatchBook::class)->query($b->company_id)->where('opportunity_matches.beneficiary_id', $b->id)
                    ->with('opportunity')->orderByDesc('opportunity_matches.changed_at')->get()
                    ->map(fn ($m) => app(\App\Services\Matches\MatchBook::class)->present($m, person: false, events: true))->values()
                : null,
            'suggested'   => $request->user()->can('opportunities.view') && $request->user()->can('matches.view')
                ? app(\App\Services\Matches\MatchBook::class)->suggested($b, app(\App\Services\Matches\MatchFit::class))
                : [],
            'stop_reasons' => \App\Models\OpportunityMatch::STOP_REASONS,
            'history'     => $b->history()->limit(50)->get()->map(fn (BeneficiaryChange $c) => [
                'id'      => $c->id,
                'action'  => $c->action,
                'by'      => $c->user_name,
                'at'      => $c->created_at?->toIso8601String(),
                // "Update from this CV" notes the CV file the changes came from.
                'from_cv' => $c->changes['_from_cv'] ?? null,
                // Added from the Public Talent Pool (Step 10).
                'from_pool' => (bool) ($c->changes['_from_pool'] ?? false),
                // A case worker's eligibility decision (Step 11): the job or training, before → after, reason.
                'eligibility' => $c->changes['_eligibility'] ?? null,
                // A referral or a stage change (Step 13): the job or training, what happened, the note or reason.
                'match'   => $c->changes['_match'] ?? null,
                'changes' => is_array($c->changes) ? (array_diff_key($c->changes, ['_from_cv' => 1, '_from_pool' => 1, '_eligibility' => 1, '_match' => 1]) ?: null) : $c->changes,
            ]),
        ]);
    }

    // ── Edit ─────────────────────────────────────────────────────────

    public function edit(Request $request, int $number): Response
    {
        $b = $this->find($request, $number);
        $b->load(['unit.enoc', 'escoOccupation']);

        return Inertia::render('App/Beneficiaries/Form', [
            'beneficiary' => self::present($b) + [
                'occupation' => OccupationDisplay::block($b->unit, $b->escoOccupation, $b->gender),
            ],
            'options'     => $this->options(),
            'backbone'    => $this->catalog->loaded(),
        ]);
    }

    public function update(SaveBeneficiaryRequest $request, int $number): RedirectResponse
    {
        $b = $this->find($request, $number);
        $data = $request->validated();

        // Only a NEW mobile or email is checked — an existing, already
        // confirmed one does not ask again on every save.
        $newPhone = $data['phone'] !== $b->phone ? $data['phone'] : null;
        $newEmail = $data['email'] !== $b->email ? $data['email'] : null;
        $this->stopOnDuplicates(['phone' => $newPhone, 'email' => $newEmail] + $data, $b->company_id, $b->id);

        $this->recorder->update($b, $data, $request->user());

        return redirect()->route('app.beneficiaries.show', $b->number)->with('success', __('beneficiaries.saved'));
    }

    // ── Occupation picker (JSON) ─────────────────────────────────────

    public function pick(Request $request, OccupationDisplay $display): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $gender = in_array($request->query('gender'), ['male', 'female'], true) ? $request->query('gender') : null;

        return response()->json([
            'results' => mb_strlen($q) < 2 ? [] : $display->pick($q, $gender),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** One beneficiary of the signed-in person's own workspace, or 404. */
    private function find(Request $request, int $number): Beneficiary
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 404);

        $b = Beneficiary::query()->inWorkspace($companyId)->where('number', $number)->firstOrFail();
        abort_unless($b->company_id === $companyId, 404);   // belt and braces

        return $b;
    }

    private function stopOnDuplicates(array $data, int $companyId, ?int $exceptId = null): void
    {
        if ($data['confirm_duplicate'] ?? false) {
            return;
        }
        $same = $this->recorder->duplicates($companyId, $data['phone'] ?? null, $data['email'] ?? null, $exceptId);
        if ($same->isEmpty()) {
            return;
        }

        $locale = app()->getLocale();
        $names = $same->map(fn (Beneficiary $d) => '#'.$d->number.' '.$d->displayName($locale))->implode('، ');

        throw ValidationException::withMessages([
            'duplicate' => __('beneficiaries.v.duplicate', ['names' => $names]),
        ])->errorBag('default');
    }

    /** The profile's own fields, as the form and the profile page use them. */
    public static function present(Beneficiary $b): array
    {
        return [
            'number'            => $b->number,
            'name_ar'           => $b->name_ar,
            'name_en'           => $b->name_en,
            'initials'          => $b->initials(),
            'gender'            => $b->gender,
            'date_of_birth'     => $b->date_of_birth?->format('Y-m-d'),
            'age'               => $b->age(),
            'military_status'   => $b->military_status,
            'governorate'       => $b->governorate,
            'city'              => $b->city,
            'phone'             => $b->phone,
            'email'             => $b->email,
            'education_level'   => $b->education_level,
            'education'         => $b->education ?? [],
            'work_history'      => self::jobsForScreen($b->work_history ?? []),
            'experience_months' => $b->experience_months,
            'skills'            => $b->skills ?? [],
            'languages'         => $b->languages ?? [],
            'expected_salary'   => $b->expected_salary,
            'job_type'          => $b->job_type,
        ];
    }

    /** Each job with its sector's name, for the screens (Step 10.5). Not saved. */
    public static function jobsForScreen(array $jobs): array
    {
        $names = app(\App\Services\Employers\EmployerBook::class)->sectorNames();
        $ar = app()->getLocale() === 'ar';

        return array_map(function ($j) use ($names, $ar) {
            if (! is_array($j)) {
                return $j;
            }
            $sub = $names[$j['sub_sector'] ?? ''] ?? null;
            $sec = $sub ? ($names[$sub['parent']] ?? null) : null;
            $j['sector_label'] = $sub ? (($sec ? ($ar ? $sec['name_ar'] : $sec['name_en']).' › ' : '').($ar ? $sub['name_ar'] : $sub['name_en'])) : null;
            if (! $sub && ! empty($j['sub_sector_other']) && ($sec = $names[$j['sector'] ?? ''] ?? null)) {
                $j['sector_label'] = ($ar ? $sec['name_ar'] : $sec['name_en']).' › '.$j['sub_sector_other'];   // "Other", as typed
            }

            return $j;
        }, $jobs);
    }

    private function filters(Request $request): array
    {
        $c = config('beneficiaries');
        $pick = fn (string $key, array $allowed) => in_array($request->query($key), $allowed, true) ? $request->query($key) : '';

        return [
            'q'           => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'governorate' => $pick('governorate', $c['governorates']),
            'gender'      => $pick('gender', $c['genders']),
            'education'   => $pick('education', $c['education_levels']),
            'major'       => $pick('major', ['none', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9']),
            'min_years'   => max(0, min(40, (int) $request->query('min_years', 0))),
        ];
    }

    /** The form's fixed choices — also used by the CV review screen. */
    public function formOptions(): array
    {
        return $this->options();
    }

    private function options(): array
    {
        return collect(config('beneficiaries'))->only([
            'governorates', 'genders', 'military_statuses', 'education_levels', 'languages', 'language_levels', 'job_types', 'max',
        ])->all() + [
            // Step 10.5: each job's country, sector and sub-sector.
            'countries' => config('countries.codes'),
            'cities'    => config('countries.cities'),
            'sectors'   => \App\Models\Sector::tree(),
        ];
    }
}
