<?php

namespace App\Services\Backbone;

use App\Support\TextNormalizer;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

// ══════════════════════════════════════════════════════════════════
//  Massar — MarketFile (reads the Egypt Occupational Outlook figures)
//  Location: app/Services/Backbone/MarketFile.php
//
//  Reads the labour market columns of the Egypt Occupational Outlook
//  file into one plain array per ENOC code. No database work here.
//
//  SAFETY
//  · Every column is checked against its expected Arabic header
//    before anything is read. A newer file with a different layout
//    stops with "column N should be …", instead of loading the wrong
//    figures into the wrong fields.
//  · Category texts ("نمو أسرع بكثير من المتوسط" …) are turned into
//    short keys (much_faster …). A wording the app does not know yet
//    stops the import and names it, so nothing is silently dropped.
//  · Figures are taken as published. Nothing is "corrected". Values
//    that cannot be right (a percentage of 976, sectors adding up to
//    128%, 408 hours a week) are kept and FLAGGED, so they can be reviewed and hidden
//    from partners.
//  · "." and empty cells mean "no data" → null.
// ══════════════════════════════════════════════════════════════════

class MarketFile
{
    /** Column index => [field, words its header must contain]. */
    public const COLUMNS = [
        0  => ['code', 'الكود'],
        3  => ['workers', 'عدد المشتغلين'],
        4  => ['workers_trend', 'معدل نمو عدد المشتغلين'],
        5  => ['share_of_employment', 'من إجمالي المشتغلين'],
        6  => ['pct_paid', 'المشتغلين بأجر'],
        7  => ['pct_unpaid', 'بدون أجر'],
        8  => ['pct_formal', 'بصفة رسمية'],
        9  => ['pct_regular', 'بصفة منتظمة'],
        10 => ['pct_women', 'الإناث'],
        11 => ['pct_public', 'القطاع العام'],
        12 => ['pct_private', 'القطاع الخاص'],
        13 => ['sector.agriculture', 'الزراعة'],
        14 => ['sector.manufacturing', 'الصناعات التحويلية'],
        15 => ['sector.construction', 'التشييد'],
        16 => ['sector.trade', 'تجارة الجملة'],
        17 => ['sector.transport', 'النقل والتخزين'],
        18 => ['sector.hospitality', 'الغذاء والإقامة'],
        19 => ['sector.ict', 'المعلومات والاتصالات'],
        20 => ['sector.finance', 'الوساطة المالية'],
        21 => ['sector.real_estate', 'العقارات'],
        22 => ['sector.public_services', 'الإدارة العامة والتعليم والصحة'],
        23 => ['sector.other_services', 'الخدمات الأخرى'],
        24 => ['region.cairo', 'القاهرة الكبرى'],
        25 => ['region.alexandria', 'الأسكندرية'],
        26 => ['region.delta', 'وجه بحري'],
        27 => ['region.canal', 'مدن القناة'],
        28 => ['region.north_upper', 'شمال الصعيد'],
        29 => ['region.middle_upper', 'وسط الصعيد'],
        30 => ['region.south_upper', 'جنوب الصعيد'],
        31 => ['wage_avg', 'متوسط الأجر الشهري'],
        32 => ['wage_male', 'للذكور'],
        33 => ['wage_female', 'للإناث'],
        34 => ['weekly_hours', 'ساعات العمل'],
        35 => ['green', 'الانتقال الأخضر'],
        36 => ['education', 'مستوى التعليم'],
        37 => ['knowledge', 'مجالات للمعرفة'],
        38 => ['abilities', 'قدرات'],
        39 => ['skills', 'مهارات'],
        40 => ['outlook_trend', 'معدل نمو التشغيل المتوقع'],
        41 => ['outlook_jobs', 'الوظائف المتوقعة'],
        42 => ['check.wage_avg', 'متوسط الأجر الشهري'],
        43 => ['check.wage_male', 'للذكور'],
        44 => ['check.wage_female', 'للإناث'],
        45 => ['wage_public', 'بالقطاع العام'],
        46 => ['wage_private', 'بالقطاع الخاص'],
        47 => ['skill.technical', 'المهارات الفنية'],
        48 => ['skill.literacy', 'القراءة والكتابة'],
        49 => ['skill.numeracy', 'الحساب'],
        50 => ['skill.computer', 'الكومبيوتر'],
        51 => ['skill.language', 'اللغة'],
        52 => ['skill.problem_solving', 'حل المشكلات'],
        53 => ['skill.communication', 'التواصل'],
        54 => ['skill.teamwork', 'العمل الجماعي'],
        55 => ['skill.customer', 'التعامل مع العملاء'],
        56 => ['skill.physical', 'بدنية'],
    ];

