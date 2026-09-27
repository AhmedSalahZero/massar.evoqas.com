<?php

namespace App\Services\Cv;

use App\Models\Beneficiary;
use App\Models\CvDocument;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvBankIndex (Scope v2 §3 Searchable CV Bank)
//  Location: app/Services/Cv/CvBankIndex.php
//
//  Keeps the search index (table cv_bank_index) of one profile up to
//  date: every word of the profile and of every original CV attached
//  to it, normalised the same way as the search words (so أ/ا, ة/ه,
//  ى/ي and "ال" never stop a match).
//
//  Called automatically when a profile is saved (Beneficiary::saved)
//  and when a CV is added to a profile (CvDocument::saved); and for
//  every profile by `php artisan cvbank:index`.
// ══════════════════════════════════════════════════════════════════

class CvBankIndex
{
    /** Longest text kept per profile (the CVs of one person are far below this). */
    private const MAX = 2_000_000;

    private static ?bool $ready = null;

    public function refresh(Beneficiary $b): void
    {
        if (! self::ready() || ! $b->exists) {
            return;
        }
        $docs = CvDocument::query()->withoutGlobalScopes()->where('company_id', $b->company_id)->where('beneficiary_id', $b->id)
            ->whereIn('status', CvDocument::ON_PROFILE)->get(['id', 'text', 'language']);

        $parts = [$b->name_ar, $b->name_en, $b->email, $b->phone, $b->city];
        foreach ($b->work_history ?? [] as $job) {
            $parts[] = $job['title'] ?? null;
            $parts[] = $job['employer'] ?? null;
            $parts[] = $job['location'] ?? null;
            foreach ($job['responsibilities'] ?? [] as $d) {
                $parts[] = $d;
            }
        }
        foreach ($b->education ?? [] as $e) {
            $parts[] = $e['qualification'] ?? null;
            $parts[] = $e['field'] ?? null;
            $parts[] = $e['institution'] ?? null;
        }
        foreach ($b->skills ?? [] as $s) {
            $parts[] = $s;
        }
        foreach ($docs as $d) {
            $parts[] = $d->text;
        }
        $body = ' '.mb_substr(TextNormalizer::normalize(implode(' | ', array_filter($parts, fn ($p) => is_string($p) && $p !== ''))), 0, self::MAX).' ';
        $languages = $docs->pluck('language')->filter()->unique()->sort()->implode(',');

        DB::table('cv_bank_index')->upsert([[
            'beneficiary_id' => $b->id, 'company_id' => $b->company_id, 'body' => $body,
            'cv_languages' => $languages ?: null, 'cv_count' => $docs->count(), 'updated_at' => now(),
        ]], ['beneficiary_id'], ['company_id', 'body', 'cv_languages', 'cv_count', 'updated_at']);
    }

    /** Every profile (of one workspace, or all). Returns how many. */
    public function refreshAll(?int $companyId = null, ?callable $progress = null): int
    {
        $n = 0;
        Beneficiary::query()->withoutGlobalScopes()->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('id')->chunkById(200, function ($chunk) use (&$n, $progress) {
                foreach ($chunk as $b) {
                    $this->refresh($b);
                    $n++;
                }
                if ($progress) {
                    $progress($n);
                }
            });

        return $n;
    }

    /** The table exists (before `php artisan migrate` nothing is indexed, nothing breaks). */
    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('cv_bank_index');
    }

    /** For tests that create the table after this was asked. */
    public static function forget(): void
    {
        self::$ready = null;
    }
}
