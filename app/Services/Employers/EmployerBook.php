<?php

namespace App\Services\Employers;

use App\Models\Employer;
use App\Models\Sector;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\Cache;

// ══════════════════════════════════════════════════════════════════
//  Massar — EmployerBook (known companies, sectors, countries · Step 10.5)
//  Location: app/Services/Employers/EmployerBook.php
//
//  key($name)       a company name made comparable: Arabic/English
//                   normalised (TextNormalizer) and the legal words
//                   removed — "Vodafone Egypt S.A.E" and "vodafone egypt"
//                   are the same; "شركة فودافون مصر (ش.م.م)" too.
//  match($name)     the known companies a CV's employer line means:
//                     one   exactly one company → its sector is used
//                     many  more than one (e.g. the same name twice) → Check
//                     none  not in the list → the person chooses the sector
//                   A name counts when it is the whole line, or the start
//                   of it followed by more words ("Vodafone Egypt
//                   Telecommunications" → Vodafone Egypt). A one-word
//                   name ("Orange", "Corona", "WE") must be the whole
//                   line: "Corona Hospital" is not Corona.
//  search($q)       the suggestions under the employer box, as you type
//  enrich($jobs)    a CV's jobs: country from the job's place (config/
//                   countries.php), then the company and its sector
//  learn($jobs)     a job saved by staff at a company not in the list,
//                   with a sector chosen: remembered for that workspace
// ══════════════════════════════════════════════════════════════════

class EmployerBook
{
    /** Words that say "a company", not which one. */
    private const LEGAL = ['co', 'company', 'companies', 'corp', 'corporation', 'inc', 'ltd', 'limited', 'llc', 'plc', 'sae', 'jsc',
        'شركه', 'مؤسسه'];

    private const LEGAL_SEQ = ['s a e', 'ش م م', 'ذ م م', 'ش ذ م م', 'ش م', 'w l l'];

    private array $index = [];

    public static function key(?string $name): string
    {
        $n = ' '.TextNormalizer::normalize($name).' ';
        foreach (self::LEGAL_SEQ as $seq) {
            $n = str_replace(' '.$seq.' ', ' ', $n);
        }
        $words = array_filter(explode(' ', $n), fn ($w) => $w !== '' && ! in_array($w, self::LEGAL, true));

        return implode(' ', $words);
    }

