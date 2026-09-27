<?php

namespace Tests\Feature;

use App\Models\Backbone\EscoOccupation;
use App\Models\Beneficiary;
use App\Models\Company;
use App\Models\EligibilityRun;
use App\Models\Opportunity;
use App\Models\ReportDownload;
use App\Models\SavedReport;
use App\Models\Sector;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use App\Services\Beneficiaries\BeneficiaryRecorder;
use App\Support\ArabicShaper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — ReportsTest (Step 14 · Reports)
//  Location: tests/Feature/ReportsTest.php
//  Scope: docs/SCOPE_REPORTS.md (agreed as recommended)
//
//    • the six filters of the Scope, alone and together: occupation (any
//      standard, any level), industry (current or last job), gender, age,
//      experience, expected salary — and governorate, education, stage
//    • counts and averages ("based on N people"; nothing guessed)
//    • split by one or two things; occupation levels and standards;
//      the market wage next to the salary by occupation
//    • the date range: registration, or the placement date with "Placed"
//    • Excel and PDF, in English and Arabic; every download recorded
//    • saved reports for the team; only the maker or an admin changes one
//    • "Open these people" in the CV Bank, and checking them against a job
//    • the Super Admin: every partner, counts only, "fewer than 5", never
//      the public pool
//    • how people joined (source) and their industry are kept up to date
//    • PARTNER SEPARATION and permissions
// ══════════════════════════════════════════════════════════════════

