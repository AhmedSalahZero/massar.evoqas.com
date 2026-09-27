<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\ApproveCvRequest;
use App\Http\Requests\App\SaveLearnedRuleRequest;
use App\Models\LearnedRule;
use App\Services\Cv\CvProfileUpdate;
use App\Services\Cv\LearnedRuleBook;
use App\Support\TextNormalizer;
use Illuminate\Http\JsonResponse;
use App\Models\Beneficiary;
use App\Models\CvDocument;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Beneficiaries\BeneficiaryRecorder;
use App\Services\Cv\CvIntake;
use App\Services\Cv\CvStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\ReviewQueueController (Scope v2 §3 Review Queue & Review Screen)
//  Location: app/Http/Controllers/App/ReviewQueueController.php
//  Permission: cv.review
//
//  GET  /app/review-queue                 index    CVs waiting for a person
//  GET  /app/review-queue/{uuid}          show     the CV text beside the filled form
//  POST /app/review-queue/{uuid}/approve  approve  create the profile from the checked form
//  POST /app/review-queue/{uuid}/attach   attach   add this CV to an existing profile (same person)
//  POST /app/review-queue/{uuid}/reject   reject   not a CV / not wanted: the file is deleted
//  POST /app/review-queue/{uuid}/reread   reread   read the CV again with today's Learned Rules
//                                                  (also: a PDF uploaded before the PDF reader was set up)
//  POST /app/review-queue/{uuid}/teach    teach    a Learned Rule (heading, job title, skill word) —
//                                                  this CV and the other waiting CVs with those
//                                                  words are read again            (+ rules.manage)
//  GET  /app/review-queue/profiles?q=…    profiles find the person to add a CV to (name, mobile, email, #)
//  GET  /app/review-queue/{uuid}/update/{number}   compare  "Update from this CV": the profile and the CV side by side
//  POST /app/review-queue/{uuid}/update/{number}   update   make the ticked changes and attach the CV
//
//  Approve with "Remember this occupation" (learn_title) also saves a
//  Learned Rule: the CV's title → the occupation the reviewer chose.
//
//  After each decision the reviewer goes straight to the next CV in
//  the queue. Every CV is looked up in the signed-in person's own
//  workspace only; one that is already decided cannot be decided
//  again (two reviewers on the same CV: the second is told so).
// ══════════════════════════════════════════════════════════════════

class ReviewQueueController extends Controller
{
    public function __construct(
        private readonly BeneficiaryRecorder $recorder,
        private readonly OccupationCatalog $catalog,
        private readonly CvIntake $intake,
        private readonly LearnedRuleBook $rules,
    ) {}

    // ── Queue ────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $companyId = $request->user()->company_id;
        $status = in_array($request->query('status'), CvDocument::OPEN, true) ? $request->query('status') : '';

        $list = CvDocument::query()->inWorkspace($companyId)
            ->whereIn('status', $status ? [$status] : CvDocument::OPEN)
            ->orderByDesc('id')
            ->paginate((int) config('cv.per_page', 25))
            ->withQueryString()
            ->through(fn (CvDocument $d) => self::summary($d));

