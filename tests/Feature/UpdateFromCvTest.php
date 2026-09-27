<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\Company;
use App\Models\CvDocument;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesCvFiles;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — UpdateFromCvTest (Step 7 · "Update from this CV")
//  Location: tests/Feature/UpdateFromCvTest.php
//
//    • find the person by name, mobile or number (this workspace only)
//    • the profile and the newer CV side by side: additions ticked,
//      replacements not, a mobile another profile uses cannot be taken
//    • only the ticked changes are made, the CV is attached, and the
//      history says "updated from CV …" and by whom
// ══════════════════════════════════════════════════════════════════

class UpdateFromCvTest extends TestCase
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

    /** The sample CV, a year later: a new job with duties, a new skill, a new email. */
    private function newerCv(): array
    {
        $cv = $this->englishCv();
        $cv[2] = 'Nasr City, Cairo | +20 100 555 1234 | ahmed.new@example.com';
        $cv[6] = 'Accountant — Delta Trading | Mar 2021 – Dec 2023';
        array_splice($cv, 6, 0, ['Chief Accountant — Delta Trading | Jan 2024 – Present', '- Leading a team of 4 accountants']);
        $i = array_search('Microsoft Excel, SAP, Budget planning', $cv, true);
        $cv[$i] = 'Microsoft Excel, SAP, Budget planning, Power BI';

        return $cv;
    }

    public function test_the_person_is_found_by_name_mobile_or_number_in_this_workspace_only(): void
    {
        $worker = User::factory()->employee()->create();
        $this->upload($worker, $this->docx($this->englishCv()));
        $stranger = User::factory()->employee(Company::factory()->create())->create();

        foreach (['Ahmed', 'hassan mahmoud', '01005551234', '1005551234', '1'] as $q) {
            $this->actingAs($worker)->getJson('/app/review-queue/profiles?q='.urlencode($q))->assertOk()->assertJsonPath('0.number', 1);
        }
        $this->actingAs($stranger)->getJson('/app/review-queue/profiles?q=Ahmed')->assertOk()->assertExactJson([]);
    }

    public function test_the_newer_cv_updates_only_what_the_reviewer_ticks(): void
    {
        $worker = User::factory()->employee()->create(['name' => 'Mona Reviewer']);
        $first = $this->upload($worker, $this->docx($this->englishCv()));
        $this->assertSame('added', $first['status']);
        $newer = $this->upload($worker, $this->docx($this->newerCv(), 'Ahmed CV 2025.docx'));
        $this->assertSame('duplicate', $newer['status']);

        $rows = [];
        $this->actingAs($worker)->get("/app/review-queue/{$newer['uuid']}/update/1")
            ->assertInertia(function (Assert $p) use (&$rows) {
                $p->component('App/Cv/Update')->where('profile.number', 1);
                $rows = collect($p->toArray()['props']['rows'])->keyBy('id')->all();
            });

        $job = collect($rows)->first(fn ($r) => $r['group'] === 'jobs' && $r['kind'] === 'add');
        $skill = collect($rows)->first(fn ($r) => $r['group'] === 'skills');
        $email = $rows['f:email'];
        $this->assertSame('Chief Accountant', $job['new']['title']);
        $this->assertTrue($job['ticked']);
        $this->assertSame(['Power BI', true], [$skill['new'], $skill['ticked']]);
        $this->assertSame(['replace', false, 'ahmed.hassan@example.com', 'ahmed.new@example.com'], [$email['kind'], $email['ticked'], $email['old'], $email['new']]);
        // The job that is already on the profile is not offered again.
        $this->assertCount(1, collect($rows)->where('group', 'jobs'));

        $this->actingAs($worker)->post("/app/review-queue/{$newer['uuid']}/update/1", ['ticked' => [$job['id'], $skill['id']]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $b = Beneficiary::query()->withoutGlobalScopes()->sole();
        $this->assertCount(3, $b->work_history);
        $this->assertContains(['title' => 'Chief Accountant', 'employer' => 'Delta Trading', 'location' => null, 'from' => '2024-01', 'to' => null, 'current' => true,
            'responsibilities' => ['Leading a team of 4 accountants'], 'country' => 'EG'], $b->work_history);
        $this->assertContains('Power BI', $b->skills);
        $this->assertSame('ahmed.hassan@example.com', $b->email);          // not ticked: not changed
        $this->assertGreaterThan(72, $b->experience_months);              // counted again with the new job

        $doc = CvDocument::query()->withoutGlobalScopes()->where('uuid', $newer['uuid'])->sole();
        $this->assertSame([CvDocument::ATTACHED, $b->id], [$doc->status, $doc->beneficiary_id]);

        $change = BeneficiaryChange::query()->withoutGlobalScopes()->where('action', 'updated')->sole();
        $this->assertSame(['Ahmed CV 2025.docx', 'Mona Reviewer'], [$change->changes['_from_cv'], $change->user_name]);
        $this->actingAs($worker)->get('/app/beneficiaries/1')->assertInertia(fn (Assert $p) => $p
            ->where('history.0.from_cv', 'Ahmed CV 2025.docx')->missing('history.0.changes._from_cv'));

        // Decided once: it cannot be done again.
        $this->actingAs($worker)->post("/app/review-queue/{$newer['uuid']}/update/1", ['ticked' => [$job['id']]])->assertSessionHasErrors('cv');
    }

    public function test_a_mobile_another_profile_uses_cannot_be_taken_and_nothing_ticked_just_attaches(): void
    {
        $worker = User::factory()->employee()->create();
        $this->upload($worker, $this->docx($this->englishCv()));                              // #1, mobile …1234
        $other = $this->englishCv();
        $other[0] = 'Karim Adel Fathy';
        $other[2] = 'Nasr City, Cairo | +20 100 555 9999 | karim@example.com';
        $this->upload($worker, $this->docx($other, 'karim.docx'));                           // #2, mobile …9999

        // A CV of #1 that carries #2's mobile.
        $cv = $this->englishCv();
        $cv[2] = 'Nasr City, Cairo | +20 100 555 9999 | ahmed.hassan@example.com';
        $newer = $this->upload($worker, $this->docx($cv, 'mixed.docx'));

        $this->actingAs($worker)->get("/app/review-queue/{$newer['uuid']}/update/1")
            ->assertInertia(fn (Assert $p) => $p->where('rows.0.id', 'f:phone')->where('rows.0.blocked', 'used_by_other')->where('rows.0.ticked', false));

        // Even if it is sent ticked, it is not taken; with nothing else, the CV is only attached.
        $this->actingAs($worker)->post("/app/review-queue/{$newer['uuid']}/update/1", ['ticked' => ['f:phone']])->assertSessionHasNoErrors();
        $this->assertSame('01005551234', Beneficiary::query()->withoutGlobalScopes()->where('number', 1)->value('phone'));
        $this->assertSame(CvDocument::ATTACHED, CvDocument::query()->withoutGlobalScopes()->where('uuid', $newer['uuid'])->value('status'));
        $this->assertSame(0, BeneficiaryChange::query()->withoutGlobalScopes()->where('action', 'updated')->count());
    }

    public function test_another_partners_profile_can_never_be_updated(): void
    {
        $worker = User::factory()->employee()->create();
        $lines = $this->newerCv();
        $lines[5] = '#Where I Worked';                                                        // an unknown heading: it waits for review
        $cv = $this->upload($worker, $this->docx($lines));
        $this->assertSame('review', $cv['status']);
        $stranger = User::factory()->employee(Company::factory()->create())->create();
        $this->upload($stranger, $this->docx($this->englishCv()));                          // the stranger's #1

        $this->actingAs($worker)->get("/app/review-queue/{$cv['uuid']}/update/5")->assertNotFound();
        $this->actingAs($stranger)->get("/app/review-queue/{$cv['uuid']}/update/1")->assertNotFound();
        $this->actingAs($stranger)->post("/app/review-queue/{$cv['uuid']}/update/1", ['ticked' => []])->assertNotFound();
    }
}
