<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CvBatch;
use App\Models\CvDocument;
use App\Services\Cv\CvIntake;
use App\Services\Cv\TextExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\CvUploadController (Scope v2 §3 "Bulk CV Upload")
//  Location: app/Http/Controllers/App/CvUploadController.php
//  Permission: cv.upload
//
//  GET  /app/cv-upload                         index  the upload page + recent uploads
//  POST /app/cv-upload/batches                 batch  start an upload of N files (JSON)
//  POST /app/cv-upload/batches/{batch}/files   file   one CV → read at once → its result (JSON)
//
//  The browser sends the files one by one (two at a time), so each
//  CV's result appears on screen as soon as it is read, a slow file
//  never holds up the others, and no background worker has to be
//  running on the computer. (For very large volumes the same CvIntake
//  can later run in the background queue — Scope v2 §7.)
//
//  Each request carries a client_id made by the browser, so two
//  different CVs sent within seconds are never mistaken for a
//  double-click by the duplicate-submission guard.
// ══════════════════════════════════════════════════════════════════

class CvUploadController extends Controller
{
    public function index(Request $request, TextExtractor $extractor): Response
    {
        $companyId = $request->user()->company_id;
        $batches = CvBatch::query()->inWorkspace($companyId)->orderByDesc('id')->limit(8)->get();
        $counts = CvDocument::query()->inWorkspace($companyId)->whereIn('batch_id', $batches->pluck('id'))
            ->selectRaw('batch_id, status, count(*) as n')->groupBy('batch_id', 'status')->get()->groupBy('batch_id');

        return Inertia::render('App/Cv/Upload', [
            'limits' => [
                'max_files'  => (int) config('cv.max_files'),
                'max_kb'     => (int) config('cv.max_kb'),
                'extensions' => config('cv.extensions'),
            ],
            // Is pdftotext installed and found? A "yes" is remembered for 10 minutes;
            // a "no" is not, so the warning goes as soon as the reader is set up.
            'pdf_ready' => Cache::get('cv.pdf_ready') || tap($extractor->pdfReaderReady(), fn ($ready) => $ready && Cache::put('cv.pdf_ready', true, 600)),
            'waiting'   => CvDocument::query()->inWorkspace($companyId)->whereIn('status', CvDocument::OPEN)->count(),
            'batches'   => $batches->map(fn (CvBatch $b) => [
                'id'       => $b->id,
                'by'       => $b->user_name,
                'at'       => $b->created_at?->toIso8601String(),
                'files'    => $b->files_received,
                'counts'   => collect($counts->get($b->id, []))->mapWithKeys(fn ($r) => [$r->status => (int) $r->n]),
            ]),
        ]);
    }

    public function batch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'files'     => ['required', 'integer', 'min:1', 'max:'.config('cv.max_files')],
            'client_id' => ['required', 'string', 'max:64'],
        ]);
        $user = $request->user();

        // The same client_id twice (a resent request) is the same upload.
        $batch = CvBatch::query()->withoutGlobalScopes()
            ->where('company_id', $user->company_id)->where('client_id', $data['client_id'])->first();
        if (! $batch) {
            $batch = new CvBatch;
            $batch->forceFill([
                'company_id' => $user->company_id, 'client_id' => $data['client_id'], 'user_id' => $user->id,
                'user_name' => mb_substr($user->name, 0, 100), 'files_expected' => $data['files'],
            ])->save();
        }

        return response()->json(['batch' => $batch->id]);
    }

    public function file(Request $request, int $batch, CvIntake $intake): JsonResponse
    {
        $user = $request->user();
        $b = CvBatch::query()->inWorkspace($user->company_id)->findOrFail($batch);

        $request->validate([
            'file'      => ['required', 'file', 'max:'.config('cv.max_kb')],
            'client_id' => ['required', 'string', 'max:64'],
        ], [
            'file.max'      => __('cv.too_big', ['mb' => round(config('cv.max_kb') / 1024)]),
            'file.uploaded' => __('cv.upload_failed'),
            'file.required' => __('cv.upload_failed'),
        ]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, config('cv.extensions'), true)) {
            return response()->json(['message' => __('cv.wrong_type'), 'errors' => ['file' => [__('cv.wrong_type')]]], 422);
        }
        if ($b->files_received >= $b->files_expected) {
            return response()->json(['message' => __('cv.batch_full'), 'errors' => ['file' => [__('cv.batch_full')]]], 422);
        }

        $doc = $intake->handle($file, $b, $user);

        return response()->json(['document' => ReviewQueueController::summary($doc->fresh(['beneficiary']))]);
    }
}
