<?php

namespace Tests\Feature;

use App\Services\Cv\CvReader;
use App\Services\Cv\TextExtractor;
use Tests\Concerns\MakesCvFiles;
use Tests\Concerns\ReadsHeadingReferences;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvHeadingsTest (Step 6.5 · section headings & job responsibilities)
//  Location: tests/Feature/CvHeadingsTest.php
//
//    • EVERY heading in the two reference files (docs/cv-headings/) is
//      recognised — and each goes to its section
//    • headings are recognised however they are written: capitals,
//      plural, numbered, decorated, spaced-out letters, bilingual,
//      combined, a Word tab in front
//    • a heading word used as a list item stays content
//      ("Communication Skills" under Skills, "• Research")
//    • job responsibilities: under "Key Responsibilities:", plain lines
//      under a job, lines a PDF broke in two, a job with no dates,
//      "Achievements:" inside a job vs. after the work experience
//    • tables: a column of labels and a column of values
//    • dates: "15‐8‐2017", "June to September 2015", "17-7 to 16-8-2017",
//      "Jan 2008 - Till April 2012", a single year at the start of a job
// ══════════════════════════════════════════════════════════════════

class CvHeadingsTest extends TestCase
{
    use MakesCvFiles;
    use ReadsHeadingReferences;

    private function reader(): CvReader
    {
        return app(CvReader::class);
    }

    /** Read a Word file made from these lines ('#' = bold). */
    private function readDocx(array $lines): array
    {
        $x = app(TextExtractor::class)->extract($this->docx($lines)->getRealPath(), 'docx');

        return $this->reader()->read($x['text'], $x['arabic_ligatures']);
    }

    // ── The reference files ─────────────────────────────────────────

    public function test_every_heading_in_the_reference_files_is_recognised(): void
    {
        $headings = $this->referenceHeadings();
        $this->assertGreaterThan(2000, count($headings), 'The reference files are missing from docs/cv-headings/.');

        $missing = [];
        foreach ($headings as $h) {
            if ($this->reader()->recognise($h['heading']) === null) {
                $missing[] = $h['heading'].'  ('.$h['from'].')';
            }
        }
        $this->assertSame([], $missing, 'Headings from the reference files that are not recognised.');

        foreach ($this->referenceWritingExamples() as $example) {
            $this->assertNotNull($this->reader()->recognise($example), "Not recognised: {$example}");
        }
    }

    public function test_reference_headings_go_to_their_section(): void
    {
        $r = $this->reader();
        foreach ([
            'Work Experience' => 'experience', 'Employment History' => 'experience', 'Career Path' => 'experience',
            'الخبرات العملية' => 'experience', 'Professional Experience | الخبرات العملية' => 'experience',
            'Internships' => 'internships', 'Summer Training' => 'internships', 'التدريب الصيفي' => 'internships',
            'Educational Background' => 'education', 'المؤهلات الدراسية' => 'education', 'Academic Qualifications' => 'education',
            'Core Competencies' => 'skills', 'Computer Skills' => 'skills', 'المهارات الفنية' => 'skills',
            'Linguistic Skills' => 'languages', 'اللغات' => 'languages',
            'Certifications & Licenses' => 'courses', 'Workshops' => 'courses', 'الدورات التدريبية' => 'courses',
            'Personal Details' => 'personal', 'البيانات الشخصية' => 'personal', 'Military Status | موقف التجنيد' => 'military',
            'Volunteer Work' => 'volunteering', 'Hobbies & Interests' => 'interests', 'References Available Upon Request' => 'references',
            'Salary Expectations' => 'salary', 'Notice Period' => 'availability', 'Professional Memberships' => 'memberships',
            'Key Responsibilities' => 'responsibilities', 'Duties and Responsibilities' => 'responsibilities',
            'Job Description | الوصف الوظيفي' => 'responsibilities', 'المهام الوظيفية' => 'responsibilities',
        ] as $heading => $section) {
            $this->assertSame($section, $r->recognise($heading), $heading);
        }
    }

    public function test_headings_are_recognised_however_they_are_written(): void
    {
        $r = $this->reader();
        foreach ([
            'WORK EXPERIENCE:' => 'experience', 'Work Experiences' => 'experience', '1. Education' => 'education',
            '03 | Skills' => 'skills', 'Section 1: Profile' => 'summary', '★ SKILLS ★' => 'skills', '** Objective **' => 'objective',
            '— EXPERIENCE —' => 'experience', 'Education :‐' => 'education', 'Career Objectives' => 'objective',
            'S ki ll s' => 'skills', 'P erson al I n forma ti on' => 'personal', 'Cou rses & Trai n i n gs' => 'courses',
            'Honours & Awards' => 'achievements', 'Licences' => 'courses', 'Courses&Sessions:' => 'courses',
            'Professional Achievements and Awards:' => 'achievements', 'Computer and Linguistic Abilities:' => 'skills_languages',
            'Skills | المهارات' => 'skills', 'Education - المؤهلات الدراسية' => 'education', 'EXTRACIRCULAR ACTIVITIES:' => 'volunteering',
        ] as $line => $section) {
            $this->assertSame($section, $r->recognise($line), $line);
        }
        foreach (['Ratio analysis for liquidity, profitability, activity and leverage Ratios.', 'Prepared monthly reports', 'Cairo University'] as $line) {
            $this->assertNull($r->recognise($line), $line);
        }
    }

