<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\CvDocument;
use App\Models\Employer;
use App\Models\Sector;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use App\Services\Employers\EmployerBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\MakesCvFiles;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — EmployersTest (Step 10.5 · sectors, companies, countries)
//  Location: tests/Feature/EmployersTest.php
//
//    • employers:import loads the sectors and companies; running it
//      again adds and updates, never duplicates
//    • a CV's employer is matched to the list (sector filled), its
//      country found from the place ("Riyadh, KSA" → Saudi Arabia);
//      two companies with the same name → Check
//    • the job's country and sub-sector are saved with the form's rules
//    • a company typed by staff with a sector is learned for THAT
//      workspace only; the public site never teaches
//    • the employer suggestions; the sector filter of the CV Bank
// ══════════════════════════════════════════════════════════════════

class EmployersTest extends TestCase
{
    use MakesCvFiles;
    use RefreshDatabase;
    use WritesBackboneSamples;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSamples();
        BackboneImporter::make()->run();
        MarketImporter::make()->run();
        Storage::fake('cvs');
        Cache::flush();
        $this->file = sys_get_temp_dir().'/massar-employers-'.uniqid().'.xlsx';
        $this->writeBook([
            ['Vodafone Egypt', 'فودافون مصر', 'Vodafone; Mobinil Old', 'S05', 'Private (foreign-owned)', 'EG', 'Cairo'],
            ['Delta Trading', 'دلتا للتجارة', '', 'T01', 'Private', 'EG', ''],
            ['Delta Trading Co', '', '', 'T02', 'Private', 'EG', ''],   // another company whose name reads the same
            ['National Bank of Egypt', 'البنك الأهلي المصري', 'NBE', 'S01', 'State-owned', 'EG', ''],
        ], [['Juhayna Food Industries', 'جهينة', 'I01 · Industrial › Food & Beverages']]);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        $this->tearDownSamples();
        parent::tearDown();
    }

    private function writeBook(array $companies, array $added = []): void
    {
        $book = new Spreadsheet;
        $s = $book->getActiveSheet()->setTitle('Sectors');
        $s->fromArray([['Sector', 'القطاع', 'Sub-sector code', 'Sub-sector', 'القطاع الفرعي'],
            ['Industrial', 'صناعي', 'I01', 'Food & Beverages', 'الأغذية والمشروبات'],
            ['Trading', 'تجاري', 'T01', 'Wholesale & Distribution', 'تجارة الجملة والتوزيع'],
            ['Trading', 'تجاري', 'T02', 'Retail & Supermarkets', 'تجارة التجزئة'],
            ['Service', 'خدمي', 'S01', 'Banking', 'البنوك'],
            ['Service', 'خدمي', 'S05', 'Telecommunications', 'الاتصالات']]);
        $c = $book->createSheet()->setTitle('Companies');
        $c->fromArray([['Company name (English)', 'اسم الشركة (عربي)', 'Other names (; separated)', 'Sub-sector code', 'Ownership', 'Country', 'Governorate (head office)'], ...$companies]);
        $a = $book->createSheet()->setTitle('Add companies');
        $a->fromArray([['Company name (English)', 'اسم الشركة (عربي)', 'Sub-sector'], ...$added]);
        (new Xlsx($book))->save($this->file);
    }

    public function test_the_import_loads_sectors_and_companies_and_can_run_again(): void
    {
        $this->artisan('employers:import', ['file' => $this->file])->assertSuccessful();
        $this->assertSame(3, Sector::query()->whereNull('parent')->count());
        $this->assertSame(5, Sector::query()->whereNotNull('parent')->count());
        $this->assertSame(5, Employer::query()->count());
        $v = Employer::query()->where('name_en', 'Vodafone Egypt')->sole();
        $this->assertSame(['S05', 'foreign', 'cai'], [$v->sub_sector, $v->ownership, $v->governorate]);
        $this->assertSame('I01', Employer::query()->where('name_en', 'Juhayna Food Industries')->value('sub_sector'));

        // Again, with one change and one new company: updated and added, not duplicated.
        $this->writeBook([['Vodafone Egypt', 'فودافون مصر', 'Vodafone', 'S05', 'Private', 'EG', 'Cairo'], ['Orange Egypt', 'أورانج مصر', 'Mobinil', 'S05', 'Private', 'EG', '']]);
        $this->artisan('employers:import', ['file' => $this->file])->assertSuccessful();
        $this->assertSame(6, Employer::query()->count());
        $this->assertSame('private', Employer::query()->where('name_en', 'Vodafone Egypt')->value('ownership'));

        // A mistake in the file: nothing changes.
        $this->artisan('employers:import', ['file' => '/no/such/file.xlsx'])->assertFailed();
        $this->assertSame(6, Employer::query()->count());
    }

    public function test_a_cv_employer_is_matched_and_the_country_found(): void
    {
        $this->artisan('employers:import', ['file' => $this->file]);
        $book = app(EmployerBook::class);
        $this->assertSame('one', $book->match('Vodafone Egypt Telecommunications S.A.E', null)['status']);
        $this->assertSame('one', $book->match('شركة فودافون مصر (ش.م.م)', null)['status']);
        $this->assertSame('one', $book->match('NBE', null)['status']);
        $this->assertSame('many', $book->match('Delta Trading', null)['status']);
        $this->assertSame('none', $book->match('Nile Foods', null)['status']);
        $this->assertSame('none', $book->match('Vodafone Hospital', null)['status']);   // a one-word name must be the whole line
        $this->assertSame('SA', EmployerBook::countryOf('Riyadh, KSA'));
        $this->assertSame('AE', EmployerBook::countryOf('Dubai – UAE'));
        $this->assertSame('EG', EmployerBook::countryOf('Nasr City, Cairo'));

        $worker = User::factory()->employee()->create();
        $batch = $this->actingAs($worker)->postJson('/app/cv-upload/batches', ['files' => 1, 'client_id' => 'b1'])->assertOk()->json('batch');
        $cv = $this->englishCv();
        $cv[array_search('Accountant — Delta Trading | Mar 2021 – Present', $cv, true)] = 'Accountant — Vodafone Egypt | Mar 2021 – Present';
        $cv[array_search('Junior Accountant, Nile Foods (01/2018 - 02/2021)', $cv, true)] = 'Junior Accountant, Almarai, Riyadh, KSA (01/2018 - 02/2021)';
        $this->actingAs($worker)->post("/app/cv-upload/batches/{$batch}/files", ['file' => $this->docx($cv), 'client_id' => 'c1'], ['Accept' => 'application/json'])->assertOk();
        $jobs = CvDocument::query()->withoutGlobalScopes()->sole()->reading['form']['work_history'];
        $this->assertSame(['EG', 'S05'], [$jobs[0]['country'], $jobs[0]['sub_sector']]);
        $this->assertSame(Employer::query()->where('name_en', 'Vodafone Egypt')->value('id'), $jobs[0]['employer_id']);
        $this->assertSame('SA', $jobs[1]['country']);
        $this->assertArrayNotHasKey('employer_id', $jobs[1]);
    }

    public function test_the_form_saves_country_and_sector_and_staff_teach_new_companies(): void
    {
        $this->artisan('employers:import', ['file' => $this->file]);
        $a = User::factory()->employee()->create();
        $b = User::factory()->employee()->create();
        $job = ['title' => 'Accountant', 'employer' => 'Nile Foods', 'from' => '2019-01', 'current' => true, 'country' => 'eg', 'sub_sector' => 'I01'];
        $profile = fn (array $jobs, string $phone) => ['name_en' => 'Omar Ali', 'gender' => 'male', 'governorate' => 'cai', 'phone' => $phone, 'work_history' => $jobs];

        $this->actingAs($a)->post('/app/beneficiaries', $profile([['sub_sector' => 'Z99'] + $job], '01001234567'))->assertSessionHasErrors(['work_history.0.sub_sector']);
        $this->actingAs($a)->post('/app/beneficiaries', $profile([['country' => 'ZZ'] + $job], '01001234567'))->assertSessionHasErrors(['work_history.0.country']);
        $this->actingAs($a)->post('/app/beneficiaries', $profile([$job, ['title' => 'Clerk', 'employer' => 'Almarai', 'from' => '2015-01', 'to' => '2018-12', 'country' => 'SA', 'sub_sector' => 'I01']], '01001234567'))
            ->assertSessionHasNoErrors();
        $saved = Beneficiary::query()->withoutGlobalScopes()->sole()->work_history[0];
        $this->assertSame(['EG', 'I01'], [$saved['country'], $saved['sub_sector']]);

        // Learned for A's workspace only (and only the job in Egypt).
        $this->assertSame(1, Employer::query()->whereNotNull('company_id')->count());
        $this->assertSame($a->company_id, Employer::query()->whereNotNull('company_id')->value('company_id'));
        $book = app(EmployerBook::class);
        $this->assertSame('one', $book->match('Nile Foods', $a->company_id)['status']);
        $this->assertSame('none', $book->match('Nile Foods', $b->company_id)['status']);

        // Suggestions: the Massar list for everyone, a workspace's own only for it; the public site only the list.
        $this->actingAs($a)->getJson('/app/employer-search?q=nile')->assertJsonPath('results.0.name_en', 'Nile Foods')->assertJsonPath('results.0.own', true);
        $this->actingAs($b)->getJson('/app/employer-search?q=nile')->assertJsonCount(0, 'results');
        $this->actingAs($b)->getJson('/app/employer-search?q=فودا')->assertJsonPath('results.0.sub_sector', 'S05');
        auth()->logout();
        $this->getJson('/employer-search?q=nile')->assertJsonCount(0, 'results');
        $this->getJson('/employer-search?q=vodafone')->assertJsonPath('results.0.name_en', 'Vodafone Egypt');

        // The CV Bank: worked in this sector / sub-sector.
        $this->actingAs($a)->get('/app/cv-bank?sector=IND')->assertInertia(fn (Assert $p) => $p->has('list.data', 1)->has('sectors', 3));
        $this->actingAs($a)->get('/app/cv-bank?sector=I01')->assertInertia(fn (Assert $p) => $p->has('list.data', 1));
        $this->actingAs($a)->get('/app/cv-bank?sector=S01')->assertInertia(fn (Assert $p) => $p->has('list.data', 0));
        // The profile shows the sector's name.
        $this->actingAs($a)->get('/app/beneficiaries/1')->assertInertia(fn (Assert $p) => $p->where('beneficiary.work_history.0.sector_label', 'Industrial › Food & Beverages'));
    }

    public function test_other_sub_sectors_go_to_the_super_admin_and_the_employer_is_matched_after_a_swap(): void
    {
        $this->artisan('employers:import', ['file' => $this->file]);
        $a = User::factory()->employee()->create();
        $job = ['title' => 'Agent', 'employer' => 'Blue Call Centre', 'from' => '2019-01', 'current' => true, 'country' => 'EG', 'governorate' => 'giz',
            'sector' => 'SRV', 'sub_sector_other' => 'Call centres for banks'];
        $this->actingAs($a)->post('/app/beneficiaries', ['name_en' => 'Mona', 'gender' => 'female', 'governorate' => 'cai', 'phone' => '01001234567', 'work_history' => [$job]])
            ->assertSessionHasNoErrors();
        $this->actingAs($a)->post('/app/beneficiaries', ['name_en' => 'Hany', 'gender' => 'male', 'governorate' => 'cai', 'phone' => '01001234568',
            'work_history' => [['sub_sector_other' => 'call centres for BANKS'] + $job]])->assertSessionHasNoErrors();
        $p = \App\Models\SectorProposal::query()->sole();
        $this->assertSame(['SRV', 2, 'open'], [$p->sector, $p->times, $p->status]);
        $this->assertSame('giz', Beneficiary::query()->withoutGlobalScopes()->first()->work_history[0]['governorate']);

        // Only the Super Admin decides. Adding it makes a new official sub-sector and moves the jobs to it.
        $this->actingAs($a)->get('/admin/sectors')->assertForbidden();
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get('/admin/sectors')->assertOk()->assertInertia(fn (Assert $pg) => $pg->component('Admin/Sectors/Index')->has('open', 1));
        $this->actingAs($admin)->post("/admin/sectors/proposals/{$p->id}/add", ['name_en' => 'Call Centres', 'name_ar' => 'مراكز الاتصال'])->assertSessionHasNoErrors();
        $this->assertSame('S06', Sector::query()->where('name_en', 'Call Centres')->value('code'));
        foreach (Beneficiary::query()->withoutGlobalScopes()->get() as $b) {
            $this->assertSame(['S06', null], [$b->work_history[0]['sub_sector'], $b->work_history[0]['sub_sector_other']]);
        }

        // After ⇄ moved a name into the employer box: the one company it means.
        $this->actingAs($a)->getJson('/app/employer-search?match=1&q='.urlencode('Vodafone Egypt'))->assertJsonPath('match.sub_sector', 'S05');
        $this->actingAs($a)->getJson('/app/employer-search?match=1&q=Delta%20Trading')->assertJsonPath('match', null);
        // Nothing typed: the companies this workspace added lately.
        $this->actingAs($a)->getJson('/app/employer-search?q=')->assertJsonPath('recent', true);
        $this->assertSame('cai', EmployerBook::governorateOf('Nasr City, Cairo'));
    }
}