        $counts = CvDocument::query()->inWorkspace($companyId)->whereIn('status', CvDocument::OPEN)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return Inertia::render('App/Cv/Queue', [
            'list'   => $list,
            'status' => $status,
            'counts' => collect(CvDocument::OPEN)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)]),
        ]);
    }

    // ── Review screen ────────────────────────────────────────────────

    public function show(Request $request, string $uuid): Response|RedirectResponse
    {
        $doc = $this->find($request, $uuid);
        if (! $doc->isOpen()) {
            return $doc->beneficiary_id && ($number = Beneficiary::query()->whereKey($doc->beneficiary_id)->value('number'))
                ? redirect()->route('app.beneficiaries.show', $number)
                : redirect()->route('app.review-queue.index');
        }
        $reading = $doc->reading ?? [];
        $form = $reading['form'] ?? [];
        $occupation = $reading['occupation'] ?? null;

        // Duplicates are checked again now: the queue may have changed since the upload.
        $duplicates = collect($this->intake->samePerson($doc->company_id, $doc->phone, $doc->email, $doc->id))
            ->merge(collect($doc->duplicates ?? [])->where('type', 'file'))
            ->values();

        return Inertia::render('App/Cv/Review', [
            'doc'        => self::summary($doc) + [
                'text'   => $doc->text,
                'marks'  => $reading['marks'] ?? [],
                'notes'  => $reading['notes'] ?? [],
                'attention' => $reading['attention'] ?? [],
                'on_text'   => $reading['marks_on_text'] ?? null,
                'links'     => $reading['links'] ?? [],
                'occupation' => $occupation ? [
                    'status'     => $occupation['status'],
                    'title'      => $occupation['title'],
                    'candidates' => array_values(array_filter($occupation['candidates'] ?? [], fn ($c) => ($c['score'] ?? 0) > 0)),
                ] : null,
            ],
            'initial'    => $form ? array_merge($form, [
                'occupation' => $occupation['choice'] ?? null,
                'education'  => $form['education'] ?? [],
                'work_history' => $form['work_history'] ?? [],
                'skills'     => $form['skills'] ?? [],
                'languages'  => $form['languages'] ?? [],
            ]) : null,
            'duplicates' => $duplicates,
            'options'    => app(BeneficiaryController::class)->formOptions(),
            'backbone'   => $this->catalog->loaded(),
            'can_download' => $request->user()->can('cv.download'),
            'next'       => $this->next($doc),
            'waiting'    => CvDocument::query()->inWorkspace($doc->company_id)->whereIn('status', CvDocument::OPEN)->count(),
            'teach'      => [
                'can'      => $request->user()->can('rules.manage'),
                'sections' => LearnedRule::SECTIONS,
                // Offer "Remember: <title> means this occupation" when the engine did not settle it.
                'title'    => $occupation && ! in_array($occupation['status'], ['exact', 'rule'], true) ? ($occupation['title'] ?? null) : null,
            ],
        ]);
    }

    // ── Decisions ────────────────────────────────────────────────────

    public function approve(ApproveCvRequest $request, string $uuid): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $number = DB::transaction(function () use ($request, $uuid, $user, $data) {
            $doc = $this->lockOpen($request, $uuid);

            if (! ($data['confirm_duplicate'] ?? false)) {
                $same = $this->recorder->duplicates($doc->company_id, $data['phone'] ?? null, $data['email'] ?? null);
                if ($same->isNotEmpty()) {
                    $names = $same->map(fn (Beneficiary $b) => '#'.$b->number.' '.$b->displayName(app()->getLocale()))->implode('، ');
                    throw ValidationException::withMessages(['duplicate' => __('beneficiaries.v.duplicate', ['names' => $names])]);
                }
            }

            // Kept as the engine's choice when the reviewer did not change it.
            $engine = $doc->reading['occupation'] ?? [];
            $same = in_array($engine['status'] ?? '', ['exact', 'rule'], true)
                && (int) ($engine['choice']['esco_id'] ?? 0) === (int) ($data['esco_occupation_id'] ?? 0)
                && ($engine['choice']['group_only'] ?? false ? $engine['choice']['unit_code'] : null) === ($data['occupation_unit'] ?? null);
            $method = $same ? (($engine['status'] ?? '') === 'rule' ? 'cv_rule' : 'cv_exact') : 'cv_review';

            $b = $this->recorder->create($user->company()->firstOrFail(), $data, $user, $method, 'cv_upload');
            $this->decide($doc, CvDocument::APPROVED, $user, $b->id);

            // "Remember: <the CV's title> means this occupation" — a person decided it.
            $title = $engine['title'] ?? null;
            if ($request->boolean('learn_title') && $title && ! $same && $user->can('rules.manage')
                && (! empty($data['esco_occupation_id']) || ! empty($data['occupation_unit']))) {
                $this->rules->teach((int) $doc->company_id, $user, 'title', $title,
                    ['esco_occupation_id' => $data['esco_occupation_id'] ?? null, 'occupation_unit' => $data['occupation_unit'] ?? null], 'review');
            }

            return $b->number;
        });

        return $this->onward($uuid, __('cv.approved', ['number' => $number]));
    }

    public function attach(Request $request, string $uuid): RedirectResponse
    {
        $data = $request->validate(['number' => ['required', 'integer', 'min:1']], ['number.*' => __('cv.attach_which')]);
        $user = $request->user();

        $number = DB::transaction(function () use ($request, $uuid, $user, $data) {
            $doc = $this->lockOpen($request, $uuid);
            $b = Beneficiary::query()->inWorkspace($doc->company_id)->where('number', $data['number'])->first();
            if (! $b) {
                throw ValidationException::withMessages(['number' => __('cv.attach_not_found', ['number' => $data['number']])]);
            }
            $this->decide($doc, CvDocument::ATTACHED, $user, $b->id);

            return $b->number;
        });

        return $this->onward($uuid, __('cv.attached', ['number' => $number]));
    }

    // ── Learned Rules, taught on the review screen ───────────────────

    public function teach(SaveLearnedRuleRequest $request, string $uuid): RedirectResponse
    {
        $user = $request->user();
        $doc = $this->find($request, $uuid);
        if (! $doc->isOpen()) {
            throw ValidationException::withMessages(['cv' => __('cv.already_decided')]);
        }
        $this->rules->teach((int) $doc->company_id, $user, $request->input('kind'), $request->input('phrase'), $request->target(), 'review');

        // The rule works at once: this CV, and the other waiting CVs with those words, are read again.
        $this->intake->reread($doc);
        $others = $this->intake->rereadContaining((int) $doc->company_id, $request->input('phrase'), $doc->id);

        return redirect()->route('app.review-queue.show', $doc->uuid)
            ->with('success', $others ? __('rules.taught_many', ['n' => $others]) : __('rules.taught'));
    }

    /**
     * "Read again": the CV is read again with today's rules (after a rule changed elsewhere),
     * or a PDF that came in before the PDF reader was set up is read at last.
     */
    public function reread(Request $request, string $uuid): RedirectResponse
    {
        $doc = $this->find($request, $uuid);
        if (! $doc->isOpen()) {
            throw ValidationException::withMessages(['cv' => __('cv.already_decided')]);
        }
        $this->intake->reread($doc);
        // Still no PDF reader on this computer: say so, not "read again".
        [$type, $message] = $doc->problem === 'no_pdf_reader' ? ['warning', 'cv.no_pdf_reader'] : ['success', 'cv.reread'];

        return redirect()->route('app.review-queue.show', $doc->uuid)->with($type, __($message));
    }

    // ── Update from this CV ──────────────────────────────────────────

    /** People of this workspace matching a name, mobile, email or profile number (for "Add to an existing profile"). */
    public function profiles(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        if (mb_strlen($q) < 2 && ! ctype_digit($q)) {
            return response()->json([]);
        }
        $norm = TextNormalizer::normalize($q);
        $digits = preg_replace('/\D/', '', strtr($q, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])) ?? '';
        $locale = app()->getLocale();

        $found = Beneficiary::query()->inWorkspace($companyId)
            ->where(function ($w) use ($q, $norm, $digits) {
                if (ctype_digit($q) && strlen($q) <= 7) {
                    $w->orWhere('number', (int) $q);
                }
                if ($norm !== '') {
                    $w->orWhere('search_text', 'like', '%'.$norm.'%');
                }
                if (strlen($digits) >= 6) {
                    $w->orWhere('phone', 'like', '%'.ltrim(substr($digits, -10), '0').'%');
                }
            })
            ->orderByDesc('id')->limit(8)->get();

        return response()->json($found->map(fn (Beneficiary $b) => [
            'number' => $b->number, 'name' => $b->displayName($locale), 'phone' => $b->phone, 'email' => $b->email,
            'city' => $b->city, 'jobs' => count($b->work_history ?? []),
        ])->values());
    }

    public function compare(Request $request, string $uuid, int $number, CvProfileUpdate $update): Response|RedirectResponse
    {
        $doc = $this->find($request, $uuid);
        if (! $doc->isOpen() || $doc->status === CvDocument::UNREADABLE || ! $doc->reading) {
            return redirect()->route('app.review-queue.show', $doc->uuid);
        }
        $b = Beneficiary::query()->inWorkspace($doc->company_id)->where('number', $number)->firstOrFail();

        return Inertia::render('App/Cv/Update', [
            'doc'     => self::summary($doc),
            'profile' => ['number' => $b->number, 'name' => $b->displayName(app()->getLocale()), 'occupation' => $update->occupationBlock($b)] + $update->current($b),
            'rows'    => $update->compare($b, $doc),
            'options' => app(BeneficiaryController::class)->formOptions(),
        ]);
    }

    public function update(Request $request, string $uuid, int $number, CvProfileUpdate $update): RedirectResponse
    {
        $data = $request->validate(['ticked' => ['present', 'array', 'max:500'], 'ticked.*' => ['string', 'max:40']]);
        $user = $request->user();

        [$b, $made] = DB::transaction(function () use ($request, $uuid, $number, $update, $user, $data) {
            $doc = $this->lockOpen($request, $uuid);
            $b = Beneficiary::query()->inWorkspace($doc->company_id)->where('number', $number)->lockForUpdate()->firstOrFail();
            $made = $update->apply($b, $doc, $data['ticked'], $user);
            $this->decide($doc, CvDocument::ATTACHED, $user, $b->id);

            return [$b, $made];
        });

        return $this->onward($uuid, $made ? __('cv.updated', ['number' => $b->number, 'n' => $made]) : __('cv.attached', ['number' => $b->number]));
    }

    public function reject(Request $request, string $uuid, CvStorage $storage): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $uuid, $user, $storage) {
            $doc = $this->lockOpen($request, $uuid);
            // Personal data is not kept for a rejected CV: the file, the text and
            // the reading are removed; the file name and who rejected it remain.
            $storage->delete($doc->stored_path);
            $doc->forceFill(['stored_path' => null, 'text' => null, 'reading' => null, 'duplicates' => null, 'phone' => null, 'email' => null]);
            $this->decide($doc, CvDocument::REJECTED, $user, null);
        });

        return $this->onward($uuid, __('cv.rejected'));
    }

    // ── Shared ───────────────────────────────────────────────────────

    /** One CV as the upload page and the queue list show it. */
    public static function summary(CvDocument $d): array
    {
        $locale = app()->getLocale();
        $b = $d->relationLoaded('beneficiary') ? $d->beneficiary : null;

        return [
            'uuid'       => $d->uuid,
            'file'       => $d->original_name,
            'extension'  => $d->extension,
            'size'       => $d->size,
            'status'     => $d->status,
            'problem'    => $d->problem,
            'language'   => $d->language,
            'name'       => $d->foundName($locale),
            'title'      => $d->reading['occupation']['title'] ?? ($d->reading['titles'][0] ?? null),
            'attention'  => $d->reading['attention'] ?? [],
            'duplicates' => count($d->duplicates ?? []),
            'uploaded_by' => $d->uploaded_by_name,
            'uploaded_at' => $d->created_at?->toIso8601String(),
            'beneficiary' => $b ? ['number' => $b->number, 'name' => $b->displayName($locale)] : null,
        ];
    }

    private function find(Request $request, string $uuid): CvDocument
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 404);

        return CvDocument::query()->inWorkspace($companyId)->where('uuid', $uuid)->firstOrFail();
    }

    /** The CV, locked for this decision, and still waiting — or a clear message. */
    private function lockOpen(Request $request, string $uuid): CvDocument
    {
        $doc = CvDocument::query()->inWorkspace((int) $request->user()->company_id)->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
        if (! $doc->isOpen()) {
            throw ValidationException::withMessages(['cv' => __('cv.already_decided')]);
        }

        return $doc;
    }

    private function decide(CvDocument $doc, string $status, $user, ?int $beneficiaryId): void
    {
        $doc->forceFill([
            'status'           => $status,
            'beneficiary_id'   => $beneficiaryId,
            'reviewed_by'      => $user->id,
            'reviewed_by_name' => mb_substr($user->name, 0, 100),
            'reviewed_at'      => now(),
        ])->save();
    }

    /** The next CV waiting (older first after this one, then the newest). */
    private function next(CvDocument $doc): ?string
    {
        $q = fn () => CvDocument::query()->inWorkspace($doc->company_id)->whereIn('status', CvDocument::OPEN)->whereKeyNot($doc->id);

        return $q()->where('id', '<', $doc->id)->orderByDesc('id')->value('uuid')
            ?? $q()->orderByDesc('id')->value('uuid');
    }

    private function onward(string $uuid, string $message): RedirectResponse
    {
        $doc = CvDocument::query()->where('uuid', $uuid)->first();
        $next = $doc ? $this->next($doc) : null;

        return ($next ? redirect()->route('app.review-queue.show', $next) : redirect()->route('app.review-queue.index'))
            ->with('success', $message);
    }
}
