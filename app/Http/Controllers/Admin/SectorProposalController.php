<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\Sector;
use App\Models\SectorProposal;
use App\Support\TextNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — sub-sectors proposed through "Other" (Super Admin · Step 10.5)
//  Location: app/Http/Controllers/Admin/SectorProposalController.php
//  Routes (platform.backbone):
//    GET  /admin/sectors                        the official list + the proposals
//    POST /admin/sectors/proposals/{id}/add     add as a NEW official sub-sector
//    POST /admin/sectors/proposals/{id}/merge   it is an EXISTING sub-sector
//    POST /admin/sectors/proposals/{id}/decline
//
//  Adding or merging also puts every job that typed that wording under
//  the official sub-sector (in every workspace), so the reports count
//  them. Only wordings and counts are shown here — never people.
// ══════════════════════════════════════════════════════════════════

class SectorProposalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Sectors/Index', [
            'sectors'   => Sector::tree(),
            'open'      => SectorProposal::query()->where('status', 'open')->orderByDesc('times')->limit(200)->get(),
            'resolved'  => SectorProposal::query()->where('status', '!=', 'open')->latest('updated_at')->limit(30)->get(),
        ]);
    }

    public function add(Request $request, SectorProposal $proposal): RedirectResponse
    {
        $data = $request->validate(['name_en' => ['required', 'string', 'max:120'], 'name_ar' => ['required', 'string', 'max:120']]);
        abort_unless($proposal->status === 'open', 404);

        $code = DB::transaction(function () use ($proposal, $data) {
            $letter = $proposal->sector[0];
            $last = Sector::query()->where('parent', $proposal->sector)->pluck('code')->map(fn ($c) => (int) substr($c, 1))->max() ?? 0;
            $code = $letter.str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT);
            Sector::query()->create(['code' => $code, 'parent' => $proposal->sector, 'name_en' => $data['name_en'], 'name_ar' => $data['name_ar'],
                'sort' => (int) Sector::query()->max('sort') + 1]);
            $this->resolve($proposal, 'added', $code);

            return $code;
        });

        return back()->with('success', __('sectors.added', ['code' => $code]));
    }

    public function merge(Request $request, SectorProposal $proposal): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', Rule::exists('sectors', 'code')->whereNotNull('parent')]]);
        abort_unless($proposal->status === 'open', 404);
        DB::transaction(fn () => $this->resolve($proposal, 'merged', $data['code']));

        return back()->with('success', __('sectors.merged'));
    }

    public function decline(SectorProposal $proposal): RedirectResponse
    {
        $proposal->update(['status' => 'declined']);

        return back()->with('success', __('sectors.declined'));
    }

    /** The proposal is settled; jobs that typed this wording now use the official sub-sector. */
    private function resolve(SectorProposal $p, string $status, string $code): void
    {
        $p->update(['status' => $status, 'code' => $code]);
        Cache::forget('sectors.names');

        Beneficiary::query()->withoutGlobalScopes()->where('work_history', 'like', '%sub_sector_other%')->orderBy('id')
            ->chunkById(200, function ($list) use ($p, $code) {
                foreach ($list as $b) {
                    $changed = false;
                    $jobs = array_map(function ($j) use ($p, $code, &$changed) {
                        if (is_array($j) && ($j['sector'] ?? null) === $p->sector && ! empty($j['sub_sector_other'])
                            && TextNormalizer::normalize($j['sub_sector_other']) === $p->key) {
                            $j['sub_sector'] = $code;
                            $j['sub_sector_other'] = null;
                            $j['sector'] = null;
                            $changed = true;
                        }

                        return $j;
                    }, $b->work_history ?? []);
                    if ($changed) {
                        $b->work_history = $jobs;
                        $b->save();
                    }
                }
            });
    }
}
