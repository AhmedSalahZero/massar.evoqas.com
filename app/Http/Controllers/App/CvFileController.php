<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CvAccessLog;
use App\Models\CvDocument;
use App\Services\Cv\CvStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\CvFileController (the original CV files)
//  Location: app/Http/Controllers/App/CvFileController.php
//  Scope v2 §7 Secure CV File Storage · Personal Data Protection
//  Permission: cv.download ("sensitive" in the Scope)
//
//  GET /app/cv-files/{uuid}           download the original file
//  GET /app/cv-files/{uuid}?view=1    open it in the browser (PDF only)
//
//  Only a CV of the signed-in person's own workspace (another
//  partner's is "not found"). Every download and every opening is
//  written to cv_access_logs: who, when, from which address. The
//  file is decrypted on the way out and never stored unencrypted.
// ══════════════════════════════════════════════════════════════════

class CvFileController extends Controller
{
    public function __invoke(Request $request, string $uuid, CvStorage $storage): Response
    {
        $user = $request->user();
        $doc = CvDocument::query()->inWorkspace($user->company_id)->where('uuid', $uuid)->firstOrFail();
        abort_if($doc->status === CvDocument::REJECTED || ! $doc->stored_path, 404);

        $contents = $storage->get($doc->stored_path);
        abort_if($contents === null, 404);

        $view = $request->boolean('view') && $doc->extension === 'pdf';
        CvAccessLog::query()->create([
            'company_id'     => $doc->company_id,
            'cv_document_id' => $doc->id,
            'user_id'        => $user->id,
            'user_name'      => mb_substr($user->name, 0, 100),
            'action'         => $view ? 'view' : 'download',
            'ip'             => $request->ip(),
        ]);

        return self::send($doc, $contents, $view);
    }

    /** The file as a private download (or shown in the browser, for a PDF). Also used by the Talent Pool and "My profile". */
    public static function send(CvDocument $doc, string $contents, bool $view = false): Response
    {
        $type = match ($doc->extension) {
            'pdf'   => 'application/pdf',
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc'   => 'application/msword',
            default => 'application/octet-stream',
        };
        $disposition = $view && $doc->extension === 'pdf' ? 'inline' : 'attachment';

        return response($contents, 200, [
            'Content-Type'           => $type,
            'Content-Disposition'    => $disposition.'; filename="cv.'.$doc->extension.'"; filename*=UTF-8\'\''.rawurlencode($doc->original_name),
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