    public function test_a_tabbed_word_heading_and_bold_headings_are_read(): void
    {
        // A Word tab before the heading used to come out as the two characters "\t".
        $path = tempnam(sys_get_temp_dir(), 'cv');
        $p = fn ($t, $bold = false) => '<w:p><w:r>'.($bold ? '<w:rPr><w:b/></w:rPr>' : '').'<w:tab/><w:t xml:space="preserve">'.$t.'</w:t></w:r></w:p>';
        $body = $p('Nour Adel').$p('nour@example.com').$p('Work Experience', true).$p('Sales Advisor at Unilever | Mar 2019 – Jan 2021')
            .$p('Language Skills', true).$p('English: Fluent');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();

        $x = app(TextExtractor::class)->extract($path, 'docx');
        $this->assertStringNotContainsString('\t', $x['text']);
        $r = $this->reader()->read($x['text']);
        $this->assertSame([], $r['marks_on_text']['unknown']);
        $this->assertSame('Unilever', $r['form']['work_history'][0]['employer']);
        $this->assertSame([['code' => 'en', 'level' => 'fluent']], $r['form']['languages']);
    }

    public function test_a_heading_word_inside_a_list_stays_content(): void
    {
        $r = $this->reader()->read("Omar Ali\nomar@example.com\n\nSkills\nCommunication Skills\n• Research\nNegotiation Skills\n\nLanguages\nArabic: Native");
        $this->assertSame(['Communication Skills', 'Research', 'Negotiation Skills'], $r['form']['skills']);
        $this->assertSame([['code' => 'ar', 'level' => 'native']], $r['form']['languages']);
    }

    // ── Job responsibilities ────────────────────────────────────────

    public function test_responsibilities_are_read_under_each_job(): void
    {
        $r = $this->readDocx([
            'Rania Ahmed', 'rania@example.com',
            '#Professional Experience:',
            '#Current Job:',
            'Budgeting and Financial Reporting Team Leader – Universal for Metal and Supporting Industries',
            'Since 26/2/2017',
            'Web site: www.universalgroup.org',
            '#Key Responsibilities:',
            'Preparing the master budget.',
            'Monitor all CAPEX activities according to its budget',
            '#Historical Experience:',
            'Financial Reporting Manager – Easy Group',
            'November 2014 – February 2017',
            'Easy Group is one of the leading companies in the field of FMCG.',
            '#Key Responsibilities:',
            'Analyze any deviation between budget and actual results',
            'Approving promotions for all sales channels',
            '#Education & Qualifications:',
            'Bachelor of Commerce, Alexandria University, 2004',
        ]);
        $jobs = $r['form']['work_history'];
        $this->assertCount(2, $jobs);
        $this->assertSame('found', $r['marks']['work_history']);
        $this->assertSame('found', $r['marks']['responsibilities']);
        $this->assertSame(['title' => 'Budgeting and Financial Reporting Team Leader', 'employer' => 'Universal for Metal and Supporting Industries',
            'location' => null, 'from' => '2017-02', 'to' => null, 'current' => true,
            'responsibilities' => ['Preparing the master budget', 'Monitor all CAPEX activities according to its budget'], 'check' => []], $jobs[0]);
        // The line about the company, above "Key Responsibilities:", is not a duty.
        $this->assertSame(['Analyze any deviation between budget and actual results', 'Approving promotions for all sales channels'], $jobs[1]['responsibilities']);
        $this->assertSame('university', $r['form']['education_level']);
    }

    public function test_pdf_lines_broken_in_two_are_joined_and_a_job_without_dates_is_kept(): void
    {
        $text = "Ahmad Diaa\nahmad@example.com\n\nWork Experiences\n\nAccountant – Rooya Holding Group\n\nJob description:\n"
            ."Compiling and analyzing customer needs, and addressing the customers with the right\nsolutions and offers\n"
            ."Preparing contracts\n\nMarketing Manager at SADA CODE company (September 2014 - July 2015)\n\nJob description :\n"
            ."- Monitoring and analyzing market trends\n- Studying competitors’ products and\nservices\n1\nPage 1 of 2\n\nLanguages\nArabic (Mother tongue)";
        $r = $this->reader()->read($text, false, true);
        $jobs = $r['form']['work_history'];
        $this->assertCount(2, $jobs);
        $this->assertSame('Marketing Manager', $jobs[0]['title']);
        $this->assertSame(['Monitoring and analyzing market trends', 'Studying competitors’ products and services'], $jobs[0]['responsibilities']);
        // The job with no dates is read, and a person adds the dates.
        $this->assertSame('Accountant', $jobs[1]['title']);
        $this->assertNull($jobs[1]['from']);
        $this->assertSame(['Compiling and analyzing customer needs, and addressing the customers with the right solutions and offers', 'Preparing contracts'], $jobs[1]['responsibilities']);
        $this->assertSame('check', $r['marks']['work_history']);
    }