    /** Category wording in the file → key. Matched after TextNormalizer. */
    public const CATEGORIES = [
        'trend' => [
            'نمو أسرع بكثير من المتوسط'     => 'much_faster',
            'نمو أسرع إلى حد ما من المتوسط' => 'faster',
            'نمو أبطأ إلى حد ما من المتوسط' => 'slower',
            'نمو أبطأ بكثير من المتوسط'     => 'much_slower',
            'انكماش'                        => 'decline',
        ],
        'region' => [
            'أكثر بكثير من المتوسط'     => 'much_above',
            'أكثر إلى حد ما من المتوسط' => 'above',
            'متوسط'                     => 'average',
            'أقل إلى حد ما من المتوسط'  => 'below',
            'أقل بكثير من المتوسط'      => 'much_below',
        ],
        'green' => [
            'شديدة الصلة بالانتقال الأخضر'              => 'very_high',
            'عالية الصلة بالانتقال الأخضر'              => 'high',
            'متوسطة الصلة بالانتقال الأخضر'             => 'medium',
            'منخفضة الصلة بالانتقال الأخضر'             => 'low',
            'لا توجد صلة ولكن ستنمو مع الانتقال الأخضر' => 'none_growing',
            'لا توجد صلة بالانتقال الأخضر'              => 'none',
        ],
        'education' => [
            'شهادة أقل من الثانوية' => 'below_secondary',
            'شهادة ثانوية'          => 'secondary',
            'تعليم عالي'            => 'higher',
        ],
        'jobs' => [
            'إضافة أكثر من ٥٠ ألف وظيفة في السنة'       => 'add_50k_plus',
            'إضافة من ١٠ ألف إلى٥٠ ألف وظيفة في السنة' => 'add_10k_50k',
            'إضافة أقل من ١٠ ألف وظيفة في السنة'        => 'add_under_10k',
            'تناقص أقل من ١٠ ألف وظيفة في السنة'        => 'lose_under_10k',
            'تناقص ١٠ ألف وظيفة فأكثر في السنة'         => 'lose_10k_plus',
        ],
    ];

    /** More hours a week than this cannot be right (7 days × 12 hours). */
    public const MAX_WEEKLY_HOURS = 84;

    private const FIELD_CATEGORY = [
        'workers_trend' => 'trend', 'outlook_trend' => 'trend', 'green' => 'green',
        'education' => 'education', 'outlook_jobs' => 'jobs',
    ];

    public function __construct(private readonly string $path) {}

    public static function fromConfig(): self
    {
        return new self(rtrim(config('backbone.path'), '/\\').DIRECTORY_SEPARATOR.config('backbone.market.file'));
    }

    public function path(): string
    {
        return $this->path;
    }

    public function fileName(): string
    {
        return basename($this->path);
    }

    public function sha256(): string
    {
        if (! is_file($this->path)) {
            throw new RuntimeException('File missing: '.$this->fileName().'. Put it in database/data/backbone and run the import again.');
        }

        return hash_file('sha256', $this->path);
    }

