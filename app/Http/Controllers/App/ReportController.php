<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ReportDownload;
use App\Models\SavedReport;
use App\Models\Sector;
use App\Services\Cv\CvBankSearch;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as FileResponse;

// ══════════════════════════════════════════════════════════════════
//  Massar — ReportController (Step 14 · Reports)
//  Location: app/Http/Controllers/App/ReportController.php
//  Scope: docs/SCOPE_REPORTS.md
//
//  Partner workspace (its own people only):
//  GET    /app/reports?r={…}            index    the question and its answer   reports.view
//  GET    /app/reports/export?r=&format  export   Excel or PDF (logged)         reports.export
//  POST   /app/reports/saved             save     keep the question for the team reports.view
//  PATCH  /app/reports/saved/{id}        rename                                 reports.view
//  DELETE /app/reports/saved/{id}        forget                                 reports.view
//
//  Super Admin (every partner together, counts only, "fewer than 5"):
//  GET    /admin/reports?r={…}          adminIndex                             reports.view
//  GET    /admin/reports/export          adminExport                            reports.export
//  GET    …/reports/occupations?q=       occupations  the occupation filter's search (JSON)
//
//  r is the question as JSON; ReportBuilder::normalize() cleans it.
// ══════════════════════════════════════════════════════════════════

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportBuilder $builder,
        private readonly ReportExporter $exporter,
    ) {}

    // ── Partner workspace ────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $companyId = $this->companyId($request);
        $p = $this->params($request);
        $locale = app()->getLocale();
        $standard = $request->user()->occupation_standard ?: 'enoc';

        return Inertia::render('App/Reports/Index', $this->page($p, $companyId, $locale, $standard) + [
            'saved' => SavedReport::query()->inWorkspace($companyId)->orderBy('name')->get()
                ->map(fn (SavedReport $s) => [
                    'id' => $s->id, 'name' => $s->name, 'by' => $s->created_by_name, 'params' => ReportBuilder::normalize($s->params),
                    'can_change' => $s->canChange($request->user()), 'updated_at' => $s->updated_at?->toIso8601String(),
                ])->values(),
            'can_open_people' => $request->user()->can('beneficiaries.view'),
        ]);
    }

    public function export(Request $request): FileResponse
    {
        $companyId = $this->companyId($request);

        return $this->download($request, $companyId, (string) ($request->user()->company?->displayName(app()->getLocale()) ?? ''));
    }

    public function save(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'params' => ['required', 'array']], ['name.*' => __('reports.v.name')]);
        $name = trim($data['name']);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => __('reports.v.name')]);
        }
        $user = $request->user();
        $s = new SavedReport;
        $s->forceFill([
            'company_id' => $companyId, 'name' => $name, 'params' => ReportBuilder::normalize($data['params']),
            'created_by' => $user->id, 'created_by_name' => mb_substr($user->name, 0, 100),
        ])->save();

        return back()->with('success', __('reports.saved', ['name' => $name]));
    }

    public function rename(Request $request, int $id): RedirectResponse
    {
        $s = $this->saved($request, $id);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']], ['name.*' => __('reports.v.name')]);
        $s->forceFill(['name' => trim($data['name'])])->save();

        return back()->with('success', __('reports.renamed'));
    }

    public function forget(Request $request, int $id): RedirectResponse
    {
        $this->saved($request, $id)->delete();

        return back()->with('success', __('reports.deleted'));
    }

    // ── Super Admin ──────────────────────────────────────────────────

    public function adminIndex(Request $request): Response
    {
        $p = $this->params($request);
        $p['measure'] = 'count';

        return Inertia::render('Admin/Reports/Index', $this->page($p, null, app()->getLocale(), $request->user()->occupation_standard ?: 'enoc') + [
            'saved' => [], 'can_open_people' => false,
        ]);
    }

    public function adminExport(Request $request): FileResponse
    {
        return $this->download($request, null, __('reports.all_partners'));
    }

    /** The occupation filter's search: any standard, any level (the CV Bank's list). */
    public function occupations(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(app(CvBankSearch::class)->occupationOptions(mb_substr((string) $request->query('q', ''), 0, 60), app()->getLocale()));
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function page(array $p, ?int $companyId, string $locale, string $standard): array
    {
        $result = $this->builder->run($p, $companyId, $locale, $standard);
        $search = app(CvBankSearch::class);

        return [
            'params'   => $p,
            'result'   => $result,
            'summary'  => $this->builder->summary($p, $locale, $standard),
            'admin'    => $companyId === null,
            'occupations' => collect($p['f']['occ'])->map(fn ($v) => $search->occupationLabel($v, $locale) ?? ['value' => $v, 'standard' => explode(':', $v)[0], 'code' => explode(':', $v)[1], 'title' => ''])->values(),
            'options'  => [
                'dimensions' => ReportBuilder::DIMENSIONS,
                'measures'   => $companyId === null ? ['count'] : ReportBuilder::MEASURES,
                'occ_levels' => ReportBuilder::OCC_LEVELS,
                'ind_levels' => ReportBuilder::IND_LEVELS,
                'stages'     => ReportBuilder::STAGES,
                'ranges'     => ReportBuilder::RANGES,
                'governorates' => config('beneficiaries.governorates'),
                'education_levels' => config('beneficiaries.education_levels'),
                'sectors'    => Sector::tree(),
            ],
        ];
    }

    private function download(Request $request, ?int $companyId, string $workspace): FileResponse
    {
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        $p = $this->params($request);
        if ($companyId === null) {
            $p['measure'] = 'count';
        }
        $locale = app()->getLocale();
        $standard = $request->user()->occupation_standard ?: 'enoc';
        $result = $this->builder->run($p, $companyId, $locale, $standard);
        $result['rows_title'] = __('reports.dim.'.$p['rows'], [], $locale);
        $meta = [
            'title'     => $request->query('title') ? mb_substr((string) $request->query('title'), 0, 120) : __('reports.title', [], $locale).' · '.__('reports.measure.'.$p['measure'], [], $locale),
            'workspace' => $workspace,
            'by'        => $request->user()->name,
            'locale'    => $locale,
            'summary'   => $this->builder->summary($p, $locale, $standard),
        ];
        $bytes = $format === 'pdf' ? $this->exporter->pdf($result, $meta) : $this->exporter->xlsx($result, $meta);

        ReportDownload::query()->create([
            'company_id' => $companyId, 'user_id' => $request->user()->id, 'user_name' => mb_substr($request->user()->name, 0, 100),
            'format' => $format, 'params' => $p, 'created_at' => now(),
        ]);

        $name = 'massar-report-'.now()->format('Y-m-d-His').'.'.$format;

        return response($bytes, 200, [
            'Content-Type'        => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    private function params(Request $request): array
    {
        $raw = $request->query('r');
        $in = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);

        return ReportBuilder::normalize($in ?? ReportBuilder::defaults());
    }

    private function companyId(Request $request): int
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($companyId, 404);

        return $companyId;
    }

    private function saved(Request $request, int $id): SavedReport
    {
        $s = SavedReport::query()->inWorkspace($this->companyId($request))->whereKey($id)->firstOrFail();
        abort_unless($s->canChange($request->user()), 403, __('reports.not_yours'));

        return $s;
    }
}