class ReportsTest extends TestCase
{
    use RefreshDatabase;
    use WritesBackboneSamples;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-27 10:00:00');
        $this->setUpSamples();
        BackboneImporter::make()->run();
        MarketImporter::make()->run();
        foreach ([['SRV', null, 'Service', 'خدمي'], ['S01', 'SRV', 'Banking', 'البنوك'], ['S02', 'SRV', 'Food services', 'خدمات الطعام'],
            ['TRD', null, 'Trading', 'تجاري'], ['T01', 'TRD', 'Retail', 'التجزئة']] as $i => [$code, $parent, $en, $ar]) {
            Sector::query()->forceCreate(['code' => $code, 'parent' => $parent, 'name_en' => $en, 'name_ar' => $ar, 'sort' => $i]);
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownSamples();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function later(): void
    {
        Carbon::setTestNow(now()->addSeconds(10));
    }

    private function person(User $worker, array $o = []): Beneficiary
    {
        static $phone = 1002000000;
        $this->actingAs($worker)->post('/app/beneficiaries', array_merge([
            'name_en' => 'Sara Mostafa', 'name_ar' => 'سارة مصطفى', 'gender' => 'female', 'date_of_birth' => '1999-04-12',
            'governorate' => 'cai', 'phone' => '0'.(++$phone), 'education_level' => 'university',
            'esco_occupation_id' => EscoOccupation::where('code', '2411.1')->value('id'), 'confirm_duplicate' => true,
        ], $o))->assertSessionHasNoErrors();
        $this->later();

        return Beneficiary::withoutGlobalScopes()->where('company_id', $worker->company_id)->orderByDesc('id')->first();
    }

    private function job(string $sub, string $from, ?string $to, bool $current = false): array
    {
        return ['title' => 'Clerk', 'employer' => 'X', 'from' => $from, 'to' => $to, 'current' => $current, 'sub_sector' => $sub, 'country' => 'EG'];
    }

    /** Five people: see the table in the comments below. */
    private function people(User $w): array
    {
        return [
            // #1 woman 27, Cairo, accountant, 3 years in Banking (current), 8,000
            'sara' => $this->person($w, ['work_history' => [$this->job('S01', '2023-09', null, true)], 'expected_salary' => '8000']),
            // #2 woman 24, Giza, accountant, last job Retail (Banking before), 6,000
            'mona' => $this->person($w, ['name_en' => 'Mona Adel', 'date_of_birth' => '2002-01-10', 'governorate' => 'giz', 'expected_salary' => '6000',
                'work_history' => [$this->job('S01', '2021-01', '2022-01'), $this->job('T01', '2022-02', '2024-02')]]),
            // #3 man 40, Cairo, accounting analyst (ESCO 2411.1.1), no work, no salary
            'omar' => $this->person($w, ['name_en' => 'Omar Ali', 'gender' => 'male', 'military_status' => 'completed', 'date_of_birth' => '1986-05-05',
                'esco_occupation_id' => EscoOccupation::where('code', '2411.1.1')->value('id')]),
            // #4 woman, no date of birth, nurse (3221), Food services, 4,500
            'hoda' => $this->person($w, ['name_en' => 'Hoda Samir', 'date_of_birth' => null, 'esco_occupation_id' => null, 'occupation_unit' => '3221',
                'expected_salary' => '4500', 'education_level' => 'secondary_technical', 'work_history' => [$this->job('S02', '2020-01', '2020-12')]]),
            // #5 man 30, Alexandria, no occupation yet
            'ali' => $this->person($w, ['name_en' => 'Ali Hassan', 'gender' => 'male', 'military_status' => 'completed', 'date_of_birth' => '1996-01-01',
                'governorate' => 'alx', 'esco_occupation_id' => null]),
        ];
    }

    private function props($response): array
    {
        $out = [];
        $response->assertInertia(function (Assert $p) use (&$out) {
            $out = $p->toArray()['props'];
        });

        return $out;
    }

    private function report(User $u, array $q, string $area = 'app')
    {
        return $this->actingAs($u)->get("/$area/reports?r=".urlencode(json_encode($q)));
    }

    private function total(User $u, array $filters, array $more = []): ?int
    {
        $total = null;
        $this->report($u, ['f' => $filters] + $more)->assertOk()->assertInertia(function (Assert $p) use (&$total) {
            $total = $p->toArray()['props']['result']['total']['v'];
        });

        return $total;
    }

    // ── Filters ──────────────────────────────────────────────────────

    public function test_the_six_filters_alone_and_together(): void
    {
        $w = User::factory()->employee()->create();
        $this->people($w);

        $this->actingAs($w)->get('/app/reports')->assertInertia(fn (Assert $p) => $p->component('App/Reports/Index')
            ->where('result.total.v', 5)->where('params.rows', 'occupation')->has('options.sectors'));

        // Occupation: any standard, any level; several = any of them.
        $this->assertSame(3, $this->total($w, ['occ' => ['isco:24']]));
        $this->assertSame(3, $this->total($w, ['occ' => ['enoc:2411']]));
        $this->assertSame(3, $this->total($w, ['occ' => ['esco:2411.1']]));        // the analyst is inside 2411.1
        $this->assertSame(1, $this->total($w, ['occ' => ['esco:2411.1.1']]));
        $this->assertSame(4, $this->total($w, ['occ' => ['isco:2411', 'isco:3']]));
        // Industry: the current or last job only (Mona's Banking job is an old one).
        $this->assertSame(1, $this->total($w, ['industry' => ['S01']]));
        $this->assertSame(2, $this->total($w, ['industry' => ['SRV']]));
        $this->assertSame(1, $this->total($w, ['industry' => ['TRD']]));
        // Gender, age (nobody without a date of birth), experience, salary.
        $this->assertSame(3, $this->total($w, ['gender' => 'female']));
        $this->assertSame(3, $this->total($w, ['age' => [24, 30]]));                // Mona 24, Sara 27, Ali 30
        $this->assertSame(2, $this->total($w, ['age' => [24, 29]]));
        $this->assertSame(1, $this->total($w, ['age' => [35, null]]));
        $this->assertSame(2, $this->total($w, ['exp' => [2, null]]));              // Sara 3y, Mona 3y
        $this->assertSame(2, $this->total($w, ['salary' => [5000, 9000]]));
        $this->assertSame(1, $this->total($w, ['salary' => [null, 5000]]));
        // The others.
        $this->assertSame(2, $this->total($w, ['gov' => ['giz', 'alx']]));
        $this->assertSame(1, $this->total($w, ['edu' => ['secondary_technical']]));

        // All together: women, 22–30, accountants, Banking, 2+ years, 7,000–9,000 → Sara only.
        $this->assertSame(1, $this->total($w, ['occ' => ['enoc:2411'], 'industry' => ['S01'], 'gender' => 'female',
            'age' => [22, 30], 'exp' => [2, null], 'salary' => [7000, 9000]]));
        $this->assertSame(0, $this->total($w, ['occ' => ['enoc:2411'], 'gender' => 'male', 'industry' => ['S01']]));

        // Anything strange is dropped, never guessed.
        $this->report($w, ['f' => ['occ' => ['nope:1', 'isco:24'], 'gender' => 'x', 'gov' => ['zzz']], 'rows' => 'bad', 'measure' => 'max'])
            ->assertInertia(fn (Assert $p) => $p->where('params.f.occ', ['isco:24'])->where('params.f.gender', '')->where('params.f.gov', [])
                ->where('params.rows', 'occupation')->where('params.measure', 'count'));
    }

    public function test_counts_averages_splits_and_the_market_wage(): void
    {
        $w = User::factory()->employee()->create();
        $this->people($w);

        // Split by 4-digit occupation in ENOC: accountants first (the biggest), "not given" last.
        $this->report($w, ['rows' => 'occupation', 'occ_level' => 'unit'])->assertInertia(fn (Assert $p) => $p
            ->where('result.rows.0.key', '2411')->where('result.rows.0.label', 'ENOC 2411 · Accountants')
            ->where('result.row_totals.2411.v', 3)
            ->where('result.rows.2.key', '_none')->where('result.rows.2.label', 'Not given'));
        // Other levels: the major group, the ESCO job.
        $this->report($w, ['rows' => 'occupation', 'occ_level' => 'major'])->assertInertia(fn (Assert $p) => $p->where('result.row_totals.2.v', 3)->where('result.row_totals.3.v', 1));
        $esco = $this->props($this->report($w, ['rows' => 'occupation', 'occ_level' => 'esco']))['result']['row_totals'];
        $this->assertSame([2, 1, 1], [$esco['esco:2411.1']['v'], $esco['esco:2411.1.1']['v'], $esco['unit:3221']['v']]);

        // Average expected salary: based on the 3 people who gave one; next to the market wage.
        $this->report($w, ['measure' => 'salary', 'rows' => 'occupation'])->assertInertia(fn (Assert $p) => $p
            ->where('result.measure', 'salary')->where('result.total.v', 6166.7)->where('result.total.n', 3)
            ->where('result.row_totals.2411.v', 7000)->where('result.row_totals.2411.n', 2)
            ->where('result.market.2411', 6740));
        // Average age: Hoda has no date of birth, so 4 people.
        $this->report($w, ['measure' => 'age', 'rows' => 'gender'])->assertInertia(fn (Assert $p) => $p->where('result.total.n', 4));
        $this->report($w, ['measure' => 'experience', 'rows' => 'gender'])->assertInertia(fn (Assert $p) => $p->where('result.total.n', 5));

        // Bands in their natural order; a cross-table.
        $this->report($w, ['rows' => 'age'])->assertInertia(fn (Assert $p) => $p
            ->where('result.rows', fn ($r) => collect($r)->pluck('key')->all() === ['20_24', '25_29', '30_34', '35_44', '_none']));
        $this->report($w, ['rows' => 'governorate', 'cols' => 'gender'])->assertInertia(fn (Assert $p) => $p
            ->where('result.cells.cai.female.v', 2)->where('result.cells.cai.male.v', 1)->where('result.col_totals.male.v', 2)
            ->where('result.cols.0.key', 'female'));
        // Industry, sector or sub-sector.
        $this->report($w, ['rows' => 'industry', 'ind_level' => 'sub_sector'])->assertInertia(fn (Assert $p) => $p
            ->where('result.row_totals.S01.v', 1)->where('result.row_totals.T01.v', 1)->where('result.row_totals._none.v', 2));
        // In Arabic.
        $w->forceFill(['language' => 'ar'])->save();
        $this->withSession(['locale' => 'ar'])->report($w, ['rows' => 'gender'])->assertInertia(fn (Assert $p) => $p
            ->where('summary.0', fn ($s) => str_contains($s, 'سُجّلوا')));
    }

    public function test_the_date_range_and_the_journey_stage(): void
    {
        $w = User::factory()->employee()->create();
        Carbon::setTestNow('2025-06-15 10:00:00');
        $old = $this->person($w, ['name_en' => 'Old Timer']);
        Carbon::setTestNow('2026-09-10 10:00:00');
        $ps = $this->people($w);

        $this->assertSame(6, $this->total($w, [], ['range' => ['preset' => 'all']]));
        $this->assertSame(5, $this->total($w, [], ['range' => ['preset' => 'this_month']]));
        $this->assertSame(1, $this->total($w, [], ['range' => ['preset' => 'last_year']]));
        $this->assertSame(1, $this->total($w, [], ['range' => ['preset' => 'custom', 'from' => '2025-06-01', 'to' => '2025-06-30']]));

        // Stages: the old one is placed (in September 2026), Sara is matched, Mona only eligible.
        $o = Opportunity::withoutGlobalScopes()->forceCreate(['company_id' => $w->company_id, 'kind' => 'job', 'title' => 'Clerk', 'occupations' => ['isco:2411'],
            'occupation_keys' => '|isco:2411|', 'governorates' => ['cai'], 'seats' => 5, 'eligible_from' => 50, 'check_from' => 20,
            'rules' => [['type' => 'age', 'mode' => 'must', 'min' => 18, 'max' => 60]], 'status' => 'open']);
        foreach ([$old, $ps['sara'], $ps['mona']] as $b) {
            $this->actingAs($w)->post("/app/beneficiaries/{$b->number}/eligibility", ['opportunity_id' => $o->id]);
            $this->later();
        }
        $this->actingAs($w)->post('/app/matches', ['opportunity_id' => $o->id, 'numbers' => [$old->number, $ps['sara']->number]]);
        $this->later();
        $m = \App\Models\OpportunityMatch::withoutGlobalScopes()->where('beneficiary_id', $old->id)->sole();
        $this->actingAs($w)->post("/app/matches/{$m->id}/move", ['to' => 'done']);
        $this->later();

        $this->report($w, ['rows' => 'stage'])->assertInertia(fn (Assert $p) => $p
            ->where('result.row_totals.placed.v', 1)->where('result.row_totals.matched.v', 1)->where('result.row_totals.eligible.v', 1)
            ->where('result.row_totals.registered.v', 3));
        $this->assertSame(3, $this->total($w, ['stage' => 'eligible']));   // at least eligible
        // "Placed" this month: the placement date counts, not the registration (registered in 2025).
        $this->assertSame(1, $this->total($w, ['stage' => 'placed'], ['range' => ['preset' => 'this_month']]));
        $this->assertSame(0, $this->total($w, ['stage' => 'placed'], ['range' => ['preset' => 'last_year']]));
    }

    // ── Excel · PDF · saved · open ──────────────────────────────────

    public function test_excel_and_pdf_in_english_and_arabic_are_recorded(): void
    {
        $w = User::factory()->employee()->create();
        $this->people($w);
        $q = urlencode(json_encode(['rows' => 'governorate', 'cols' => 'gender', 'f' => ['occ' => ['isco:24']]]));

        $x = $this->actingAs($w)->get("/app/reports/export?format=xlsx&r=$q")->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = sys_get_temp_dir().'/massar-report-test.xlsx';
        file_put_contents($path, $x->getContent());
        $book = IOFactory::load($path);
        $sheet = $book->getSheet(0)->toArray();
        $this->assertSame(['Governorate', 'Female', 'Male', 'Total'], array_slice($sheet[0], 0, 4));
        $this->assertSame('Cairo', $sheet[1][0]);
        $this->assertEquals(1, $sheet[1][1]);
        $this->assertSame('Total', end($sheet)[0]);
        $this->assertEquals(3, end($sheet)[3]);
        $about = implode(' ', array_map(fn ($r) => (string) $r[0], $book->getSheet(1)->toArray()));
        $this->assertStringContainsString('ISCO-08 24', $about);
        @unlink($path);

        $pdf = $this->actingAs($w)->get("/app/reports/export?format=pdf&r=$q")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Arabic: a right-to-left sheet with the Arabic names; the PDF is made with shaped letters.
        $this->withSession(['locale' => 'ar']);
        $w->forceFill(['language' => 'ar'])->save();
        $x = $this->actingAs($w)->get("/app/reports/export?format=xlsx&r=$q")->assertOk();
        file_put_contents($path, $x->getContent());
        $book = IOFactory::load($path);
        $this->assertTrue($book->getSheet(0)->getRightToLeft());
        $this->assertSame('القاهرة', $book->getSheet(0)->getCell('A2')->getValue());
        @unlink($path);
        $this->assertStringStartsWith('%PDF', $this->actingAs($w)->get("/app/reports/export?format=pdf&r=$q")->assertOk()->getContent());
        $this->assertSame('ﻥﻮﺒﺳﺎﺤﻤﻟﺍ 2411', ArabicShaper::line('2411 المحاسبون'));

        // Every download is recorded.
        $this->assertSame(['xlsx', 'pdf', 'xlsx', 'pdf'], ReportDownload::query()->orderBy('id')->pluck('format')->all());
        $this->assertSame([$w->company_id], ReportDownload::query()->distinct()->pluck('company_id')->all());
    }

    public function test_saved_reports_for_the_team(): void
    {
        $admin = User::factory()->companyAdmin()->create();
        $maker = User::factory()->employee($admin->company)->create();
        $other = User::factory()->employee($admin->company)->create();
        $this->person($maker);

        $this->actingAs($maker)->post('/app/reports/saved', ['name' => ' ', 'params' => ['rows' => 'gender']])->assertSessionHasErrors('name');
        $this->later();
        $this->actingAs($maker)->post('/app/reports/saved', ['name' => 'Women by governorate', 'params' => ['rows' => 'governorate', 'f' => ['gender' => 'female', 'occ' => ['junk']]]])
            ->assertSessionHas('success');
        $s = SavedReport::withoutGlobalScopes()->sole();
        $this->assertSame(['governorate', 'female', []], [$s->params['rows'], $s->params['f']['gender'], $s->params['f']['occ']]);

        // The whole team sees it; only its maker (or a Company Admin) changes it.
        $this->actingAs($other)->get('/app/reports')->assertInertia(fn (Assert $p) => $p->where('saved.0.name', 'Women by governorate')->where('saved.0.can_change', false));
        $this->actingAs($other)->patch("/app/reports/saved/{$s->id}", ['name' => 'Mine'])->assertForbidden();
        $this->actingAs($other)->delete("/app/reports/saved/{$s->id}")->assertForbidden();
        $this->actingAs($maker)->patch("/app/reports/saved/{$s->id}", ['name' => 'Women, by governorate'])->assertSessionHas('success');
        $this->assertSame('Women, by governorate', $s->refresh()->name);
        $this->actingAs($admin)->delete("/app/reports/saved/{$s->id}")->assertSessionHas('success');
        $this->assertSame(0, SavedReport::withoutGlobalScopes()->count());
    }

    public function test_open_these_people_in_the_cv_bank(): void
    {
        $w = User::factory()->employee()->create();
        $ps = $this->people($w);
        $token = fn (array $p, ?string $row = null, ?string $col = null) => rtrim(strtr(base64_encode(json_encode(['p' => $p, 'row' => $row, 'col' => $col])), '+/', '-_'), '=');

        // Every accountant; then only the women among them in Cairo.
        $all = $token(['f' => ['occ' => ['isco:2411']], 'rows' => 'governorate', 'cols' => 'gender']);
        $this->actingAs($w)->get('/app/cv-bank?report='.$all)->assertInertia(fn (Assert $p) => $p
            ->has('list.data', 3)->where('report.summary', fn ($s) => str_contains(collect($s)->implode(' '), 'ISCO-08 2411 · Accountants')));
        $cell = $token(['f' => ['occ' => ['isco:2411']], 'rows' => 'governorate', 'cols' => 'gender'], 'cai', 'female');
        $this->actingAs($w)->get('/app/cv-bank?report='.$cell)->assertInertia(fn (Assert $p) => $p
            ->has('list.data', 1)->where('list.data.0.number', $ps['sara']->number)->where('report.row', 'Cairo')->where('report.col', 'Female'));

        // … and check exactly those people against a training.
        $o = Opportunity::withoutGlobalScopes()->forceCreate(['company_id' => $w->company_id, 'kind' => 'training', 'title' => 'Excel', 'occupations' => ['isco:24'],
            'occupation_keys' => '|isco:2|isco:24|', 'governorates' => ['cai'], 'seats' => 5, 'eligible_from' => 50, 'check_from' => 20,
            'rules' => [['type' => 'age', 'mode' => 'must', 'min' => 18, 'max' => 60]], 'status' => 'open']);
        $this->actingAs($w)->post("/app/training/{$o->id}/runs", ['scope' => 'search', 'filters' => ['report' => $all]])->assertRedirect();
        $this->assertSame(3, EligibilityRun::withoutGlobalScopes()->sole()->total);

        // A broken token is ignored, never an error.
        $this->actingAs($w)->get('/app/cv-bank?report=%%%')->assertOk()->assertInertia(fn (Assert $p) => $p->where('report', null));
    }

    // ── Super Admin, separation, permissions, how people joined ─────

    public function test_the_super_admin_sees_counts_only_across_partners(): void
    {
        $a = User::factory()->employee()->create();
        $this->people($a);                                     // 5 in partner A (3 women)
        $b = User::factory()->employee()->create();
        $this->person($b, ['name_en' => 'Nour Hany']);           // 1 in partner B
        // A job seeker's own profile in the public pool is never counted.
        $pool = Company::query()->withoutGlobalScope('partners')->forceCreate(['name' => 'Massar Public Pool', 'is_pool' => true, 'is_active' => true, 'seat_limit' => 0]);
        Beneficiary::withoutGlobalScopes()->forceCreate(['company_id' => $pool->id, 'number' => 1, 'name_en' => 'Seeker', 'gender' => 'female', 'governorate' => 'cai']);

        $admin = User::factory()->superAdmin()->create();
        $this->report($admin, ['rows' => 'gender', 'measure' => 'salary'], 'admin')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Admin/Reports/Index')->where('admin', true)
            ->where('result.measure', 'count')                              // counts only
            ->where('result.total.v', 6)
            ->where('result.row_totals.female.small', true)->where('result.row_totals.female.v', null)   // 4 → "fewer than 5"
            ->where('result.row_totals.male.small', true)->where('result.row_totals.male.v', null)       // 2 → "fewer than 5"
            ->where('saved', []));
        $this->assertStringStartsWith('%PDF', $this->actingAs($admin)->get('/admin/reports/export?format=pdf')->assertOk()->getContent());
        $this->assertNull(ReportDownload::query()->sole()->company_id);
        $this->actingAs($admin)->get('/app/reports')->assertRedirect();
    }

