<?php

namespace Tests\Feature;

use App\Models\Backbone\EscoOccupation;
use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\Company;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — BeneficiariesTest (Step 4 · beneficiary profiles)
//  Location: tests/Feature/BeneficiariesTest.php
//
//  On the same sample backbone as the occupation tests (2411
//  Accountants with ESCO jobs and wages, 3221 Nursing with no ESCO):
//    • registering, with the occupation stored at ESCO level, the
//      mobile tidied, experience worked out, and the history row
//    • the profile: market panel, expected-salary check, history
//    • a group ESCO does not detail can be chosen as a group; one it
//      does detail cannot
//    • editing records who changed what; saving without changes
//      records nothing
//    • PARTNER SEPARATION: another partner can never list, open,
//      edit or find a beneficiary, and each partner numbers its own
//    • the minimum details, the mobile check, men-only military status
//    • the same mobile in one workspace needs confirming; in another
//      partner's workspace it is never even checked
//    • search (Arabic spelling variants) and filters
//    • the occupation picker
//    • permissions
// ══════════════════════════════════════════════════════════════════

class BeneficiariesTest extends TestCase
{
    use RefreshDatabase;
    use WritesBackboneSamples;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSamples();
        BackboneImporter::make()->run();
        MarketImporter::make()->run();
    }

    protected function tearDown(): void
    {
        $this->tearDownSamples();
        parent::tearDown();
    }

    private function esco(string $code): int
    {
        return EscoOccupation::where('code', $code)->value('id');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name_ar'         => 'سارة مصطفى',
            'name_en'         => 'Sara Mostafa',
            'gender'          => 'female',
            'date_of_birth'   => '1999-04-12',
            'military_status' => 'completed',          // must be dropped: women are not asked
            'governorate'     => 'cai',
            'city'            => 'Nasr City',
            'phone'           => '+20 100 123 4567',
            'email'           => 'Sara.M@Example.com',
            'education_level' => 'university',
            'education'       => [['qualification' => 'BCom', 'field' => 'Accounting', 'institution' => 'Cairo University', 'year' => 2020], ['qualification' => '', 'field' => '']],
            'work_history'    => [
                ['title' => 'Junior accountant', 'employer' => 'Delta Trading', 'from' => '2020-01', 'to' => '2021-12', 'current' => false],
                ['title' => 'Accountant', 'employer' => 'Nile Foods', 'from' => '2021-06', 'to' => '2022-05', 'current' => false],
            ],
            'skills'          => ['Excel', 'excel', ' SAP '],
            'languages'       => [['code' => 'ar', 'level' => 'native'], ['code' => 'en', 'level' => 'good']],
            'esco_occupation_id' => $this->esco('2411.1'),
            'expected_salary' => '8,000',
            'job_type'        => 'full_time',
        ], $overrides);
    }

    public function test_a_case_worker_registers_a_beneficiary(): void
    {
        $worker = User::factory()->employee()->create();

        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect('/app/beneficiaries/1');

        $b = Beneficiary::sole();
        $this->assertSame($worker->company_id, $b->company_id);
        $this->assertSame(1, $b->number);
        $this->assertSame('01001234567', $b->phone);
        $this->assertSame('sara.m@example.com', $b->email);
        $this->assertNull($b->military_status);
        $this->assertSame(['Excel', 'SAP'], $b->skills);
        $this->assertCount(1, $b->education);
        $this->assertSame(29, $b->experience_months);           // Jan 2020 – May 2022, overlap counted once
        $this->assertSame(8000, $b->expected_salary);

        // Occupation: stored at the ESCO level, with its unit group, and how it was chosen.
        $this->assertSame($this->esco('2411.1'), $b->esco_occupation_id);
        $this->assertSame('2411', $b->isco_code);
        $this->assertSame('manual', $b->occupation_method);
        $this->assertSame($worker->id, $b->occupation_set_by);

        // Who did it.
        $this->assertSame($worker->id, $b->created_by);
        $change = BeneficiaryChange::sole();
        $this->assertSame(BeneficiaryChange::CREATED, $change->action);
        $this->assertSame($worker->id, $change->user_id);
        $this->assertSame($worker->name, $change->user_name);
        $this->assertSame($b->company_id, $change->company_id);
    }

    public function test_the_profile_shows_the_market_panel_the_salary_check_and_the_history(): void
    {
        $worker = User::factory()->employee()->create();
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload());

        $this->actingAs($worker)->get('/app/beneficiaries/1')->assertOk()
            ->assertInertia(fn ($page) => $page->component('App/Beneficiaries/Show')
                ->where('beneficiary.name_en', 'Sara Mostafa')
                ->where('beneficiary.created_by', $worker->name)
                ->where('occupation.esco.code', '2411.1')
                ->where('occupation.esco.title_ar', 'محاسبة')          // her form of the title
                ->where('occupation.enoc.code', '2411')
                ->where('occupation.isco.code', '2411')
                ->where('occupation.set_by', $worker->name)
                ->where('market.profile.wage_avg', 6740)
                ->where('salary.status', 'ok')
                ->where('salary.references.0.key', 'avg')
                ->where('salary.references.0.diff_pct', 19)
                ->where('salary.references.0.band', 'above')
                ->where('salary.references.1.key', 'private')
                ->where('salary.references.1.value', 6112)
                ->has('history', 1)
                ->where('history.0.action', 'created'));

        // Close to the average → "close"; no expected salary → says so.
        $b = Beneficiary::sole();
        $this->actingAs($worker)->patch('/app/beneficiaries/1', $this->payload(['expected_salary' => 7000]));
        $this->actingAs($worker)->get('/app/beneficiaries/1')
            ->assertInertia(fn ($page) => $page->where('salary.references.0.band', 'close')->where('salary.references.1.band', 'close'));
        $this->actingAs($worker)->patch('/app/beneficiaries/1', $this->payload(['expected_salary' => '']));
        $this->actingAs($worker)->get('/app/beneficiaries/1')
            ->assertInertia(fn ($page) => $page->where('salary.status', 'no_expected'));
        $this->assertNull($b->fresh()->expected_salary);
    }

    public function test_a_group_esco_does_not_detail_can_be_chosen_as_a_group_only(): void
    {
        $worker = User::factory()->employee()->create();

        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload(['esco_occupation_id' => null, 'occupation_unit' => '3221']))
            ->assertSessionHasNoErrors();
        $b = Beneficiary::sole();
        $this->assertSame('3221', $b->isco_code);
        $this->assertNull($b->esco_occupation_id);
        $this->actingAs($worker)->get('/app/beneficiaries/1')
            ->assertInertia(fn ($page) => $page->where('occupation.group_only', true)->where('occupation.esco', null)
                ->where('market.profile.wage_avg', 4000));

        // 2411 has detailed ESCO jobs, so it must be chosen at that level.
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload([
            'esco_occupation_id' => null, 'occupation_unit' => '2411', 'phone' => '01112223334', 'email' => null,
        ]))->assertSessionHasErrors('occupation');
        $this->assertSame(1, Beneficiary::count());

        // No occupation at all is fine; the salary check asks for one.
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload([
            'esco_occupation_id' => null, 'phone' => '01112223334', 'email' => null,
        ]))->assertSessionHasNoErrors();
        $this->actingAs($worker)->get('/app/beneficiaries/2')
            ->assertInertia(fn ($page) => $page->where('salary.status', 'no_occupation')->where('market', null));
    }

    public function test_editing_records_who_changed_what(): void
    {
        $company = Company::factory()->create();
        $first = User::factory()->employee($company)->create();
        $second = User::factory()->companyAdmin($company)->create();
        $this->actingAs($first)->post('/app/beneficiaries', $this->payload());

        $this->actingAs($second)->patch('/app/beneficiaries/1', $this->payload([
            'governorate'        => 'giz',
            'work_history'       => [['title' => 'Senior accountant', 'employer' => 'Nile Foods', 'from' => '2021-06', 'to' => null, 'current' => true]],
            'esco_occupation_id' => $this->esco('2411.1.1'),
        ]))->assertSessionHasNoErrors()->assertRedirect('/app/beneficiaries/1');

        $b = Beneficiary::sole();
        $this->assertSame('giz', $b->governorate);
        $this->assertSame($first->id, $b->created_by);
        $this->assertSame($second->id, $b->updated_by);
        $this->assertSame($second->id, $b->occupation_set_by);

        $change = BeneficiaryChange::where('action', 'updated')->sole();
        $this->assertSame($second->id, $change->user_id);
        $this->assertSame(['from' => 'cai', 'to' => 'giz'], $change->changes['governorate']);
        $this->assertSame(['from' => '2411.1', 'to' => '2411.1.1'], $change->changes['occupation']);
        $this->assertSame(['changed' => true], $change->changes['work_history']);
        $this->assertArrayNotHasKey('name_en', $change->changes);

        // Saving again without changing anything adds no history.
        $this->actingAs($second)->patch('/app/beneficiaries/1', $this->payload([
            'governorate'        => 'giz',
            'work_history'       => [['title' => 'Senior accountant', 'employer' => 'Nile Foods', 'from' => '2021-06', 'to' => null, 'current' => true]],
            'esco_occupation_id' => $this->esco('2411.1.1'),
        ]))->assertSessionHasNoErrors();
        $this->assertSame(2, BeneficiaryChange::count());

        $this->actingAs($first)->get('/app/beneficiaries/1')
            ->assertInertia(fn ($page) => $page->has('history', 2)->where('history.0.by', $second->name)->where('beneficiary.updated_by', $second->name));
    }

    public function test_job_responsibilities_are_saved_and_checked(): void
    {
        $worker = User::factory()->employee()->create();
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload([
            'work_history' => [[
                'title' => 'Accountant', 'employer' => 'Nile Foods', 'from' => '2021-06', 'to' => null, 'current' => true,
                'responsibilities' => ['  Prepared monthly financial statements ', '', 'Reconciled bank accounts'],
            ]],
        ]))->assertSessionHasNoErrors();
        $b = Beneficiary::sole();
        $this->assertSame(['Prepared monthly financial statements', 'Reconciled bank accounts'], $b->work_history[0]['responsibilities']);

        // Text with one duty per line is accepted too.
        $this->actingAs($worker)->patch('/app/beneficiaries/1', $this->payload([
            'work_history' => [['title' => 'Accountant', 'from' => '2021-06', 'current' => true, 'responsibilities' => "Payroll\n\nMonthly closing"]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['Payroll', 'Monthly closing'], $b->fresh()->work_history[0]['responsibilities']);

        // Too many, or one too long, is refused with a clear message.
        $this->actingAs($worker)->patch('/app/beneficiaries/1', $this->payload([
            'work_history' => [['title' => 'Accountant', 'from' => '2021-06', 'current' => true, 'responsibilities' => array_fill(0, 31, 'Duty')]],
        ]))->assertSessionHasErrors(['work_history.0.responsibilities']);
        $this->actingAs($worker)->patch('/app/beneficiaries/1', $this->payload([
            'work_history' => [['title' => 'Accountant', 'from' => '2021-06', 'current' => true, 'responsibilities' => [str_repeat('x', 501)]]],
        ]))->assertSessionHasErrors(['work_history.0.responsibilities.0']);
    }

    public function test_a_profile_saved_before_responsibilities_existed_shows_no_false_change(): void
    {
        $worker = User::factory()->employee()->create();
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload());
        // A profile from before this update: its jobs have no "responsibilities" at all.
        $b = Beneficiary::sole();
        $b->forceFill(['work_history' => array_map(fn ($j) => array_diff_key($j, ['responsibilities' => 1]), $b->work_history)])->save();

        $this->actingAs($worker)->patch('/app/beneficiaries/1', $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(1, BeneficiaryChange::count());      // only "created": nothing changed
    }

    public function test_partners_never_see_each_others_beneficiaries(): void
    {
        $alamal = User::factory()->employee()->create();
        $other = User::factory()->companyAdmin()->create();
        $this->assertNotSame($alamal->company_id, $other->company_id);

        $this->actingAs($alamal)->post('/app/beneficiaries', $this->payload());

        // The other partner: an empty list, nothing found by name or mobile, and "not found" on every address.
        $this->actingAs($other)->get('/app/beneficiaries')->assertOk()
            ->assertInertia(fn ($page) => $page->where('total', 0)->has('list.data', 0));
        $this->actingAs($other)->get('/app/beneficiaries?q=Sara')
            ->assertInertia(fn ($page) => $page->has('list.data', 0));
        $this->actingAs($other)->get('/app/beneficiaries?q=01001234567')
            ->assertInertia(fn ($page) => $page->has('list.data', 0));
        $this->actingAs($other)->get('/app/beneficiaries/1')->assertNotFound();
        $this->actingAs($other)->get('/app/beneficiaries/1/edit')->assertNotFound();
        $this->actingAs($other)->patch('/app/beneficiaries/1', $this->payload(['name_en' => 'Changed']))->assertNotFound();
        $this->assertSame('Sara Mostafa', Beneficiary::withoutGlobalScopes()->sole()->name_en);
        $this->assertSame(1, BeneficiaryChange::withoutGlobalScopes()->count());

        // The other partner registers the same person: their own record, their own No. 1,
        // and the mobile is not reported as a duplicate across workspaces.
        $this->actingAs($other)->post('/app/beneficiaries', $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect('/app/beneficiaries/1');
        $this->assertSame(2, Beneficiary::withoutGlobalScopes()->count());
        $this->assertEqualsCanonicalizing([1, 1], Beneficiary::withoutGlobalScopes()->pluck('number')->all());

        $this->actingAs($alamal)->get('/app/beneficiaries')
            ->assertInertia(fn ($page) => $page->where('total', 1));
        $this->actingAs($alamal)->get('/app/beneficiaries/1')
            ->assertInertia(fn ($page) => $page->has('history', 1)->where('history.0.by', $alamal->name));

        // The Super Admin has no workspace: /app sends them to the platform area.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get('/app/beneficiaries')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get('/app/beneficiaries/1')->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_minimum_details_and_the_mobile_check(): void
    {
        $worker = User::factory()->employee()->create();

        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload([
            'name_ar' => '', 'name_en' => '', 'phone' => '', 'email' => '', 'governorate' => '', 'gender' => '',
        ]))->assertSessionHasErrors(['name_ar', 'name_en', 'phone', 'email', 'governorate', 'gender']);

        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload(['phone' => '0123', 'email' => null]))
            ->assertSessionHasErrors('phone');
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload(['expected_salary' => '7500.50']))
            ->assertSessionHasErrors('expected_salary');
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload([
            'work_history' => [['title' => 'Clerk', 'from' => '2022-05', 'to' => '2021-01'], ['title' => 'Clerk', 'from' => '2022-05']],
        ]))->assertSessionHasErrors(['work_history.0.to', 'work_history.1.to']);
        $this->assertSame(0, Beneficiary::count());

        // Arabic name only, Arabic digits in the mobile, no email: enough to register.
        $this->actingAs($worker)->post('/app/beneficiaries', [
            'name_ar' => 'خالد علي', 'gender' => 'male', 'military_status' => 'exempted', 'governorate' => 'giz', 'phone' => '٠١٥١٢٣٤٥٦٧٨',
        ])->assertSessionHasNoErrors();
        $b = Beneficiary::sole();
        $this->assertSame('01512345678', $b->phone);
        $this->assertSame('exempted', $b->military_status);
        $this->assertSame(0, $b->experience_months);
    }

    public function test_the_same_mobile_in_one_workspace_needs_confirming(): void
    {
        $company = Company::factory()->create();
        $worker = User::factory()->employee($company)->create();
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload());

        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload(['name_en' => 'Mona Mostafa', 'email' => null]))
            ->assertSessionHasErrors('duplicate');
        $this->assertSame(1, Beneficiary::count());

        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload(['name_en' => 'Mona Mostafa', 'email' => null, 'confirm_duplicate' => true]))
            ->assertSessionHasNoErrors()->assertRedirect('/app/beneficiaries/2');

        // Editing a profile that keeps its (already confirmed) mobile does not ask again.
        $this->actingAs($worker)->patch('/app/beneficiaries/2', $this->payload(['name_en' => 'Mona M.', 'email' => null]))
            ->assertSessionHasNoErrors();
    }

    public function test_search_and_filters(): void
    {
        $worker = User::factory()->employee()->create();
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload(['name_ar' => 'أحمد حسن', 'name_en' => 'Ahmed Hassan', 'gender' => 'male']));
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload([
            'name_ar' => 'منى سالم', 'name_en' => null, 'phone' => '01223334445', 'email' => null, 'governorate' => 'alx',
            'esco_occupation_id' => null, 'occupation_unit' => '3221', 'work_history' => [], 'education_level' => 'secondary_technical',
        ]));
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload([
            'name_ar' => 'ليلى عمر', 'name_en' => 'Laila Omar', 'phone' => '01099887766', 'email' => null, 'esco_occupation_id' => null, 'work_history' => [],
        ]));

        $count = fn (string $query, int $n) => $this->actingAs($worker)->get('/app/beneficiaries'.$query)->assertOk()
            ->assertInertia(fn ($page) => $page->has('list.data', $n));

        $count('', 3);
        $count('?q='.urlencode('احمد'), 1);              // written without the hamza
        $count('?q=hassan', 1);
        $count('?q=0122', 1);                            // part of a mobile
        $count('?q=2', 1);                               // a beneficiary number
        $count('?governorate=alx', 1);
        $count('?gender=female', 2);
        $count('?education=secondary_technical', 1);
        $count('?major=2', 1);                           // occupation, major group 2
        $count('?major=3', 1);
        $count('?major=none', 1);                        // no occupation chosen yet
        $count('?min_years=2', 1);                       // 29 months of experience
        $count('?min_years=3', 0);
        $count('?governorate=zzz', 3);                   // unknown filter values are ignored
    }

    public function test_the_occupation_picker(): void
    {
        $worker = User::factory()->employee()->create();

        $this->actingAs($worker)->getJson('/app/occupation-picker?q='.urlencode('محاسبة').'&gender=female')->assertOk()
            ->assertJsonPath('results.0.block.esco.code', '2411.1')
            ->assertJsonPath('results.0.block.esco.title_ar', 'محاسبة')
            ->assertJsonPath('results.0.block.enoc.code', '2411');

        $this->actingAs($worker)->getJson('/app/occupation-picker?q=nursing')->assertOk()
            ->assertJsonPath('results.0.block.unit_code', '3221')
            ->assertJsonPath('results.0.block.group_only', true);

        // Groups ESCO details are offered as their detailed jobs, never as the group.
        $units = collect($this->actingAs($worker)->getJson('/app/occupation-picker?q=accountant')->json('results'))
            ->where('block.group_only', true)->pluck('block.unit_code');
        $this->assertNotContains('2411', $units);

        $this->actingAs($worker)->getJson('/app/occupation-picker?q=a')->assertOk()->assertJsonPath('results', []);
    }

    public function test_permissions(): void
    {
        $company = Company::factory()->create();
        $none = User::factory()->employee($company)->create(['permissions' => ['occupations.view']]);
        $viewer = User::factory()->employee($company)->create(['permissions' => ['beneficiaries.view']]);
        $worker = User::factory()->employee($company)->create();
        $this->actingAs($worker)->post('/app/beneficiaries', $this->payload());

        $this->actingAs($none)->get('/app/beneficiaries')->assertForbidden();
        $this->actingAs($none)->get('/app/beneficiaries/1')->assertForbidden();
        $this->actingAs($none)->getJson('/app/occupation-picker?q=accountant')->assertForbidden();

        $this->actingAs($viewer)->get('/app/beneficiaries')->assertOk();
        $this->actingAs($viewer)->get('/app/beneficiaries/1')->assertOk();
        $this->actingAs($viewer)->get('/app/beneficiaries/create')->assertForbidden();
        $this->actingAs($viewer)->post('/app/beneficiaries', $this->payload(['phone' => '01011112222', 'email' => null]))->assertForbidden();
        $this->actingAs($viewer)->get('/app/beneficiaries/1/edit')->assertForbidden();
        $this->actingAs($viewer)->patch('/app/beneficiaries/1', $this->payload())->assertForbidden();
        $this->assertSame(1, Beneficiary::count());
    }
}
