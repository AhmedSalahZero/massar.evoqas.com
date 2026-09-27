<?php

namespace Tests\Feature;

use App\Models\Backbone\EscoOccupation;
use App\Models\Beneficiary;
use App\Models\Employer;
use App\Models\Opportunity;
use App\Models\Sector;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use App\Services\Employers\EmployerBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — OpportunitiesTest (Step 12 · Jobs & Training)
//  Location: tests/Feature/OpportunitiesTest.php
//  Scope: docs/SCOPE_JOBS_AND_TRAINING.md
//
//    • a job: title, occupations (real ones, as many as needed),
//      governorates, seats, job type; the employer is OPTIONAL; a known
//      company brings its sector; a typed one with a sector is learned
//    • a training: provider, duration, cost (an amount when paid), dates
//    • the Eligibility section is required (see also EligibilityTest)
//    • only the fields of the kind are kept
//    • the list: open / closed, search, occupation at ANY level, governorate
//    • the page: the market wages next to a job's salary, the history,
//      the deadline reminder; copy keeps the details
//    • a job is never found at a training's address (and the other way)
//    • the profile and CV Bank offer open jobs and trainings; the old
//      Assessments pages are gone; Jobs and Training are no longer "Soon"
//    • the Super Admin sees counts only
// ══════════════════════════════════════════════════════════════════