    /** Every comparable form of an employer's names, one per line (the "names" column). */
    public static function namesOf(?string $en, ?string $ar, ?string $others): string
    {
        $all = [$en, $ar, ...preg_split('/\s*;\s*/u', (string) $others)];
        // "Commercial International Bank (CIB)" also counts without the brackets.
        foreach ([$en, $ar] as $n) {
            if ($n && str_contains($n, '(')) {
                $all[] = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $n));
            }
        }
        $keys = array_values(array_unique(array_filter(array_map([self::class, 'key'], $all))));

        return implode("\n", $keys);
    }

    /** @return array{status: string, ids: list<int>} */
    public function match(?string $name, ?int $companyId): array
    {
        $k = self::key(preg_replace('/\s*\([^)]*\)\s*$/u', '', (string) $name));
        if ($k === '') {
            return ['status' => 'none', 'ids' => []];
        }
        $index = $this->index($companyId);
        $ids = $index[$k] ?? [];
        if (! $ids) {
            // The longest known name the line starts with.
            $best = '';
            foreach ($index as $known => $x) {
                $known = (string) $known;
                if (strlen($known) > strlen($best) && str_contains($known, ' ') && str_starts_with($k, $known.' ')) {
                    $best = $known;
                }
            }
            $ids = $best !== '' ? $index[$best] : [];
        }
        $ids = array_values(array_unique($ids));

        return ['status' => match (count($ids)) { 0 => 'none', 1 => 'one', default => 'many' }, 'ids' => $ids];
    }

    /** Suggestions for the employer box. @return list<array> */
    public function search(string $q, ?int $companyId, ?string $locale = null, int $limit = 8): array
    {
        $k = self::key($q);
        if (mb_strlen($k) < 2) {
            return [];
        }
        $esc = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $k);
        // The typed letters must start a word of a name ("نيل" finds "النيل للطيران", not "موبينيل").
        $rows = Employer::query()->visibleTo($companyId)
            ->where(fn ($w) => $w->where('names', 'like', $esc.'%')->orWhere('names', 'like', '% '.$esc.'%')->orWhere('names', 'like', "%\n".$esc.'%'))
            ->limit(60)->get();
        $subs = $this->sectorNames();

        return $rows->sortBy(fn (Employer $e) => [
            in_array($k, explode("\n", $e->names), true) ? 0 : (preg_match('/(^|\n)'.preg_quote($k, '/').'/u', $e->names) ? 1 : 2),
            mb_strlen($e->name_en ?? $e->name_ar ?? ''),
        ])->take($limit)->values()->map(fn (Employer $e) => $this->present($e, $subs, $locale))->all();
    }

    public function present(Employer $e, ?array $subs = null, ?string $locale = null): array
    {
        $subs ??= $this->sectorNames();

        return [
            'id'         => $e->id,
            'name_en'    => $e->name_en,
            'name_ar'    => $e->name_ar,
            'name'       => $e->displayName($locale),
            'sub_sector' => $e->sub_sector,
            'sector'     => $e->sub_sector ? ($subs[$e->sub_sector]['parent'] ?? null) : null,
            'country'    => $e->country,
            'own'        => $e->company_id !== null,      // learned by this workspace
        ];
    }

    /**
     * A CV's jobs: the country from the place, then the company and its sector.
     * Adds to a job: country, and (one match) employer_id + sub_sector; (many) check "employer".
     */
    public function enrich(array $jobs, ?int $companyId): array
    {
        foreach ($jobs as &$job) {
            if (! is_array($job)) {
                continue;
            }
            $job['country'] = self::countryOf($job['location'] ?? null) ?? (empty($job['location']) ? 'EG' : null);
            if (($job['country'] ?? null) === 'EG' && ($gov = self::governorateOf($job['location'] ?? null))) {
                $job['governorate'] = $gov;
            }
            if (($job['country'] ?? null) !== 'EG' || empty($job['employer'])) {
                continue;
            }
            $m = $this->match($job['employer'], $companyId);
            if ($m['status'] === 'one' && ($e = Employer::query()->find($m['ids'][0]))) {
                $job['employer_id'] = $e->id;
                $job['sub_sector'] = $e->sub_sector;
            } elseif ($m['status'] === 'many') {
                $job['check'] = array_values(array_unique([...($job['check'] ?? []), 'employer']));
            }
        }

        return $jobs;
    }

    /** The governorate an Egyptian place means ("Nasr City, Cairo" → cai), or null. */
    public static function governorateOf(?string $place): ?string
    {
        $p = ' '.preg_replace('/\s+/u', ' ', preg_replace('/[,،\/\-|()]+/u', ' ', mb_strtolower(trim((string) $place)))).' ';
        if (trim($p) === '') {
            return null;
        }
        $found = null;
        $len = 0;
        foreach (\App\Services\Cv\CvDictionary::PLACES as $gov => $words) {
            foreach ($words as $w) {
                if (str_contains($p, ' '.$w.' ') && mb_strlen($w) > $len) {
                    [$found, $len] = [$gov, mb_strlen($w)];
                }
            }
        }

        return $found;
    }

    /** Recently added companies of this workspace (the list opened with nothing typed yet). */
    public function recentOwn(int $companyId, ?string $locale = null, int $limit = 8): array
    {
        $subs = $this->sectorNames();

        return Employer::query()->where('company_id', $companyId)->orderByDesc('id')->limit($limit)->get()
            ->map(fn (Employer $e) => $this->present($e, $subs, $locale))->all();
    }

    /** The country a place means ("Riyadh, KSA" → SA), or null. */
    public static function countryOf(?string $place): ?string
    {
        $p = ' '.mb_strtolower(trim((string) $place)).' ';
        if (trim($p) === '') {
            return null;
        }
        $p = preg_replace('/[,،\/\-|()]+/u', ' ', $p);
        $p = preg_replace('/\s+/u', ' ', $p);
        $found = null;
        $len = 0;
        foreach (config('countries.places', []) as $code => $words) {
            foreach ($words as $w) {
                if (str_contains($p, ' '.$w.' ') && mb_strlen($w) > $len) {
                    [$found, $len] = [$code, mb_strlen($w)];
                }
            }
        }

        return $found;
    }

    /**
     * Staff saved jobs at companies not in the list, with a sector chosen:
     * remember them for this workspace (the next CV is filled automatically).
     */
    public function learn(int $companyId, array $jobs, ?int $userId): void
    {
        foreach ($jobs as $job) {
            $name = trim((string) ($job['employer'] ?? ''));
            if ($name === '' || ! empty($job['employer_id']) || empty($job['sub_sector']) || ($job['country'] ?? 'EG') !== 'EG') {
                continue;
            }
            $k = self::key($name);
            if (mb_strlen($k) < 2 || $this->match($name, $companyId)['status'] !== 'none') {
                continue;
            }
            $arabic = (bool) preg_match('/\p{Arabic}/u', $name);
            Employer::query()->create([
                'company_id' => $companyId, 'name_en' => $arabic ? null : $name, 'name_ar' => $arabic ? $name : null,
                'names' => self::namesOf($name, null, null), 'sub_sector' => $job['sub_sector'], 'country' => 'EG',
                'source' => 'learned', 'created_by' => $userId,
            ]);
            $this->forget($companyId);
        }
    }

    public function forget(?int $companyId = null): void
    {
        $this->index = [];
        Cache::forget('employers.index.'.($companyId ?? 0));
        Cache::forget('employers.index.0');
    }

    /** @return array<string, list<int>> key → employer ids */
    private function index(?int $companyId): array
    {
        $slot = (string) ($companyId ?? 0);
        if (isset($this->index[$slot])) {
            return $this->index[$slot];
        }
        $build = function (?int $cid) {
            $out = [];
            Employer::query()->when($cid, fn ($q) => $q->where('company_id', $cid), fn ($q) => $q->whereNull('company_id'))
                ->get(['id', 'names'])->each(function (Employer $e) use (&$out) {
                    foreach (explode("\n", (string) $e->names) as $k) {
                        if ($k !== '') {
                            $out[$k][] = $e->id;
                        }
                    }
                });

            return $out;
        };
        $shared = Cache::remember('employers.index.0', 600, fn () => $build(null));
        $own = $companyId ? Cache::remember('employers.index.'.$companyId, 600, fn () => $build($companyId)) : [];
        foreach ($own as $k => $ids) {
            $shared[$k] = [...($shared[$k] ?? []), ...$ids];
        }

        return $this->index[$slot] = $shared;
    }

    /** @return array<string, array{parent: ?string, name_en: string, name_ar: string}> */
    public function sectorNames(): array
    {
        return Cache::remember('sectors.names', 600, fn () => Sector::query()->get(['code', 'parent', 'name_en', 'name_ar'])
            ->keyBy('code')->map(fn (Sector $s) => ['parent' => $s->parent, 'name_en' => $s->name_en, 'name_ar' => $s->name_ar])->all());
    }
}