    /**
     * @return array{rows: array<string, array>, quality: array}
     */
    public function read(): array
    {
        $this->sha256();

        $reader = IOFactory::createReaderForFile($this->path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($this->path);
        $table = $book->getSheet(0)->toArray(null, false, false, false);
        $book->disconnectWorksheets();

        $this->checkHeaders($table[1] ?? []);

        $categories = [];
        foreach (self::CATEGORIES as $name => $map) {
            foreach ($map as $text => $key) {
                $categories[$name][TextNormalizer::normalize($text)] = $key;
            }
        }

        $rows = [];
        $unknown = [];
        $wageMismatch = [];
        foreach (array_slice($table, 2) as $r) {
            $code = $this->cell($r[0] ?? null);
            if ($code === null || ! preg_match('/^\d{1,4}$/', (string) $code)) {
                continue;
            }
            $code = str_pad((string) $code, 4, '0', STR_PAD_LEFT);

            $row = ['code' => $code, 'sectors' => [], 'regions' => [], 'skill_groups' => [], 'flags' => []];
            $check = [];
            foreach (self::COLUMNS as $i => [$field]) {
                if ($field === 'code') {
                    continue;
                }
                $raw = $this->cell($r[$i] ?? null);
                [$group, $name] = str_contains($field, '.') ? explode('.', $field, 2) : [null, $field];

                match (true) {
                    $group === 'sector' => $row['sectors'][$name] = $this->number($raw, 1),
                    $group === 'region' => $row['regions'][$name] = $this->category($raw, $categories['region'], $unknown, 'region'),
                    $group === 'skill'  => $row['skill_groups'][$name] = $this->integer($raw),
                    $group === 'check'  => $check[$name] = $this->integer($raw),
                    isset(self::FIELD_CATEGORY[$field]) => $row[$field] = $this->category($raw, $categories[self::FIELD_CATEGORY[$field]], $unknown, $field),
                    in_array($field, ['knowledge', 'abilities', 'skills'], true) => $row[$field] = $this->list($raw),
                    in_array($field, ['workers', 'wage_avg', 'wage_male', 'wage_female', 'wage_public', 'wage_private', 'weekly_hours'], true) => $row[$field] = $this->integer($raw),
                    $field === 'share_of_employment' => $row[$field] = $this->number($raw, 2),
                    default => $row[$field] = $this->number($raw, 1),
                };
            }

            foreach (['sectors', 'regions', 'skill_groups'] as $set) {
                if (! array_filter($row[$set], fn ($v) => $v !== null)) {
                    $row[$set] = null;
                }
            }

            // The file repeats the wages at the end; they must agree.
            foreach (['wage_avg', 'wage_male', 'wage_female'] as $w) {
                if ($row[$w] !== null && ($check[$w] ?? null) !== null && $row[$w] !== $check[$w]) {
                    $wageMismatch[] = $code;
                }
                $row[$w] ??= $check[$w] ?? null;
            }

            $row['flags'] = $this->flags($row);
            $rows[$code] = $row;
        }

        if ($unknown) {
            $list = array_map(fn ($u) => "\"{$u[1]}\" ({$u[0]})", array_slice(array_values($unknown), 0, 10));
            throw new RuntimeException('The file uses wording the app does not know yet: '.implode(', ', $list).'. Nothing was imported.');
        }

        ksort($rows, SORT_STRING);

        return ['rows' => $rows, 'quality' => $this->quality($rows, array_values(array_unique($wageMismatch)))];
    }

    // ── Checks ───────────────────────────────────────────────────────

    private function checkHeaders(array $header): void
    {
        foreach (self::COLUMNS as $i => [$field, $words]) {
            $have = TextNormalizer::normalize((string) ($header[$i] ?? ''));
            if (! str_contains($have, TextNormalizer::normalize($words))) {
                throw new RuntimeException(sprintf(
                    'The Egypt Occupational Outlook file has a different layout: column %d should be about "%s" but is "%s". Nothing was imported.',
                    $i + 1, $words, trim((string) ($header[$i] ?? '')) ?: 'empty',
                ));
            }
        }
    }

    /** Figures that cannot be right as published. */
    private function flags(array $row): array
    {
        $flags = [];
        $pcts = ['pct_paid', 'pct_unpaid', 'pct_formal', 'pct_regular', 'pct_women', 'pct_public', 'pct_private'];
        foreach ($pcts as $p) {
            if ($row[$p] !== null && $row[$p] > 100) {
                $flags[] = 'employment';
                break;
            }
        }
        if ($row['pct_public'] !== null && $row['pct_private'] !== null && abs($row['pct_public'] + $row['pct_private'] - 100) > 1.5) {
            $flags[] = 'public_private';
        }
        // A working week cannot be longer than this. In the April 2025
        // file a few occupations show 408, 403, 470 … (the decimal point
        // lost: 40.8, 40.3, 47.0). Kept as published and flagged.
        if ($row['weekly_hours'] !== null && $row['weekly_hours'] > self::MAX_WEEKLY_HOURS) {
            $flags[] = 'hours';
        }
        if ($row['sectors']) {
            $sum = array_sum(array_filter($row['sectors'], fn ($v) => $v !== null));
            $max = max(array_map(fn ($v) => $v ?? 0, $row['sectors']));
            if ($max > 100 || abs($sum - 100) > 5) {
                $flags[] = 'sectors';
            }
        }

        return array_values(array_unique($flags));
    }

    private function quality(array $rows, array $wageMismatch): array
    {
        // In the April 2025 file the six regions outside Greater Cairo
        // carry exactly the same value for every occupation.
        $withRegions = array_filter($rows, fn ($r) => $r['regions']);
        $identical = array_filter($withRegions, function ($r) {
            $others = $r['regions'];
            unset($others['cairo']);

            return count(array_unique($others)) === 1;
        });

        $flagged = [];
        foreach ($rows as $code => $r) {
            foreach ($r['flags'] as $f) {
                $flagged[$f][] = (string) $code;
            }
        }

        return [
            'regions_outside_cairo_identical' => $withRegions && count($identical) === count($withRegions),
            'regions_rows'                    => count($withRegions),
            'wage_copies_disagree'            => $wageMismatch,
            'flagged'                         => $flagged,
        ];
    }

    // ── Cell helpers ─────────────────────────────────────────────────

    private function cell(mixed $v): mixed
    {
        if (is_string($v)) {
            $v = trim($v);

            return ($v === '' || $v === '.' || $v === '-') ? null : $v;
        }

        return $v;
    }

    private function number(mixed $v, int $decimals): ?float
    {
        if ($v === null) {
            return null;
        }
        $v = strtr((string) $v, ['٫' => '.', ',' => '']);

        return is_numeric($v) ? round((float) $v, $decimals) : null;
    }

    private function integer(mixed $v): ?int
    {
        $n = $this->number($v, 0);

        return $n === null ? null : (int) $n;
    }

    private function category(mixed $v, array $map, array &$unknown, string $field): ?string
    {
        if ($v === null) {
            return null;
        }
        $key = TextNormalizer::normalize((string) $v);
        if (! isset($map[$key])) {
            $unknown[$field.'|'.$key] = [$field, trim((string) $v)];

            return null;
        }

        return $map[$key];
    }

    /** 'التفكير النقدي, التحدث, …' → ['التفكير النقدي', 'التحدث', …] */
    private function list(mixed $v): ?array
    {
        if ($v === null) {
            return null;
        }
        $items = array_map(fn ($s) => preg_replace('/\s+/u', ' ', trim($s)), preg_split('/[,،]/u', (string) $v));
        $items = array_values(array_unique(array_filter($items, fn ($s) => $s !== '')));

        return $items ?: null;
    }
}
