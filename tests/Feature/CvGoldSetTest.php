<?php

namespace Tests\Feature;

use App\Models\Backbone\IscoGroup;
use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\LearnedRule;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Cv\CvGoldSet;
use App\Services\Cv\CvReader;
use App\Services\Cv\LearnedRuleBook;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvGoldSetTest (Step 7.5 · reading each job by what its lines are)
//  Location: tests/Feature/CvGoldSetTest.php
//
//    • every CV of the gold set (tests/Fixtures/cv-gold/) is read
//      exactly as its .json says: Title / Company / Date in any order,
//      places kept apart, "Industry:" skipped, numbered jobs, a second
//      title under the same company, two lines competing to be the
//      title marked Check, headings split over two lines, "Majors:"
//      inside Education, courses kept out of the jobs …
//    • an employer taught as a Learned Rule, or already typed in a
//      profile of the workspace, is recognised as the employer
//    • the job's location is saved; the reader's per-job Check notes
//      are not
// ══════════════════════════════════════════════════════════════════

class CvGoldSetTest extends TestCase
{
    use RefreshDatabase;
    use WritesBackboneSamples;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSamples();
        BackboneImporter::make()->run();
        // Two official ESCO titles the gold set needs that the small sample backbone does not have.
        $unit = IscoGroup::query()->where('level', 4)->value('id');
        foreach (['Welder', 'Fitter'] as $title) {
            DB::table('occupation_labels')->insert(['isco_group_id' => $unit, 'esco_occupation_id' => null, 'source' => 'esco', 'lang' => 'en',
                'kind' => 'preferred', 'label' => $title, 'normalized' => TextNormalizer::normalize($title)]);
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownSamples();
        parent::tearDown();
    }

    public function test_every_cv_of_the_gold_set_is_read_right(): void
    {
        $cases = CvGoldSet::readAll(app(CvReader::class));
        $this->assertGreaterThanOrEqual(50, count($cases));
        $wrong = [];
        foreach ($cases as $name => $case) {
            if ($case['skipped']) {
                continue;
            }
            foreach (CvGoldSet::compare($case['reading'], $case['want']) as $p) {
                $wrong[] = "{$name}: {$p}";
            }
        }
        $this->assertSame([], $wrong, "Read differently from the correct answers:\n".implode("\n", $wrong));
    }

    public function test_an_employer_the_workspace_knows_is_the_employer(): void
    {
        $cv = "Rami Adel\nrami@example.com\n\nEXPERIENCE\nStitch\nOrbit\nJan 2019 – Present\n- Daily reports";
        $reader = app(CvReader::class);

        // Nothing shows which line is which: never guessed silently.
        $job = $reader->read($cv)['form']['work_history'][0];
        $this->assertContains('title', $job['check']);

        // Taught as a Learned Rule: "Orbit" is a company.
        $r = $reader->withRules(['employers' => [['id' => 7, 'key' => TextNormalizer::normalize('Orbit')]]])->read($cv);
        $job = $r['form']['work_history'][0];
        $this->assertSame(['Stitch', 'Orbit', []], [$job['title'], $job['employer'], $job['check']]);
        $this->assertContains(7, $r['rules_used']);

        // Already typed as the employer in a profile of this workspace.
        $job = $reader->withRules(['known_employers' => ['ORBIT']])->read($cv)['form']['work_history'][0];
        $this->assertSame(['Stitch', 'Orbit'], [$job['title'], $job['employer']]);

        // A job title typed in the employer box by mistake is not learned as an employer.
        $job = $reader->withRules(['known_employers' => ['Accountant']])->read("Rami Adel\n\nEXPERIENCE\nAccountant\nNile Foods\n2019 – 2021")['form']['work_history'][0];
        $this->assertSame(['Accountant', 'Nile Foods'], [$job['title'], $job['employer']]);
    }

    public function test_employers_can_be_taught_and_come_from_profiles(): void
    {
        $worker = User::factory()->employee()->create();
        $this->actingAs($worker)->post('/app/rules', ['kind' => 'employer', 'phrase' => 'Etisalat Misr'])->assertSessionHasNoErrors();
        $rule = LearnedRule::query()->sole();
        $this->assertSame(['employer', 'etisalat misr'], [$rule->kind, $rule->normalized]);

        $this->actingAs($worker)->post('/app/beneficiaries', [
            'name_en' => 'Omar Ali', 'gender' => 'male', 'governorate' => 'cai', 'phone' => '01001234567',
            'work_history' => [['title' => 'Driver', 'employer' => 'Orbit Logistics', 'from' => '2020-01', 'current' => true]],
        ])->assertSessionHasNoErrors();

        $book = app(LearnedRuleBook::class)->forReading($worker->company_id);
        $this->assertSame([['id' => $rule->id, 'key' => 'etisalat misr']], $book['employers']);
        cache()->flush();
        $this->assertContains('Orbit Logistics', app(LearnedRuleBook::class)->forReading($worker->company_id)['known_employers']);
    }

    public function test_the_location_is_saved_with_the_job_and_the_check_notes_are_not(): void
    {
        $worker = User::factory()->employee()->create();
        $this->actingAs($worker)->post('/app/beneficiaries', [
            'name_en' => 'Sherif Aly', 'gender' => 'male', 'governorate' => 'cai', 'phone' => '01001234567',
            'work_history' => [['title' => 'General Accountant', 'employer' => 'Savola Foods', 'location' => ' Jeddah, Saudi Arabia ',
                'from' => '2017-01', 'to' => '2021-03', 'current' => false, 'check' => ['dates']]],
        ])->assertSessionHasNoErrors();

        $job = Beneficiary::query()->sole()->work_history[0];
        $this->assertSame('Jeddah, Saudi Arabia', $job['location']);
        $this->assertArrayNotHasKey('check', $job);

        // A profile saved before the location existed: saving it again shows no false change.
        $b = Beneficiary::query()->sole();
        $b->forceFill(['work_history' => [array_diff_key($job, ['location' => 1])]])->save();
        $this->actingAs($worker)->patch('/app/beneficiaries/1', [
            'name_en' => 'Sherif Aly', 'gender' => 'male', 'governorate' => 'cai', 'phone' => '01001234567',
            'work_history' => [['title' => 'General Accountant', 'employer' => 'Savola Foods', 'from' => '2017-01', 'to' => '2021-03', 'current' => false,
                'responsibilities' => $job['responsibilities']]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, BeneficiaryChange::query()->count());
    }
}
