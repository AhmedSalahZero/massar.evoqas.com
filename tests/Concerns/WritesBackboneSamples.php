<?php

namespace Tests\Concerns;

use App\Services\Backbone\MarketFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ══════════════════════════════════════════════════════════════════
//  Massar — WritesBackboneSamples (test helper)
//  Location: tests/Concerns/WritesBackboneSamples.php
//
//  Small sample files laid out exactly like the official ones (ISCO-08
//  EN + AR, Egypt Occupational Outlook with all 57 columns, ESCO EN +
//  AR), written to a temporary folder that config('backbone.path')
//  points to. The real files are never touched by tests.
//
//  Sample occupations: 2411 Accountants (complete figures), 3221
//  Nursing associates (partial figures, a broken sector share, no
//  ESCO), 0110 Armed forces (in ISCO/ESCO, not in ENOC).
// ══════════════════════════════════════════════════════════════════

trait WritesBackboneSamples
{
    protected string $sampleDir;

    protected function setUpSamples(): void
    {
        $this->sampleDir = sys_get_temp_dir().'/massar-backbone-'.uniqid();
        mkdir($this->sampleDir.'/esco', 0777, true);
        config(['backbone.path' => $this->sampleDir]);
        $this->writeSampleFiles();
    }

    protected function tearDownSamples(): void
    {
        foreach (glob($this->sampleDir.'/esco/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->sampleDir.'/*.*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->sampleDir.'/esco');
        @rmdir($this->sampleDir);
    }

    protected function writeSampleFiles(): void
    {
        $en = [['Level', 'ISCO 08 Code', 'Title EN', 'Definition', 'Tasks include', 'Included occupations', 'Excluded occupations', 'Notes']];
        $ar = [['Major Group', 'Sub Major Group', 'Minor Group', 'Unit Group', 'Description', 'الوصف المهني']];
        $groups = [
            ['0', 'Armed forces occupations', 'القوات المسلحة'], ['01', 'Commissioned armed forces officers', 'الضباط'],
            ['011', 'Commissioned armed forces officers', 'الضباط'], ['0110', 'Commissioned armed forces officers', 'الضباط القادة'],
            ['2', 'Professionals', 'الاختصاصيون'], ['24', 'Business and administration professionals', 'اختصاصيو الأعمال'],
            ['241', 'Finance professionals', 'اختصاصيو الشؤون المالية'], ['2411', 'Accountants', 'المحاسبون'],
            ['3', 'Technicians', 'الفنيون'], ['32', 'Health associate professionals', 'مساعدو الاختصاصيين الصحيين'],
            ['322', 'Nursing associate professionals', 'مساعدو التمريض'], ['3221', 'Nursing associate professionals', 'مساعدو التمريض'],
        ];
        foreach ($groups as [$code, $title, $titleAr]) {
            $level = strlen($code);
            // Codes stored as numbers, like Excel does ('0110' → 110).
            $en[] = [$level, (int) $code, $title, "{$title} do things.", null, null, null, null];
            $row = [null, null, null, null, $title, $titleAr];
            $row[$level - 1] = (int) $code;
            $ar[] = $row;
        }
        $this->xlsx(config('backbone.files.isco_en'), [['ISCO-08 EN Struct and defin', $en]]);
        $this->xlsx(config('backbone.files.isco_ar'), [['Information', [['ISCO-08 structure']]], ['ISCO-08 Structure EN AR V.1.0', $ar]]);

        $this->writeOutlook([
            $this->outlookRow('2411', '   محاسبون  ', 'يقوم المحاسبون بمسك الدفاتر.', self::ACCOUNTANT_FIGURES),
            $this->outlookRow('3221', 'مساعدو التمريض', 'وصف', self::NURSE_FIGURES),
        ]);

        $header = ['conceptType', 'conceptUri', 'iscoGroup', 'preferredLabel', 'altLabels', 'hiddenLabels', 'status', 'modifiedDate',
            'regulatedProfessionNote', 'scopeNote', 'definition', 'inScheme', 'description', 'code', 'naceCode'];
        $row = fn ($uri, $isco, $label, $alt, $desc, $code) => ['Occupation', "http://data.europa.eu/esco/occupation/{$uri}", $isco, $label, $alt, '',
            'released', '2024-01-25T11:28:50.295Z', 'http://data.europa.eu/esco/regulated-professions/unregulated', '', '', '', $desc, $code, ''];

        $this->csv(config('backbone.files.esco_en'), [$header,
            $row('a1', '2411', 'accountant', "bookkeeper\nchartered accountant", 'Accountants check financial records.', '2411.1'),
            $row('a2', '2411', 'accounting analyst', '', 'Accounting analysts evaluate statements.', '2411.1.1'),
            $row('a1', '2411', 'accountant', "bookkeeper\nchartered accountant", 'Accountants check financial records.', '2411.1'),
            $row('a3', '0110', 'army officer', '', 'Officers lead units.', '0110.1'),
        ]);
        $this->csv(config('backbone.files.esco_ar'), [$header,
            $row('a1', '2411', 'محاسب / محاسبة', '', 'يراجع المحاسبون السجلات المالية.', '2411.1'),
            $row('a2', '2411', 'محلل المحاسبة / محللة محاسبة', '', 'يقيم محللو المحاسبة القوائم.', '2411.1.1'),
            $row('a3', '0110', 'ضابط جيش / ضابطة جيش', '', 'يقود الضباط الوحدات.', '0110.1'),
        ]);
    }

    /** Columns 3–56 of the Outlook, by field name (see MarketFile::COLUMNS). */
    protected const ACCOUNTANT_FIGURES = [
        'workers' => 569316, 'workers_trend' => 'نمو أسرع إلى حد ما من المتوسط', 'share_of_employment' => 2.1,
        'pct_paid' => 97.2, 'pct_unpaid' => 2.8, 'pct_formal' => 76, 'pct_regular' => 99.7, 'pct_women' => 15.7,
        'pct_public' => 32.5, 'pct_private' => 67.5,
        'sector.agriculture' => 0.9, 'sector.manufacturing' => 16.8, 'sector.construction' => 13.2, 'sector.trade' => 17.2,
        'sector.transport' => 4, 'sector.hospitality' => 2.5, 'sector.ict' => 2.8, 'sector.finance' => 9.9,
        'sector.real_estate' => 14.7, 'sector.public_services' => 15.7, 'sector.other_services' => 1.3,
        'region.cairo' => 'أكثر بكثير من المتوسط', 'region.alexandria' => 'متوسط', 'region.delta' => 'متوسط', 'region.canal' => 'متوسط',
        'region.north_upper' => 'متوسط', 'region.middle_upper' => 'متوسط', 'region.south_upper' => 'متوسط',
        'wage_avg' => 6740, 'wage_male' => 6357, 'wage_female' => 7788, 'weekly_hours' => 46,
        'green' => 'لا توجد صلة بالانتقال الأخضر', 'education' => 'تعليم عالي',
        'knowledge' => 'اقتصاد ومحاسبة, اللغة العربية, الرياضيات', 'abilities' => 'القدرة على الاستنتاج المنطقي', 'skills' => 'فهم  نص مقروء, التفكير النقدي',
        'outlook_trend' => 'نمو أسرع إلى حد ما من المتوسط', 'outlook_jobs' => 'إضافة من ١٠ ألف إلى٥٠ ألف وظيفة في السنة',
        'check.wage_avg' => '6740', 'check.wage_male' => '6357', 'check.wage_female' => '7788', 'wage_public' => '14860', 'wage_private' => '6112',
        'skill.technical' => '62', 'skill.literacy' => '92', 'skill.numeracy' => '91', 'skill.computer' => '77', 'skill.language' => '57',
        'skill.problem_solving' => '70', 'skill.communication' => '81', 'skill.teamwork' => '55', 'skill.customer' => '88', 'skill.physical' => '26',
    ];

    /** Partial figures: no wages for women ('.'), a broken sector share (976 = 97.6), broken hours (408 = 40.8). */
    protected const NURSE_FIGURES = [
        'workers' => 1000, 'weekly_hours' => 408, 'workers_trend' => 'انكماش', 'pct_public' => 90, 'pct_private' => 10,
        'sector.transport' => 976, 'sector.public_services' => 2.4,
        'region.cairo' => 'أقل إلي حد ما من المتوسط', 'region.alexandria' => 'متوسط', 'region.delta' => 'متوسط', 'region.canal' => 'متوسط',
        'region.north_upper' => 'متوسط', 'region.middle_upper' => 'متوسط', 'region.south_upper' => 'متوسط',
        'wage_avg' => 4000, 'check.wage_avg' => '4000', 'check.wage_female' => '.', 'green' => 'منخفضة الصلة بالانتقال الأخضر',
    ];

    protected function outlookRow(string $code, string $title, string $description, array $figures): array
    {
        $row = array_fill(0, 57, null);
        [$row[0], $row[1], $row[2]] = [(int) $code, $title, $description];
        $index = array_flip(array_map(fn ($c) => $c[0], MarketFile::COLUMNS));
        foreach ($figures as $field => $value) {
            $row[$index[$field]] = $value;
        }

        return $row;
    }

    /** Writes the Outlook file: 2 header rows (like the real one) + $rows. */
    protected function writeOutlook(array $rows, array $headerOverrides = []): void
    {
        $header = array_fill(0, 57, null);
        foreach (MarketFile::COLUMNS as $i => [, $words]) {
            $header[$i] = $words;
        }
        $header[0] = "الكود\nENOC 2006";
        $header[1] = "التصنيف المهني - لمهن الحد الرابع\n(ENOC) 2006";
        $header[2] = ' الوصف الوظيفى (ENOC) 2006';
        foreach ($headerOverrides as $i => $text) {
            $header[$i] = $text;
        }

        $this->xlsx(config('backbone.market.file'), [
            ['المؤشرات', [array_fill(0, 57, null), $header, ...$rows]],
            ['المجموعات المهنية', [['المجموعة المهنية', 'الحد الأدنى', 'الحد الأقصى'], ['الأخصائيون', 2000, 2999], ['الفنيون', 3000, 3999]]],
        ]);
    }

    protected function xlsx(string $file, array $sheets): void
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($sheets as [$title, $rows]) {
            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr($title, 0, 31));
            $sheet->fromArray($rows, null, 'A1', true);
        }
        (new Xlsx($book))->save($this->sampleDir.'/'.$file);
    }

    protected function csv(string $file, array $rows): void
    {
        $h = fopen($this->sampleDir.'/'.$file, 'w');
        fwrite($h, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($h, $r, ',', '"', '');
        }
        fclose($h);
    }
}
