<?php

namespace App\Http\Controllers;

use App\Services\Employers\EmployerBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════════════
//  Massar — the suggestions under a job's employer box (Step 10.5)
//  Location: app/Http/Controllers/EmployerSearchController.php
//  Routes:  GET /app/employer-search?q=…   (staff: the Massar list + their workspace's own)
//           GET /employer-search?q=…       (public site: the Massar list only)
//  ?match=1  instead: the ONE company this exact name means (after ⇄
//            moved a name into the employer box), or null
//  nothing typed (staff): the companies this workspace added lately
// ══════════════════════════════════════════════════════════════════

class EmployerSearchController extends Controller
{
    public function __invoke(Request $request, EmployerBook $book): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $companyId = $request->routeIs('app.*') ? $request->user()?->company_id : null;

        if ($request->boolean('match')) {
            $m = $book->match($q, $companyId);
            $e = $m['status'] === 'one' ? \App\Models\Employer::query()->find($m['ids'][0]) : null;

            return response()->json(['match' => $e ? $book->present($e, null, app()->getLocale()) : null]);
        }
        if ($q === '' && $companyId) {
            return response()->json(['results' => $book->recentOwn((int) $companyId, app()->getLocale()), 'recent' => true]);
        }

        return response()->json(['results' => $book->search($q, $companyId, app()->getLocale())]);
    }
}