    public function test_another_partner_is_never_counted_and_permissions(): void
    {
        $w = User::factory()->employee()->create();
        $this->people($w);
        $outsider = User::factory()->companyAdmin()->create();
        $this->person($outsider, ['name_en' => 'Theirs']);
        $s = SavedReport::withoutGlobalScopes()->forceCreate(['company_id' => $w->company_id, 'name' => 'Ours', 'params' => [], 'created_by' => $w->id]);

        $this->report($outsider, [])->assertInertia(fn (Assert $p) => $p->where('result.total.v', 1)->where('saved', []));
        $this->actingAs($outsider)->patch("/app/reports/saved/{$s->id}", ['name' => 'x'])->assertNotFound();
        $this->actingAs($outsider)->delete("/app/reports/saved/{$s->id}")->assertNotFound();
        $token = rtrim(strtr(base64_encode(json_encode(['p' => ['rows' => 'gender'], 'row' => null, 'col' => null])), '+/', '-_'), '=');
        $this->actingAs($outsider)->get('/app/cv-bank?report='.$token)->assertInertia(fn (Assert $p) => $p->has('list.data', 1));

        $viewer = User::factory()->employee($w->company)->create(['permissions' => ['beneficiaries.view', 'reports.view']]);
        $this->actingAs($viewer)->get('/app/reports')->assertOk()->assertInertia(fn (Assert $p) => $p->where('can_open_people', true));
        $this->actingAs($viewer)->get('/app/reports/export?format=xlsx')->assertForbidden();
        $nobody = User::factory()->employee($w->company)->create(['permissions' => ['beneficiaries.view']]);
        $this->actingAs($nobody)->get('/app/reports')->assertForbidden();
        $this->actingAs($nobody)->get('/app/reports/export')->assertForbidden();
        $this->assertSame(0, ReportDownload::query()->count());
    }

    public function test_how_people_joined_and_their_industry_are_kept(): void
    {
        $w = User::factory()->employee()->create();
        $b = $this->person($w, ['work_history' => [$this->job('S01', '2020-01', '2021-01'), $this->job('T01', '2022-01', null, true)]]);
        $this->assertSame(['manual', 'T01'], [$b->source, $b->industry]);

        $this->actingAs($w)->post('/app/intake', ['name_en' => 'Omar Ali', 'gender' => 'male', 'governorate' => 'cai', 'phone' => '01009998887',
            'education_level' => 'university', 'work_history' => [], 'skills' => [], 'languages' => []])->assertSessionHasNoErrors();
        $this->assertSame('intake', Beneficiary::withoutGlobalScopes()->where('name_en', 'Omar Ali')->value('source'));

        // The current job ends; the last one (by date) counts.
        $this->assertSame('S01', BeneficiaryRecorder::industryOf([$this->job('T01', '2019-01', '2019-12'), $this->job('S01', '2020-01', '2023-12')]));
        $this->assertNull(BeneficiaryRecorder::industryOf([['title' => 'x', 'from' => '2020-01']]));
    }
}