class OpportunitiesTest extends TestCase
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

        Sector::query()->forceCreate(['code' => 'SRV', 'parent' => null, 'name_en' => 'Service', 'name_ar' => 'خدمي', 'sort' => 1]);
        Sector::query()->forceCreate(['code' => 'S01', 'parent' => 'SRV', 'name_en' => 'Banking', 'name_ar' => 'البنوك', 'sort' => 2]);
        Sector::query()->forceCreate(['code' => 'S02', 'parent' => 'SRV', 'name_en' => 'Food services', 'name_ar' => 'خدمات الطعام', 'sort' => 3]);
        Employer::query()->forceCreate(['company_id' => null, 'name_en' => 'Nile Bank', 'names' => EmployerBook::namesOf('Nile Bank', null, null),
            'sub_sector' => 'S01', 'country' => 'EG', 'source' => 'test']);
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

    private function rules(): array
    {
        return [['type' => 'age', 'mode' => 'must', 'min' => 18, 'max' => 35]];
    }

    private function jobData(array $overrides = []): array
    {
        return array_merge([
            'title'         => 'Junior accountant',
            'description'   => 'Bookkeeping, 9 to 5.',
            'occupations'   => ['esco:2411.1'],
            'governorates'  => ['cai', 'giz'],
            'city'          => 'Nasr City',
            'seats'         => 3,
            'deadline'      => '2026-10-15',
            'job_type'      => 'full_time',
            'salary_from'   => '6,000',
            'salary_to'     => '8000',
            'eligible_from' => 70,
            'check_from'    => 50,
            'rules'         => $this->rules(),
        ], $overrides);
    }

    private function trainingData(array $overrides = []): array
    {
        return array_merge([
            'title'          => 'ICDL for beginners',
            'occupations'    => ['isco:24'],
            'governorates'   => ['alx'],
            'seats'          => 25,
            'provider'       => 'Nile Skills',
            'duration_value' => 6,
            'duration_unit'  => 'weeks',
            'format'         => 'mixed',
            'cost_type'      => 'free',
            'certificate'    => 'ICDL certificate',
            'starts_on'      => '2026-11-01',
            'ends_on'        => '2026-12-15',
            'eligible_from'  => 70,
            'check_from'     => 50,
            'rules'          => $this->rules(),
        ], $overrides);
    }

    private function make(User $worker, string $section, array $data): Opportunity
    {
        $this->actingAs($worker)->post("/app/$section", $data)->assertSessionHasNoErrors();
        $this->later();

        return Opportunity::withoutGlobalScopes()->where('company_id', $worker->company_id)->latest('id')->first();
    }

    // ── A job ────────────────────────────────────────────────────────

    public function test_a_job_is_checked_and_saved_with_its_details(): void
    {
        $worker = User::factory()->employee()->create();

        $this->actingAs($worker)->post('/app/jobs', $this->jobData([
            'title' => '', 'occupations' => ['esco:9999.9'], 'governorates' => [], 'seats' => 0, 'job_type' => '',
            'salary_from' => '9000', 'salary_to' => '8000',
        ]))->assertSessionHasErrors(['title', 'occupations.0', 'governorates', 'seats', 'job_type', 'salary_to']);
        $this->later();
        $this->actingAs($worker)->post('/app/jobs', $this->jobData(['occupations' => []]))->assertSessionHasErrors('occupations');
        $this->assertSame(0, Opportunity::withoutGlobalScopes()->count());

        // No employer at all is fine (an NGO can post without naming the company).
        $this->later();
        $o = $this->make($worker, 'jobs', $this->jobData(['provider' => 'ignored for a job', 'certificate' => 'ignored']));
        $this->assertSame(['job', 'open', 'Junior accountant', 3], [$o->kind, $o->status, $o->title, $o->seats]);
        $this->assertSame([null, null, 6000, 8000], [$o->employer, $o->employer_id, $o->salary_from, $o->salary_to]);
        $this->assertNull($o->provider);
        $this->assertNull($o->certificate);
        $this->assertSame(['cai', 'giz'], $o->governorates);
        $this->assertSame('created', $o->history[0]['action']);
        // Every level the occupation sits in, for the list filter.
        $this->assertSame('|isco:2|isco:24|isco:241|isco:2411|enoc:2411|esco:2411.1|', $o->occupation_keys);
    }

    public function test_the_employer_a_known_company_brings_its_sector_a_typed_one_is_learned(): void
    {
        $worker = User::factory()->employee()->create();
        $bank = Employer::query()->where('name_en', 'Nile Bank')->first();

        $o = $this->make($worker, 'jobs', $this->jobData(['employer' => 'Nile Bank', 'employer_id' => $bank->id]));
        $this->assertSame([$bank->id, 'S01'], [$o->employer_id, $o->sub_sector]);

        $o = $this->make($worker, 'jobs', $this->jobData(['employer' => 'Koshary Corner', 'sub_sector' => 'S02']));
        $this->assertNull($o->employer_id);
        $learned = Employer::query()->where('company_id', $worker->company_id)->where('name_en', 'Koshary Corner')->first();
        $this->assertSame('S02', $learned?->sub_sector);

        // Another partner's company can never be linked.
        $theirs = Employer::query()->forceCreate(['company_id' => User::factory()->employee()->create()->company_id, 'name_en' => 'Secret Co',
            'names' => 'secret co', 'sub_sector' => 'S01', 'country' => 'EG']);
        $this->actingAs($worker)->post('/app/jobs', $this->jobData(['employer' => 'Secret Co', 'employer_id' => $theirs->id]))
            ->assertSessionHasErrors('employer_id');
    }

    // ── A training ───────────────────────────────────────────────────

    public function test_a_training_is_checked_and_saved_with_its_details(): void
    {
        $worker = User::factory()->employee()->create();
        $mate = User::factory()->employee($worker->company)->create();
        $stranger = User::factory()->employee()->create();

        $this->actingAs($worker)->post('/app/training', $this->trainingData([
            'provider' => '', 'cost_type' => 'paid', 'cost_amount' => '', 'duration_value' => 6, 'duration_unit' => '',
            'ends_on' => '2026-10-01', 'contact_user_id' => $stranger->id,
        ]))->assertSessionHasErrors(['provider', 'cost_amount', 'duration_unit', 'ends_on', 'contact_user_id']);
        $this->later();

        $o = $this->make($worker, 'training', $this->trainingData(['contact_user_id' => $mate->id, 'job_type' => 'full_time', 'salary_from' => 5000]));
        $this->assertSame(['training', 'Nile Skills', 6, 'weeks', 'mixed', 'free', null], [$o->kind, $o->provider, $o->duration_value, $o->duration_unit, $o->format, $o->cost_type, $o->cost_amount]);
        $this->assertSame([null, null], [$o->job_type, $o->salary_from]);   // job fields are not kept on a training
        $this->assertSame($mate->id, $o->contact_user_id);
        $this->assertSame('|isco:2|isco:24|', $o->occupation_keys);

        $paid = $this->make($worker, 'training', $this->trainingData(['cost_type' => 'paid', 'cost_amount' => '1,500']));
        $this->assertSame(1500, $paid->cost_amount);
    }

    // ── The list ─────────────────────────────────────────────────────

    public function test_the_list_filters_by_occupation_at_any_level_and_by_governorate(): void
    {
        $worker = User::factory()->employee()->create();
        $this->make($worker, 'jobs', $this->jobData());                                                   // ESCO 2411.1, Cairo + Giza
        $this->make($worker, 'jobs', $this->jobData(['title' => 'Nurse aide', 'occupations' => ['isco:3221'], 'governorates' => ['alx']]));
        $this->make($worker, 'training', $this->trainingData());                                          // not in the Jobs list

        $list = fn (string $q) => $this->actingAs($worker)->get('/app/jobs'.$q);
        $list('')->assertInertia(fn (Assert $p) => $p->component('App/Opportunities/Index')->where('kind', 'job')->has('list.data', 2)->where('tabs.open', 2));
        $list('?occ=isco:24')->assertInertia(fn (Assert $p) => $p->has('list.data', 1)->where('list.data.0.title', 'Junior accountant'));
        $list('?occ=enoc:2411')->assertInertia(fn (Assert $p) => $p->has('list.data', 1));
        $list('?occ=esco:2411.1')->assertInertia(fn (Assert $p) => $p->has('list.data', 1));
        $list('?occ=isco:3')->assertInertia(fn (Assert $p) => $p->has('list.data', 1)->where('list.data.0.title', 'Nurse aide'));
        $list('?governorate=giz')->assertInertia(fn (Assert $p) => $p->has('list.data', 1)->where('list.data.0.title', 'Junior accountant'));
        $list('?q=nurse')->assertInertia(fn (Assert $p) => $p->has('list.data', 1));
        $list('?tab=closed')->assertInertia(fn (Assert $p) => $p->has('list.data', 0));

        $this->actingAs($worker)->get('/app/training')->assertInertia(fn (Assert $p) => $p->where('kind', 'training')->has('list.data', 1)
            ->where('list.data.0.provider', 'Nile Skills'));
    }

    // ── The page ─────────────────────────────────────────────────────

    public function test_the_page_the_market_the_history_the_deadline_and_copy(): void
    {
        $worker = User::factory()->employee()->create();
        $o = $this->make($worker, 'jobs', $this->jobData(['deadline' => '2026-09-01']));

        $this->actingAs($worker)->get("/app/jobs/{$o->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('App/Opportunities/Show')
                ->where('opportunity.title', 'Junior accountant')
                ->where('opportunity.deadline_passed', true)
                ->where('opportunity.occupations.0.value', 'esco:2411.1')
                ->where('opportunity.occupations.0.block.isco.code', '2411')
                ->where('opportunity.occupations.0.block.enoc.code', '2411')
                ->where('market.groups.0.code', '2411')
                ->where('counts.total', 0));

        // Changing the details only: "edited"; changing the rules: "rules_changed" and a new version.
        $this->actingAs($worker)->patch("/app/jobs/{$o->id}", $this->jobData(['seats' => 5]))->assertSessionHasNoErrors();
        $this->later();
        $this->assertSame([5, 1, 'edited'], [$o->refresh()->seats, $o->rules_version, collect($o->history)->last()['action']]);
        $this->actingAs($worker)->patch("/app/jobs/{$o->id}", $this->jobData(['seats' => 5, 'eligible_from' => 80]))->assertSessionHasNoErrors();
        $this->later();
        $this->assertSame([2, 'rules_changed'], [$o->refresh()->rules_version, collect($o->history)->last()['action']]);

        // Copy keeps the details and the rules, opens the form.
        $this->actingAs($worker)->post("/app/jobs/{$o->id}/copy")->assertRedirect();
        $copy = Opportunity::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame(['Junior accountant (copy)', 5, 6000, 'job', 1, 'copied'], [$copy->title, $copy->seats, $copy->salary_from, $copy->kind, $copy->rules_version, $copy->history[0]['action']]);

        // Open again after closing.
        $this->actingAs($worker)->post("/app/jobs/{$o->id}/close", ['reason' => 'cancelled']);
        $this->later();
        $this->actingAs($worker)->post("/app/jobs/{$o->id}/reopen");
        $this->assertSame(['open', null, 'reopened'], [$o->refresh()->status, $o->close_reason, collect($o->history)->last()['action']]);
    }

    public function test_a_job_is_never_found_at_a_training_address(): void
    {
        $worker = User::factory()->employee()->create();
        $job = $this->make($worker, 'jobs', $this->jobData());
        $training = $this->make($worker, 'training', $this->trainingData());

        $this->actingAs($worker)->get("/app/training/{$job->id}")->assertNotFound();
        $this->actingAs($worker)->get("/app/jobs/{$training->id}")->assertNotFound();
        $this->actingAs($worker)->patch("/app/training/{$job->id}", $this->trainingData())->assertNotFound();
        $this->assertSame('job', $job->refresh()->kind);
    }

    // ── Where it is used ─────────────────────────────────────────────

    public function test_the_profile_the_cv_bank_the_menu_and_the_super_admin(): void
    {
        $worker = User::factory()->employee()->create();
        $job = $this->make($worker, 'jobs', $this->jobData());
        $training = $this->make($worker, 'training', $this->trainingData());
        $closed = $this->make($worker, 'jobs', $this->jobData(['title' => 'Old job']));
        $this->actingAs($worker)->post("/app/jobs/{$closed->id}/close", ['reason' => 'filled']);
        $this->later();

        $this->actingAs($worker)->post('/app/beneficiaries', [
            'name_en' => 'Sara Mostafa', 'name_ar' => 'سارة مصطفى', 'gender' => 'female', 'date_of_birth' => '1999-04-12',
            'governorate' => 'cai', 'phone' => '01001234567', 'esco_occupation_id' => EscoOccupation::where('code', '2411.1')->value('id'),
            'confirm_duplicate' => true,
        ])->assertSessionHasNoErrors();
        $b = Beneficiary::withoutGlobalScopes()->where('company_id', $worker->company_id)->first();
        $this->later();

        // The profile offers the OPEN jobs and trainings only.
        $this->actingAs($worker)->get("/app/beneficiaries/{$b->number}")
            ->assertInertia(fn (Assert $p) => $p->has('opportunities', 2)
                ->where('opportunities', fn ($list) => collect($list)->pluck('kind')->sort()->values()->all() === ['job', 'training']));
        $this->actingAs($worker)->post("/app/beneficiaries/{$b->number}/eligibility", ['opportunity_id' => $job->id])->assertSessionHas('success');
        $this->actingAs($worker)->get("/app/beneficiaries/{$b->number}")
            ->assertInertia(fn (Assert $p) => $p->where('eligibility.0.opportunity.kind', 'job')->where('eligibility.0.opportunity.title', 'Junior accountant'));

        $this->actingAs($worker)->get('/app/cv-bank?q=sara')->assertInertia(fn (Assert $p) => $p->has('opportunities', 2));

        // Jobs and Training are real pages now; Assessments is gone; Matches is real since Step 13.
        $this->actingAs($worker)->get('/app/jobs')->assertInertia(fn (Assert $p) => $p->component('App/Opportunities/Index'));
        $this->actingAs($worker)->get('/app/training/create')->assertInertia(fn (Assert $p) => $p->component('App/Opportunities/Form')
            ->where('kind', 'training')->where('workspace', $worker->company->name));
        $this->actingAs($worker)->get('/app/assessments')->assertNotFound();
        $this->actingAs($worker)->get('/app/matches')->assertInertia(fn (Assert $p) => $p->component('App/Matches/Index'));

        // The Super Admin: counts only.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get('/admin/dashboard')->assertInertia(fn (Assert $p) => $p
            ->where('stats.eligibility.jobs', 1)->where('stats.eligibility.trainings', 1)
            ->where('stats.eligibility.seats', 28)->where('stats.eligibility.assessed', 1));
        $this->assertSame($training->id, Opportunity::withoutGlobalScopes()->where('kind', 'training')->value('id'));
    }
}
