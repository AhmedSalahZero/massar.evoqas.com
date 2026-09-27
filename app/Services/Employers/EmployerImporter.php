<?php

namespace App\Services\Employers;

use App\Models\Employer;
use App\Models\Sector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

// ══════════════════════════════════════════════════════════════════
//  Massar — EmployerImporter (Step 10.5)
//  Location: app/Services/Employers/EmployerImporter.php
//  Run with: php artisan employers:import [file]
//
//  Reads database/data/employers/employers.xlsx (the file Massar gave
//  you: "Massar_Egypt_Employers_Starter_List.xlsx", renamed):
//    Sectors        → the fixed sector / sub-sector list
//    Companies      → the Massar list of companies
//    Add companies  → more companies (the sub-sector chosen from the
//                     drop-down, e.g. "S01 · Service › Banking")
//
//  Safe to run again and again: a company is found by its English name
//  (or its Arabic name when it has no English one) — new ones are added,
//  changed ones updated, nothing is duplicated, nothing is deleted.
//  All in one transaction: if the file has a mistake, nothing changes
//  and the message says which row.
// ══════════════════════════════════════════════════════════════════

class EmployerImporter
{
    private const SECTOR_CODES = ['I' => 'IND', 'T' => 'TRD', 'S' => 'SRV'];

    private const OWNERSHIP = [
        'state-owned' => 'state', 'private' => 'private', 'private (foreign-owned)' => 'foreign', 'partly state-owned' => 'partial',
        'حكومية' => 'state', 'خاصة' => 'private', 'خاصة (أجنبية)' => 'foreign', 'مساهمة حكومية جزئية' => 'partial',
    ];

    public static function defaultPath(): string
    {
        return database_path('data/employers/employers.xlsx');
    }