    public function test_achievements_inside_a_job_belong_to_the_job(): void
    {
        $r = $this->reader()->read("Mona Sami\nmona@example.com\n\nWORK EXPERIENCE\nSales Supervisor – Nile Foods | Jan 2020 – Present\nResponsibilities:\n- Leading a team of 8\nAchievements:\n- Raised sales by 20%\n"
            ."Sales Representative – Nile Foods | Mar 2017 – Dec 2019\n- Visiting 20 shops a day\n\nACHIEVEMENTS\nEmployee of the year 2019\n\nSKILLS\nNegotiation");
        $jobs = $r['form']['work_history'];
        $this->assertCount(2, $jobs);
        $this->assertSame(['Leading a team of 8', 'Raised sales by 20%'], $jobs[0]['responsibilities']);
        // After the last job, "ACHIEVEMENTS" is a section of the CV again.
        $this->assertSame(['Visiting 20 shops a day'], $jobs[1]['responsibilities']);
        $this->assertSame(['Negotiation'], $r['form']['skills']);
    }

    public function test_arabic_responsibilities_and_internships(): void
    {
        $r = $this->reader()->read("سارة محمود\nsara@example.com\n\nالخبرات العملية\nمحاسب لدى شركة النيل للتجارة من 03/2019 حتى الآن\nالمهام الوظيفية:\n- إعداد القوائم المالية الشهرية\n- متابعة الحسابات\n\n"
            ."Internships:\n1- Attended an Internship at CI Capital:\n(June 2014 to July 2014) - Customer Service Department\n• Responsible for gathering information for brokers.");
        $jobs = $r['form']['work_history'];
        $this->assertSame(['إعداد القوائم المالية الشهرية', 'متابعة الحسابات'], $jobs[0]['responsibilities']);
        $this->assertSame(['title' => 'Internship', 'employer' => 'CI Capital', 'location' => null, 'from' => '2014-06', 'to' => '2014-07', 'current' => false,
            'responsibilities' => ['Responsible for gathering information for brokers'], 'check' => []], $jobs[1]);
    }

    // ── Tables and dates ────────────────────────────────────────────

    public function test_a_column_of_labels_and_a_column_of_values(): void
    {
        $r = $this->readDocx(['Chirstine Magdy', 'christine@example.com', '01200955264', 'Personal Data', 'Date of birth', 'Nationality', 'Marital Status', 'Gender',
            'September 20th , 1995', 'Egyptian', 'Single', 'Female', 'Education Background', 'Degree', 'Graduation year', 'Bachelor of Commerce, Ain Shams University', '2017.']);
        $this->assertSame('1995-09-20', $r['form']['date_of_birth']);
        $this->assertSame('female', $r['form']['gender']);
        $this->assertSame([], $r['marks_on_text']['unknown']);
        $this->assertSame('university', $r['form']['education_level']);

        // The values further down (a two-column PDF), used only when they prove they fit.
        $r = $this->reader()->read("Tarek Said\ntarek@example.com\n\nPERSONAL INFORMATION:\nGENDER\nDATE OF BIRTH\nNATIONALITY\nMILITARY SERVICE\n\n▪\n▪\n\n*References available upon request\n\nPAGE 3 | Tarek Said - CV\n\nMale\n26th February 1979\nEgyptian\nExempted", false, true);
        $this->assertSame('male', $r['form']['gender']);
        $this->assertSame('1979-02-26', $r['form']['date_of_birth']);
        $this->assertSame('exempted', $r['form']['military_status']);
    }

    public function test_more_ways_of_writing_job_dates(): void
    {
        $r = $this->reader()->read("Engy Medhat\nengy@example.com\n\nWork Experience :‐\n1. Internship at Orascom (From 15‐8‐2017 to 28‐9‐2017)\n2.Internship at Workers Bank (From 17‐7 to 16‐8‐2017)\n"
            ."Intern at Electrolux – From June to September 2015\nInternal Auditor - Egyptian Warehouses Company\nJan 2008 - Till April 2012\n2016| Worked as Digital Ambassador at CIB");
        $jobs = collect($r['form']['work_history'])->keyBy('employer');
        $this->assertSame(['2017-08', '2017-09'], [$jobs['Orascom']['from'], $jobs['Orascom']['to']]);
        $this->assertSame(['2017-07', '2017-08'], [$jobs['Workers Bank']['from'], $jobs['Workers Bank']['to']]);
        $this->assertSame(['2015-06', '2015-09'], [$jobs['Electrolux']['from'], $jobs['Electrolux']['to']]);
        $this->assertSame(['2008-01', '2012-04'], [$jobs['Egyptian Warehouses Company']['from'], $jobs['Egyptian Warehouses Company']['to']]);
        // A single year: read, but a person checks it.
        $this->assertSame('Digital Ambassador', $jobs['CIB']['title']);
        $this->assertSame('check', $r['marks']['work_history']);
    }
}
