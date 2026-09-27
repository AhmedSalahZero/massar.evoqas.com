<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Company;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesCvFiles;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvBankSearchTest (Step 8 · Scope v2 §3 Searchable CV Bank)
//  Location: tests/Feature/CvBankSearchTest.php
//
//    • every word inside the CVs is searchable (not only the profile),
//      with the matching lines shown as snippets
//    • Arabic spelling differences do not matter (ة/ه, أ/ا, "ال")
//    • occupation synonyms: "bookkeeper" finds the accountants
//    • filters: occupation (ISCO / ENOC / ESCO, any level), governorate,
//      minimum experience, CV language
//    • the index follows profile changes; `cvbank:index` fills it
//    • only the signed-in person's workspace is searched
// ══════════════════════════════════════════════════════════════════

class CvBankSearchTest extends TestCase
{
    use MakesCvFiles;
    use RefreshDatabase;
    use WritesBackboneSamples;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSamples();
        BackboneImporter::make()->run();
        MarketImporter::make()->run();
        Storage::fake('cvs');
    }

    protected function tearDown(): void
    {
        $this->tearDownSamples();
        parent::tearDown();
    }

    private function upload(User $user, UploadedFile $file): array
    {
        $batch = $this->actingAs($user)->postJson('/app/cv-upload/batches', ['files' => 1, 'client_id' => uniqid('b', true)])->assertOk()->json('batch');

        return $this->actingAs($user)->post("/app/cv-upload/batches/{$batch}/files", ['file' => $file, 'client_id' => uniqid('c')],
            ['Accept' => 'application/json'])->assertOk()->json('document');
    }

    /** The numbers of the people a search finds, in order. */
    private function found(User $user, array $query): array
    {
        $numbers = [];
        $this->actingAs($user)->get('/app/cv-bank?'.http_build_query($query))->assertOk()
            ->assertInertia(function (Assert $p) use (&$numbers) {
                $p->component('App/CvBank/Index');
                $numbers = array_column($p->toArray()['props']['list']['data'] ?? [], 'number');
            });

        return $numbers;
    }

    private function results(User $user, array $query): array
    {
        $rows = [];
        $this->actingAs($user)->get('/app/cv-bank?'.http_build_query($query))
            ->assertInertia(function (Assert $p) use (&$rows) { $rows = $p->toArray()['props']['list']['data']; });

        return $rows;
    }

    private function twoPeople(): User
    {
        $worker = User::factory()->employee()->create();
        $this->assertSame('added', $this->upload($worker, $this->docx($this->englishCv()))['status']);     // #1 accountant, Cairo, 6+ years
        // #2: Arabic, registered by hand (no CV), Giza
        $this->actingAs($worker)->post('/app/beneficiaries', [
            'name_ar' => 'منى عبد الرحمن', 'gender' => 'female', 'governorate' => 'giz', 'phone' => '01223456789',
            'education_level' => 'university', 'education' => [['qualification' => 'بكالوريوس تجارة', 'institution' => 'جامعة عين شمس', 'year' => 2019]],
            'work_history' => [['title' => 'أخصائية موارد بشرية', 'employer' => 'شركة النيل', 'from' => '2022-01', 'current' => true, 'responsibilities' => ['إعداد كشوف المرتبات']]],
            'skills' => ['التوظيف'], 'languages' => [['code' => 'ar', 'level' => 'native']],
        ])->assertSessionHasNoErrors();

        return $worker;
    }

    public function test_every_word_in_the_cvs_is_searchable_with_snippets(): void
    {
        $worker = $this->twoPeople();

        // "general accounting" is only in the CV's summary — not in any profile field.
        $this->assertSame([1], $this->found($worker, ['q' => 'general accounting']));
        [$row] = $this->results($worker, ['q' => 'GENERAL Accounting']);
        $this->assertSame('words', $row['found_by']);
        $hits = collect($row['snippets'][0])->where('hit', true)->pluck('t')->map(fn ($t) => trim($t))->implode(' ');
        $this->assertSame('general accounting.', $hits);

        // Every word must be there.
        $this->assertSame([], $this->found($worker, ['q' => 'general plumbing']));
        // Profile words count too: a job responsibility, a skill.
        $this->assertSame([2], $this->found($worker, ['q' => 'كشوف المرتبات']));
    }

    public function test_arabic_spelling_differences_do_not_matter(): void
    {
        $worker = $this->twoPeople();
        foreach (['جامعه عين شمس', 'الجامعة', 'اخصائيه', 'المرتبات'] as $q) {
            $this->assertSame([2], $this->found($worker, ['q' => $q]), $q);
        }
    }

    public function test_occupation_synonyms_find_people_whose_cv_never_says_the_word(): void
    {
        $worker = $this->twoPeople();
        // "bookkeeper" is an alternative ESCO title of accountant: #1 is registered as accountant.
        $this->assertSame([1], $this->found($worker, ['q' => 'bookkeeper']));
        [$row] = $this->results($worker, ['q' => 'bookkeeper']);
        $this->assertSame(['occupation', []], [$row['found_by'], $row['snippets']]);
    }

    public function test_the_filters(): void
    {
        $worker = $this->twoPeople();
        $unit = DB::table('isco_groups')->where('code', '2411')->value('id');
        $esco = DB::table('esco_occupations')->where('code', '2411.1')->first();
        Beneficiary::query()->withoutGlobalScopes()->where('number', 1)->update(['isco_group_id' => $unit, 'esco_occupation_id' => $esco->id, 'isco_code' => '2411']);

        foreach (['isco:2', 'isco:24', 'isco:241', 'isco:2411', 'enoc:2411', 'esco:2411.1'] as $occ) {
            $this->assertSame([1], $this->found($worker, ['occ' => $occ]), $occ);
        }
        $this->assertSame([], $this->found($worker, ['occ' => 'isco:3']));
        $this->assertSame([], $this->found($worker, ['occ' => 'esco:2411.1.1']));      // a narrower job: not this person

        $this->assertSame([2], $this->found($worker, ['governorate' => 'giz']));
        $this->assertSame([1], $this->found($worker, ['min_years' => 5]));
        $this->assertSame([1], $this->found($worker, ['cv_lang' => 'en']));             // #2 has no CV
        $this->assertSame([], $this->found($worker, ['cv_lang' => 'ar']));
        $this->assertSame([1], $this->found($worker, ['q' => 'accountant', 'governorate' => 'cai', 'min_years' => 3]));

        // The occupation filter's search: every standard, every level.
        $options = collect($this->actingAs($worker)->getJson('/app/cv-bank/occupations?q=241')->assertOk()->json())->pluck('value');
        $this->assertContains('isco:241', $options);
        $this->assertContains('isco:2411', $options);
        $this->assertContains('esco:2411.1', $options);
    }

    public function test_the_index_follows_changes_and_can_be_rebuilt(): void
    {
        $worker = $this->twoPeople();
        $this->assertSame([], $this->found($worker, ['q' => 'Power BI']));

        $b = Beneficiary::query()->withoutGlobalScopes()->where('number', 1)->sole();
        $b->forceFill(['skills' => [...$b->skills, 'Power BI']])->save();
        $this->assertSame([1], $this->found($worker, ['q' => 'power bi']));

        DB::table('cv_bank_index')->delete();
        $this->assertSame([], $this->found($worker, ['q' => 'power bi']));
        $this->artisan('cvbank:index')->expectsOutputToContain('2 profiles')->assertSuccessful();
        $this->assertSame([1], $this->found($worker, ['q' => 'power bi']));
        $this->assertSame([1], $this->found($worker, ['q' => 'general accounting']));    // the CV text is back too
    }

    public function test_only_this_workspace_is_searched(): void
    {
        $this->twoPeople();
        $stranger = User::factory()->employee(Company::factory()->create())->create();
        $this->assertSame([], $this->found($stranger, ['q' => 'general accounting']));
        $this->assertSame([], $this->found($stranger, ['q' => 'bookkeeper']));
        $this->assertSame([], $this->found($stranger, ['governorate' => 'cai']));

        // Nothing searched yet: no list, just the page.
        $this->actingAs($stranger)->get('/app/cv-bank')->assertInertia(fn (Assert $p) => $p->where('searched', false)->where('list', null));
        $noAccess = User::factory()->employee()->create(['permissions' => ['cv.upload']]);
        $this->actingAs($noAccess)->get('/app/cv-bank')->assertForbidden();
    }
}