    /** @return array{sectors: int, added: int, updated: int, skipped: list<string>} */
    public function run(?string $path = null): array
    {
        $path ??= self::defaultPath();
        if (! is_file($path)) {
            throw new RuntimeException("The file was not found: {$path}");
        }
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        $sheet = fn (string $name) => $book->getSheetByName($name);
        if (! $sheet('Sectors') || ! $sheet('Companies')) {
            throw new RuntimeException('The file needs the sheets "Sectors" and "Companies" (as in the Massar employers file).');
        }

        $result = ['sectors' => 0, 'added' => 0, 'updated' => 0, 'skipped' => []];

        DB::transaction(function () use ($sheet, &$result) {
            // ── Sectors ──────────────────────────────────────────────
            $sort = 0;
            foreach ($this->rows($sheet('Sectors')) as $n => $r) {
                $code = strtoupper(trim((string) ($r['sub-sector code'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $parent = self::SECTOR_CODES[$code[0]] ?? null;
                if (! $parent || ! preg_match('/^[ITS]\d{2}$/', $code)) {
                    throw new RuntimeException("Sectors, row {$n}: the code \"{$code}\" should look like I01, T01 or S01.");
                }
                Sector::query()->updateOrCreate(['code' => $parent], [
                    'name_en' => trim((string) $r['sector']), 'name_ar' => trim((string) ($r['القطاع'] ?? $r['sector'])),
                    'sort' => array_search($parent, array_values(self::SECTOR_CODES), true),
                ]);
                Sector::query()->updateOrCreate(['code' => $code], [
                    'parent' => $parent, 'name_en' => trim((string) $r['sub-sector']), 'name_ar' => trim((string) ($r['القطاع الفرعي'] ?? $r['sub-sector'])),
                    'sort' => ++$sort,
                ]);
                $result['sectors']++;
            }
            $known = Sector::query()->whereNotNull('parent')->pluck('code')->all();

            // ── Companies ────────────────────────────────────────────
            $sheets = ['Companies' => $sheet('Companies')];
            if ($sheet('Add companies')) {
                $sheets['Add companies'] = $sheet('Add companies');
            }
            foreach ($sheets as $sheetName => $ws) {
                foreach ($this->rows($ws) as $n => $r) {
                    $en = trim((string) ($r['company name (english)'] ?? ''));
                    $ar = trim((string) ($r['اسم الشركة (عربي)'] ?? ''));
                    if ($en === '' && $ar === '') {
                        continue;
                    }
                    $code = strtoupper(trim((string) ($r['sub-sector code'] ?? '')));
                    if ($code === '' && preg_match('/^\s*([ITS]\d{2})\b/i', (string) ($r['sub-sector'] ?? ''), $m)) {
                        $code = strtoupper($m[1]);
                    }
                    if ($code !== '' && ! in_array($code, $known, true)) {
                        $result['skipped'][] = "{$sheetName}, row {$n} ({$en}{$ar}): unknown sub-sector \"{$code}\"";
                        continue;
                    }
                    $country = strtoupper(trim((string) ($r['country'] ?? 'EG'))) ?: 'EG';
                    $data = [
                        'name_en'     => $en ?: null,
                        'name_ar'     => $ar ?: null,
                        'other_names' => trim((string) ($r['other names (; separated)'] ?? '')) ?: null,
                        'sub_sector'  => $code ?: null,
                        'ownership'   => self::OWNERSHIP[mb_strtolower(trim((string) ($r['ownership'] ?? '')))] ?? null,
                        'country'     => in_array($country, config('countries.codes'), true) ? $country : 'XX',
                        'governorate' => $this->governorate($r['governorate (head office)'] ?? null),
                        'source'      => mb_substr(trim((string) ($r['source'] ?? $r['source (e.g. amcham website)'] ?? 'Massar list')), 0, 120) ?: 'Massar list',
                    ];
                    $data['names'] = EmployerBook::namesOf($data['name_en'], $data['name_ar'], $data['other_names']);

                    $existing = Employer::query()->whereNull('company_id')
                        ->when($en !== '', fn ($q) => $q->whereRaw('lower(name_en) = ?', [mb_strtolower($en)]), fn ($q) => $q->where('name_ar', $ar))
                        ->first();
                    if ($existing) {
                        $existing->fill($data);
                        if ($existing->isDirty()) {
                            $existing->save();
                            $result['updated']++;
                        }
                    } else {
                        Employer::query()->create($data);
                        $result['added']++;
                    }
                }
            }
        });

        Cache::forget('employers.index.0');
        Cache::forget('sectors.names');

        return $result;
    }

    /** The rows of a sheet as [header (lower case) => value], numbered as in Excel. */
    private function rows($ws): array
    {
        $grid = $ws->toArray(null, true, false, false);
        $head = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($grid) ?? []);
        $out = [];
        foreach ($grid as $i => $row) {
            if (! array_filter($row, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }
            $out[$i + 2] = array_combine($head, array_pad(array_slice($row, 0, count($head)), count($head), null));
        }

        return $out;
    }

    private function governorate(mixed $v): ?string
    {
        $v = mb_strtolower(trim((string) $v));
        if ($v === '') {
            return null;
        }
        if (in_array($v, config('beneficiaries.governorates'), true)) {
            return $v;
        }
        $names = ['cairo' => 'cai', 'giza' => 'giz', 'alexandria' => 'alx', 'qalyubia' => 'qal', 'dakahlia' => 'dak', 'sharqia' => 'sha',
            'gharbia' => 'gha', 'monufia' => 'mnf', 'beheira' => 'beh', 'kafr el sheikh' => 'kfs', 'damietta' => 'dam', 'port said' => 'pts',
            'ismailia' => 'ism', 'suez' => 'suz', 'fayoum' => 'fay', 'beni suef' => 'bns', 'minya' => 'min', 'assiut' => 'ast', 'sohag' => 'soh',
            'qena' => 'qen', 'luxor' => 'lux', 'aswan' => 'asw', 'red sea' => 'red', 'new valley' => 'wad', 'matruh' => 'mat',
            'north sinai' => 'nsi', 'south sinai' => 'ssi'];

        return $names[$v] ?? null;
    }
}
