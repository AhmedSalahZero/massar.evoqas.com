<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Company;
use App\Models\CvDocument;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesCvFiles;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — IntakeTest (Step 9 · Guided Intake)
//  Location: tests/Feature/IntakeTest.php
//
//    • without a CV: the answers make a profile, checked by the same
//      rules as the registration form
//    • with a CV: the CV is read first and NOT added automatically
//      (a person is filling the profile); the reading comes back to
//      fill the journey; saving attaches the CV to the new profile and
//      keeps how the occupation was chosen
//    • a CV already decided in the Review Queue cannot be used twice
//    • the same mobile asks the duplicate question
//    • permissions
// ══════════════════════════════════════════════════════════════════

class IntakeTest extends TestCase
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

    private function answers(array $over = []): array
    {
        return array_merge([
            'name_en' => 'Omar Ali', 'gender' => 'male', 'governorate' => 'cai', 'phone' => '01001234567',
            'education_level' => 'university', 'work_history' => [], 'skills' => ['Excel'], 'languages' => [['code' => 'ar', 'level' => 'native']],
            'expected_salary' => '6,000', 'job_type' => 'full_time',
        ], $over);
    }

    public function test_the_journey_opens_and_a_profile_is_made_without_a_cv(): void
    {
        $worker = User::factory()->employee()->create();
        $this->actingAs($worker)->get('/app/intake')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('App/Intake/Journey')->where('can_upload', true)->has('options.governorates'));

        $this->actingAs($worker)->post('/app/intake', $this->answers())
            ->assertSessionHasNoErrors()->assertRedirect('/app/beneficiaries/1');

        $b = Beneficiary::query()->withoutGlobalScopes()->sole();
        $this->assertSame('Omar Ali', $b->name_en);
        $this->assertSame(6000, $b->expected_salary);
        $this->assertNull($b->isco_group_id);

        // The same rules as the form: a mobile or an email is needed.
        $this->actingAs($worker)->post('/app/intake', $this->answers(['name_en' => 'Sara', 'phone' => null]))
            ->assertSessionHasErrors(['phone']);
        // The same mobile again: the duplicate question.
        $this->actingAs($worker)->post('/app/intake', $this->answers(['name_en' => 'Omar Aly']))
            ->assertSessionHasErrors(['duplicate']);
        $this->actingAs($worker)->post('/app/intake', $this->answers(['name_en' => 'Omar Aly', 'confirm_duplicate' => true]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Beneficiary::query()->withoutGlobalScopes()->count());
    }

    public function test_with_a_cv_only_the_missing_answers_are_asked_and_the_cv_is_attached(): void
    {
        $worker = User::factory()->employee()->create();

        // A complete CV that the upload page would add automatically — here it waits for the person.
        $r = $this->actingAs($worker)->post('/app/intake/cv', ['file' => $this->docx($this->englishCv(), 'Ahmed.docx')], ['Accept' => 'application/json'])
            ->assertOk()->json();
        $this->assertTrue($r['cv']['read']);
        $this->assertSame('Ahmed Hassan Mahmoud', $r['initial']['name_en']);
        $this->assertSame('found', $r['marks']['name']);
        $this->assertSame('2411.1', $r['initial']['occupation']['esco']['code']);
        $doc = CvDocument::query()->withoutGlobalScopes()->where('uuid', $r['cv']['uuid'])->sole();
        $this->assertSame(CvDocument::REVIEW, $doc->status);
        $this->assertSame(0, Beneficiary::query()->withoutGlobalScopes()->count());

        // The journey sends the answers (the CV's, plus what the person added).
        $initial = $r['initial'];
        $this->actingAs($worker)->post('/app/intake', array_merge(
            array_intersect_key($initial, array_flip(['name_en', 'gender', 'date_of_birth', 'military_status', 'governorate', 'city', 'phone', 'email', 'education_level', 'education', 'skills', 'languages'])),
            [
                'work_history' => array_map(fn ($j) => array_diff_key($j, ['check' => 1]), $initial['work_history']),
                'esco_occupation_id' => $initial['occupation']['esco_id'],
                'expected_salary' => '9000', 'job_type' => 'full_time', 'cv' => $r['cv']['uuid'],
            ],
        ))->assertSessionHasNoErrors()->assertRedirect('/app/beneficiaries/1');

        $b = Beneficiary::query()->withoutGlobalScopes()->sole();
        $this->assertSame('cv_exact', $b->occupation_method);
        $this->assertSame(9000, $b->expected_salary);
        $doc->refresh();
        $this->assertSame(CvDocument::APPROVED, $doc->status);
        $this->assertSame($b->id, $doc->beneficiary_id);

        // Used once only.
        $this->actingAs($worker)->post('/app/intake', $this->answers(['phone' => '01009998887', 'cv' => $r['cv']['uuid']]))
            ->assertSessionHasErrors(['cv']);
    }

    public function test_another_partners_cv_cannot_be_used(): void
    {
        $a = User::factory()->employee()->create();
        $b = User::factory()->employee()->create();
        $r = $this->actingAs($a)->post('/app/intake/cv', ['file' => $this->docx($this->englishCv())], ['Accept' => 'application/json'])->json();

        $this->actingAs($b)->post('/app/intake', $this->answers(['cv' => $r['cv']['uuid']]))->assertSessionHasErrors(['cv']);
        $this->assertSame(0, Beneficiary::query()->withoutGlobalScopes()->count());
    }

    public function test_permissions(): void
    {
        $company = Company::factory()->create();
        $viewer = User::factory()->employee($company)->create(['permissions' => ['beneficiaries.view']]);
        $noUpload = User::factory()->employee($company)->create(['permissions' => ['beneficiaries.view', 'beneficiaries.create']]);

        $this->actingAs($viewer)->get('/app/intake')->assertForbidden();
        $this->actingAs($viewer)->post('/app/intake', $this->answers())->assertForbidden();

        $this->actingAs($noUpload)->get('/app/intake')->assertOk()->assertInertia(fn (Assert $p) => $p->where('can_upload', false));
        $this->actingAs($noUpload)->post('/app/intake/cv', ['file' => $this->docx($this->englishCv())], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($noUpload)->post('/app/intake', $this->answers())->assertSessionHasNoErrors();
    }
}
