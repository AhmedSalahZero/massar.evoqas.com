<?php

namespace Tests\Feature;

use App\Models\Backbone\EscoOccupation;
use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\EligibilityAssessment;
use App\Models\Opportunity;
use App\Models\EligibilityRun;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use App\Services\Eligibility\RunStepper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — EligibilityTest (Step 11's engine, inside Jobs & Training since Step 12)
//  Location: tests/Feature/EligibilityTest.php
//  Scope: docs/SCOPE_ELIGIBILITY_ASSESSMENT.md, docs/SCOPE_JOBS_AND_TRAINING.md
//
//  Every check here goes through a Training Program (the same engine
//  serves Jobs; tests/Feature/OpportunitiesTest.php covers the details).
//
//    • the eligibility checks: the two levels, complete rules,
//      "Counts" points adding up to 100
//    • the result: "Must have" fails → Not eligible; missing → Check;
//      otherwise the partner's two levels decide
//    • every rule type, including "does not apply" (military, women)
//      and occupations in any standard and level
//    • the case worker's decision: reason required, written in the
//      history, "go back to the automatic result"
//    • the profile changes → results follow, a decision is marked
//    • the rules change → results outdated → "check them again"
//    • "Check everyone": the workspace, a CV Bank search; a resent
//      step does nothing twice
//    • closing, copying, deleting only without results
//    • the CV Bank journey-stage filter, the dashboard pipeline, the
//      profile panel
//    • PARTNER SEPARATION and permissions
// ══════════════════════════════════════════════════════════════════

class EligibilityTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->tearDownSamples();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(User $worker, array $overrides = []): Beneficiary
    {
        $data = array_merge([
            'name_ar'         => 'سارة مصطفى',
            'name_en'         => 'Sara Mostafa',
            'gender'          => 'female',
            'date_of_birth'   => '1999-04-12',           // 27
            'governorate'     => 'cai',
            'phone'           => '01001234567',
            'education_level' => 'university',
            'education'       => [['qualification' => 'BCom', 'field' => 'Accounting', 'institution' => 'Cairo University', 'year' => 2020]],
            'work_history'    => [['title' => 'Accountant', 'employer' => 'Nile Foods', 'from' => '2021-01', 'to' => '2023-12', 'current' => false]],
            'skills'          => ['Microsoft Excel', 'SAP'],
            'languages'       => [['code' => 'ar', 'level' => 'native'], ['code' => 'en', 'level' => 'good']],
            'esco_occupation_id' => EscoOccupation::where('code', '2411.1')->value('id'),
            'expected_salary' => '8000',
            'job_type'        => 'full_time',
            'confirm_duplicate' => true,
        ], $overrides);
        $this->actingAs($worker)->post('/app/beneficiaries', $data)->assertSessionHasNoErrors();

        return Beneficiary::withoutGlobalScopes()->where('company_id', $worker->company_id)->orderByDesc('id')->first();
    }

    /** Musts: age 18–29, Cairo or Giza. Counts: university 40, English good 30, Excel 30. */
    private function programData(array $overrides = []): array
    {
        return array_merge([
            'title'         => 'Youth Accounting Training 2026',
            'occupations'   => ['isco:2411'],
            'governorates'  => ['cai', 'giz'],
            'seats'         => 20,
            'provider'      => 'Nile Skills',
            'eligible_from' => 70,
            'check_from'    => 50,
            'rules'         => [
                ['type' => 'age', 'mode' => 'must', 'min' => 18, 'max' => 29],
                ['type' => 'governorate', 'mode' => 'must', 'values' => ['cai', 'giz']],
                ['type' => 'education_level', 'mode' => 'counts', 'points' => 40, 'values' => ['university', 'postgraduate']],
                ['type' => 'language', 'mode' => 'counts', 'points' => 30, 'code' => 'en', 'level' => 'good'],
                ['type' => 'skills', 'mode' => 'counts', 'points' => 30, 'values' => ['Excel'], 'need' => 'all'],
            ],
        ], $overrides);
    }

    private function program(User $worker, array $overrides = []): Opportunity
    {
        $this->actingAs($worker)->post('/app/training', $this->programData($overrides))->assertSessionHasNoErrors();
        $this->later();

        return Opportunity::withoutGlobalScopes()->where('company_id', $worker->company_id)->orderByDesc('id')->first();
    }

    private function check(User $worker, Beneficiary $b, Opportunity $p): EligibilityAssessment
    {
        $this->actingAs($worker)->post("/app/beneficiaries/{$b->number}/eligibility", ['opportunity_id' => $p->id])->assertSessionHasNoErrors();

        return EligibilityAssessment::withoutGlobalScopes()->where('opportunity_id', $p->id)->where('beneficiary_id', $b->id)->firstOrFail();
    }

    /** The double-submit guard blocks the same form for a few seconds: move the clock on. */
    private function later(): void
    {
        Carbon::setTestNow(now()->addSeconds(10));
    }

    private function statuses(EligibilityAssessment $a): array
    {
        return array_map(fn ($r) => $r['rule']['type'].':'.$r['status'], $a->reasons);
    }

    // ── The eligibility section ──────────────────────────────────────

    public function test_the_eligibility_section_is_checked_before_it_is_saved(): void
    {
        $worker = User::factory()->employee()->create();

        $bad = $this->programData(['title' => '', 'eligible_from' => 60, 'check_from' => 70]);
        $bad['rules'][2]['points'] = 20;                        // 20 + 30 + 30 = 80
        $bad['rules'][1]['values'] = [];
        $bad['rules'][0] = ['type' => 'age', 'mode' => 'must', 'min' => 40, 'max' => 20];
        $this->actingAs($worker)->post('/app/training', $bad)
            ->assertSessionHasErrors(['title', 'check_from', 'points_total', 'rules.1.values', 'rules.0.max']);

        // The Eligibility section is REQUIRED: no rule, no job or training.
        $this->later();
        $this->actingAs($worker)->post('/app/training', $this->programData(['rules' => []]))->assertSessionHasErrors('rules');
        $this->later();
        $this->actingAs($worker)->post('/app/training', $this->programData(['rules' => [['type' => 'shoe_size', 'mode' => 'must']]]))
            ->assertSessionHasErrors('rules.0.type');
        $this->assertSame(0, Opportunity::withoutGlobalScopes()->count());

        // Only "Must have" rules: no points needed. Check from = Eligible from: no Check band.
        $p = $this->program($worker, ['eligible_from' => 60, 'check_from' => 60, 'rules' => [['type' => 'gender', 'mode' => 'must', 'value' => 'female', 'points' => 99]]]);
        $this->assertSame([['type' => 'gender', 'mode' => 'must', 'points' => 0, 'value' => 'female']], $p->rules);
        $this->assertSame(['open', 'training'], [$p->status, $p->kind]);
        $this->assertSame($worker->company_id, $p->company_id);
    }

    // ── The result ───────────────────────────────────────────────────

    public function test_every_rule_passes_and_the_person_is_eligible(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);
        $a = $this->check($worker, $this->person($worker), $p);

        $this->assertSame(100, $a->score);
        $this->assertSame('eligible', $a->auto_result);
        $this->assertSame('eligible', $a->result);
        $this->assertSame(['age:pass', 'governorate:pass', 'education_level:pass', 'language:pass', 'skills:pass'], $this->statuses($a));
        $this->assertSame(27, $a->reasons[0]['value']);
        $this->assertSame(['found' => ['Excel'], 'lacking' => []], $a->reasons[4]['value']);   // "Microsoft Excel" has the word "Excel"
        $this->assertSame($worker->name, $a->checked_by_name);
    }

    public function test_a_failed_must_have_rule_means_not_eligible_whatever_the_score(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);
        $a = $this->check($worker, $this->person($worker, ['governorate' => 'alx']), $p);

        $this->assertSame(100, $a->score);
        $this->assertSame('not_eligible', $a->result);
        $this->assertSame('governorate:fail', $this->statuses($a)[1]);
    }

    public function test_missing_information_means_check_and_is_never_guessed(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);
        $a = $this->check($worker, $this->person($worker, ['date_of_birth' => null]), $p);

        $this->assertSame('check', $a->result);
        $this->assertSame('age:missing', $this->statuses($a)[0]);
        $this->assertNull($a->reasons[0]['value']);

        // Missing beats a high score, but a failed "Must have" beats missing.
        $b = $this->person($worker, ['date_of_birth' => null, 'governorate' => 'asw', 'phone' => '01001234568']);
        $this->assertSame('not_eligible', $this->check($worker, $b, $p)->result);
    }

    public function test_the_partners_levels_decide_between_eligible_check_and_not_eligible(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);

        // No Excel: 70 → Eligible (from 70).
        $a = $this->check($worker, $this->person($worker, ['skills' => ['SAP']]), $p);
        $this->assertSame([70, 'eligible'], [$a->score, $a->result]);
        $this->assertSame(['found' => [], 'lacking' => ['Excel']], $a->reasons[4]['value']);

        // Basic English and no Excel: 40 → Not eligible (Check from 50).
        $b = $this->person($worker, ['skills' => ['SAP'], 'languages' => [['code' => 'en', 'level' => 'basic']], 'phone' => '01001234568']);
        $a = $this->check($worker, $b, $p);
        $this->assertSame([40, 'not_eligible'], [$a->score, $a->result]);

        // Secondary technical, English good, Excel: 60 → Check.
        $c = $this->person($worker, ['education_level' => 'secondary_technical', 'phone' => '01001234569']);
        $a = $this->check($worker, $c, $p);
        $this->assertSame([60, 'check'], [$a->score, $a->result]);
    }

    public function test_the_other_rules_including_does_not_apply(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker, ['rules' => [
            ['type' => 'military', 'mode' => 'must', 'values' => ['completed', 'exempted']],
            ['type' => 'occupation', 'mode' => 'counts', 'points' => 25, 'values' => ['isco:24']],
            ['type' => 'field_of_study', 'mode' => 'counts', 'points' => 25, 'words' => ['محاسبة', 'accounting']],
            ['type' => 'experience', 'mode' => 'counts', 'points' => 25, 'min_years' => 2],
            ['type' => 'salary', 'mode' => 'counts', 'points' => 25, 'max' => 7000],
        ]]);

        // A woman: the military rule does not apply. 2411.1 is inside ISCO-08 24. 3 years. 8,000 > 7,000.
        $a = $this->check($worker, $this->person($worker), $p);
        $this->assertSame(['military:na', 'occupation:pass', 'field_of_study:pass', 'experience:pass', 'salary:fail'], $this->statuses($a));
        $this->assertSame([75, 'eligible'], [$a->score, $a->result]);

        // A man with no military status: missing → Check.
        $man = $this->person($worker, ['gender' => 'male', 'military_status' => null, 'name_en' => 'Omar', 'phone' => '01001234568']);
        $a = $this->check($worker, $man, $p);
        $this->assertSame('military:missing', $this->statuses($a)[0]);
        $this->assertSame('check', $a->result);

        // Occupation in other standards and levels.
        foreach (['esco:2411.1' => 'pass', 'enoc:2411' => 'pass', 'isco:2' => 'pass', 'isco:3' => 'fail', 'esco:2411.9' => 'fail'] as $value => $status) {
            $p->forceFill(['rules' => [['type' => 'occupation', 'mode' => 'must', 'points' => 0, 'values' => [$value]]]])->save();
            $a = app(\App\Services\Eligibility\Assessor::class)->assess($p, Beneficiary::withoutGlobalScopes()->find($man->id));
            $this->assertSame("occupation:$status", $this->statuses($a)[0], $value);
        }
    }

    // ── The case worker's decision ───────────────────────────────────

    public function test_a_decision_needs_a_reason_and_goes_into_the_history(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);
        $b = $this->person($worker, ['governorate' => 'alx']);
        $a = $this->check($worker, $b, $p);
        $this->assertSame('not_eligible', $a->result);

        $this->actingAs($worker)->post("/app/eligibility/{$a->id}/decide", ['decision' => 'eligible', 'reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($worker)->post("/app/eligibility/{$a->id}/decide", ['decision' => 'auto', 'reason' => 'x'])->assertSessionHasErrors('decision');
        $this->actingAs($worker)->post("/app/eligibility/{$a->id}/decide", ['decision' => 'eligible', 'reason' => 'Moving to Giza next month'])
            ->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertSame(['eligible', 'not_eligible', 'eligible'], [$a->decision, $a->auto_result, $a->result]);
        $this->assertSame('Moving to Giza next month', $a->decision_reason);
        $this->assertSame($worker->name, $a->decided_by_name);

        $h = BeneficiaryChange::withoutGlobalScopes()->where('beneficiary_id', $b->id)->where('action', 'eligibility')->sole();
        $this->assertSame(['Youth Accounting Training 2026', 'training'], [$h->changes['_eligibility']['title'], $h->changes['_eligibility']['kind']]);
        $this->assertSame(['not_eligible', 'eligible', 'Moving to Giza next month'],
            [$h->changes['_eligibility']['from'], $h->changes['_eligibility']['to'], $h->changes['_eligibility']['reason']]);

        // A new check never changes the decision.
        $this->later();
        $this->check($worker, $b, $p);
        $this->assertSame('eligible', $a->refresh()->result);

        // Back to the automatic result (also with a reason).
        $this->actingAs($worker)->post("/app/eligibility/{$a->id}/decide", ['decision' => 'auto', 'reason' => 'She is staying in Alexandria'])->assertSessionHasNoErrors();
        $this->assertSame([null, 'not_eligible'], [$a->refresh()->decision, $a->result]);

        // Notes.
        $this->actingAs($worker)->patch("/app/eligibility/{$a->id}/notes", ['notes' => 'Call again in November'])->assertSessionHasNoErrors();
        $this->assertSame('Call again in November', $a->refresh()->notes);

        // The profile page shows the result and the history line.
        $this->actingAs($worker)->get("/app/beneficiaries/{$b->number}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.0.result', 'not_eligible')
                ->where('eligibility.0.opportunity.title', 'Youth Accounting Training 2026')
                ->where('eligibility.0.opportunity.kind', 'training')
                ->where('eligibility.0.notes', 'Call again in November')
                ->where('opportunities.0.id', $p->id)
                ->where('history.0.eligibility.reason', 'She is staying in Alexandria'));
    }

    public function test_results_follow_the_profile_and_a_decision_is_marked(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);
        $b = $this->person($worker, ['governorate' => 'alx']);
        $a = $this->check($worker, $b, $p);
        $this->actingAs($worker)->post("/app/eligibility/{$a->id}/decide", ['decision' => 'on_hold', 'reason' => 'Waiting for her papers']);

        // Moves to Giza: the automatic result follows at once; the decision stays and is marked.
        $edit = $this->actingAs($worker)->get("/app/beneficiaries/{$b->number}/edit")->viewData('page')['props']['beneficiary'];
        $edit['governorate'] = 'giz';
        $edit['esco_occupation_id'] = $b->esco_occupation_id;
        $this->actingAs($worker)->patch("/app/beneficiaries/{$b->number}", $edit)->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertSame('eligible', $a->auto_result);
        $this->assertSame('on_hold', $a->result);
        $this->assertSame('profile_changed', $a->decision_flag);

        // A closed training keeps its results as they were.
        $this->actingAs($worker)->post("/app/training/{$p->id}/close", ['reason' => 'finished']);
        $edit['governorate'] = 'alx';
        $this->actingAs($worker)->patch("/app/beneficiaries/{$b->number}", $edit)->assertSessionHasNoErrors();
        $this->assertSame('eligible', $a->refresh()->auto_result);
    }

    public function test_new_rules_outdate_results_and_they_are_checked_again(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);
        $b1 = $this->person($worker);
        $b2 = $this->person($worker, ['governorate' => 'alx', 'phone' => '01001234568']);
        $a1 = $this->check($worker, $b1, $p);
        $a2 = $this->check($worker, $b2, $p);
        $this->actingAs($worker)->post("/app/eligibility/{$a2->id}/decide", ['decision' => 'eligible', 'reason' => 'Moving']);

        // Saving the same rules changes nothing.
        $this->actingAs($worker)->patch("/app/training/{$p->id}", $this->programData(['title' => 'Renamed']))->assertSessionHasNoErrors();
        $this->later();
        $this->assertSame(1, $p->refresh()->rules_version);

        // Alexandria added: version 2, both results out of date.
        $data = $this->programData();
        $data['rules'][1]['values'] = ['cai', 'giz', 'alx'];
        $this->actingAs($worker)->patch("/app/training/{$p->id}", $data)->assertSessionHas('success');
        $this->assertSame(2, $p->refresh()->rules_version);
        $this->actingAs($worker)->get("/app/training/{$p->id}")
            ->assertInertia(fn (Assert $page) => $page->component('App/Opportunities/Show')->where('outdated', 2));

        $this->actingAs($worker)->post("/app/training/{$p->id}/runs", ['scope' => 'outdated'])->assertRedirect("/app/training/{$p->id}");
        $run = EligibilityRun::withoutGlobalScopes()->sole();
        $this->assertSame(2, $run->total);
        $this->actingAs($worker)->postJson("/app/eligibility/runs/{$run->id}/step", ['at' => 0])->assertOk()->assertJson(['done' => 2, 'status' => 'done']);

        $this->assertSame([2, 'eligible'], [$a2->refresh()->rules_version, $a2->auto_result]);
        $this->assertSame('rules_changed', $a2->decision_flag);
        $this->assertNull($a1->refresh()->decision_flag);
    }

    // ── Check everyone ───────────────────────────────────────────────

    public function test_check_everyone_in_steps_and_from_a_cv_bank_search(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);
        $this->person($worker);
        $this->person($worker, ['governorate' => 'alx', 'phone' => '01001234568']);
        $this->person($worker, ['date_of_birth' => null, 'phone' => '01001234569', 'governorate' => 'giz']);
        $other = User::factory()->employee()->create();
        $this->person($other);                                   // another partner's: never checked

        $this->actingAs($worker)->post("/app/training/{$p->id}/runs", ['scope' => 'all']);
        $run = EligibilityRun::withoutGlobalScopes()->sole();
        $this->assertSame([3, 'running'], [$run->total, $run->status]);

        $this->actingAs($worker)->postJson("/app/eligibility/runs/{$run->id}/step", ['at' => 0])
            ->assertJson(['done' => 3, 'status' => 'done', 'counts' => ['eligible' => 1, 'check' => 1, 'not_eligible' => 1]]);
        // The same step sent again does nothing twice.
        $this->actingAs($worker)->postJson("/app/eligibility/runs/{$run->id}/step", ['at' => 0, 'nonce' => 'b'])->assertJson(['done' => 3]);
        $this->assertSame(3, EligibilityAssessment::withoutGlobalScopes()->count());

        // The shortlist: sorted by score, filtered by result.
        $this->actingAs($worker)->get("/app/training/{$p->id}?result=eligible")
            ->assertInertia(fn (Assert $page) => $page->has('list.data', 1)->where('list.data.0.person.number', 1)
                ->where('counts.total', 3)->where('counts.eligible', 1)->where('run.status', 'done'));

        // From a CV Bank search: only the people in Giza.
        $this->actingAs($worker)->post("/app/training/{$p->id}/runs", ['scope' => 'search', 'filters' => ['governorate' => 'giz']]);
        $run = EligibilityRun::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame([1, 'search'], [$run->total, $run->scope]);

        // Many people: moved forward STEP at a time.
        $stepper = app(RunStepper::class);
        $run = $stepper->start($p, 'all', [], $worker);
        $this->assertSame('done', $stepper->step($run, 0, 'x')->status);   // 3 < STEP
    }

    public function test_the_cv_bank_stage_filter_and_the_dashboard_pipeline(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);
        $b1 = $this->person($worker);
        $this->person($worker, ['phone' => '01001234568', 'name_en' => 'Mona Adel', 'name_ar' => 'منى عادل']);
        $this->check($worker, $b1, $p);

        $this->actingAs($worker)->get('/app/cv-bank?stage=eligible')
            ->assertInertia(fn (Assert $page) => $page->has('list.data', 1)->where('list.data.0.number', 1)->where('opportunities.0.id', $p->id));
        $this->actingAs($worker)->get('/app/cv-bank?stage=not_assessed')
            ->assertInertia(fn (Assert $page) => $page->has('list.data', 1)->where('list.data.0.number', 2));
        $this->actingAs($worker)->get('/app/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('pipeline', ['registered' => 2, 'assessed' => 1, 'eligible' => 1, 'matched' => 0, 'active' => 0, 'placed' => 0, 'follow' => 0]));
    }

    // ── Close, copy, delete ──────────────────────────────────────────

    public function test_close_copy_and_delete(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);
        $b = $this->person($worker);
        $this->check($worker, $b, $p);

        $this->actingAs($worker)->delete("/app/training/{$p->id}")->assertSessionHas('error');
        $this->assertNotNull($p->fresh());

        // Closing needs a reason.
        $this->actingAs($worker)->post("/app/training/{$p->id}/close")->assertSessionHasErrors('reason');
        $this->later();
        $this->actingAs($worker)->post("/app/training/{$p->id}/close", ['reason' => 'filled'])->assertSessionHasNoErrors();
        $this->assertSame(['closed', 'filled'], [$p->refresh()->status, $p->close_reason]);
        $this->assertSame('closed', collect($p->history)->last()['action']);
        $this->later();
        $this->actingAs($worker)->post("/app/beneficiaries/{$b->number}/eligibility", ['opportunity_id' => $p->id])->assertSessionHasErrors('opportunity_id');
        $this->actingAs($worker)->post("/app/training/{$p->id}/runs", ['scope' => 'all'])->assertSessionHas('error');
        $this->actingAs($worker)->get('/app/training?tab=closed')
            ->assertInertia(fn (Assert $page) => $page->component('App/Opportunities/Index')->has('list.data', 1)->where('list.data.0.counts.eligible', 1));

        $this->actingAs($worker)->post("/app/training/{$p->id}/copy");
        $copy = Opportunity::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame(['open', 1, $p->rules, 'training'], [$copy->status, $copy->rules_version, $copy->rules, $copy->kind]);
        $this->assertStringContainsString('(copy)', $copy->title);

        $this->actingAs($worker)->delete("/app/training/{$copy->id}")->assertRedirect('/app/training');
        $this->assertNull($copy->fresh());
    }

    // ── Partner separation and permissions ───────────────────────────

    public function test_another_partner_sees_and_touches_nothing(): void
    {
        $worker = User::factory()->employee()->create();
        $p = $this->program($worker);
        $a = $this->check($worker, $this->person($worker), $p);
        $this->actingAs($worker)->post("/app/training/{$p->id}/runs", ['scope' => 'all']);
        $run = EligibilityRun::withoutGlobalScopes()->sole();

        $outsider = User::factory()->companyAdmin()->create();
        $theirs = $this->person($outsider);

        $this->actingAs($outsider)->get('/app/training')->assertInertia(fn (Assert $page) => $page->has('list.data', 0)->where('tabs.open', 0));
        $this->actingAs($outsider)->get("/app/training/{$p->id}")->assertNotFound();
        $this->actingAs($outsider)->get("/app/training/{$p->id}/edit")->assertNotFound();
        $this->actingAs($outsider)->patch("/app/training/{$p->id}", $this->programData())->assertNotFound();
        $this->actingAs($outsider)->post("/app/training/{$p->id}/copy")->assertNotFound();
        $this->actingAs($outsider)->post("/app/training/{$p->id}/close", ['reason' => 'filled'])->assertNotFound();
        $this->actingAs($outsider)->delete("/app/training/{$p->id}")->assertNotFound();
        $this->actingAs($outsider)->post("/app/training/{$p->id}/runs", ['scope' => 'all'])->assertNotFound();
        $this->actingAs($outsider)->postJson("/app/eligibility/runs/{$run->id}/step", ['at' => 0])->assertNotFound();
        $this->actingAs($outsider)->post("/app/eligibility/{$a->id}/decide", ['decision' => 'eligible', 'reason' => 'x'])->assertNotFound();
        $this->actingAs($outsider)->patch("/app/eligibility/{$a->id}/notes", ['notes' => 'x'])->assertNotFound();
        // Their own person against our program: refused.
        $this->actingAs($outsider)->post("/app/beneficiaries/{$theirs->number}/eligibility", ['opportunity_id' => $p->id])->assertSessionHasErrors('opportunity_id');
        $this->assertSame(1, EligibilityAssessment::withoutGlobalScopes()->count());
        $this->assertSame('eligible', $a->refresh()->result);
    }

    public function test_permissions(): void
    {
        $admin = User::factory()->companyAdmin()->create();
        $p = $this->program($admin);
        $a = $this->check($admin, $this->person($admin), $p);

        // See only: the pages, never the form, the checks or the decision.
        $viewer = User::factory()->employee($admin->company)->create(['permissions' => ['beneficiaries.view', 'opportunities.view']]);
        $this->actingAs($viewer)->get('/app/training')->assertOk();
        $this->actingAs($viewer)->get("/app/training/{$p->id}")->assertOk();
        $this->actingAs($viewer)->get('/app/training/create')->assertForbidden();
        $this->actingAs($viewer)->post("/app/training/{$p->id}/close", ['reason' => 'filled'])->assertForbidden();
        $this->actingAs($viewer)->post("/app/beneficiaries/1/eligibility", ['opportunity_id' => $p->id])->assertForbidden();
        $this->actingAs($viewer)->post("/app/training/{$p->id}/runs", ['scope' => 'all'])->assertForbidden();
        $this->actingAs($viewer)->post("/app/eligibility/{$a->id}/decide", ['decision' => 'eligible', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->get('/app/beneficiaries/1')->assertInertia(fn (Assert $page) => $page->where('opportunities', []));

        // Check people, without being allowed to change the rules.
        $checker = User::factory()->employee($admin->company)->create(['permissions' => ['beneficiaries.view', 'opportunities.view', 'eligibility.check']]);
        $this->actingAs($checker)->post("/app/beneficiaries/1/eligibility", ['opportunity_id' => $p->id])->assertSessionHasNoErrors();
        $this->actingAs($checker)->get("/app/training/{$p->id}/edit")->assertForbidden();
        $this->actingAs($checker)->patch("/app/training/{$p->id}", $this->programData())->assertForbidden();

        $nobody = User::factory()->employee($admin->company)->create(['permissions' => ['beneficiaries.view']]);
        $this->actingAs($nobody)->get('/app/training')->assertForbidden();
        $this->actingAs($nobody)->get('/app/jobs')->assertForbidden();
        $this->actingAs($nobody)->get('/app/beneficiaries/1')->assertInertia(fn (Assert $page) => $page->where('eligibility', null));

        $decider = User::factory()->employee($admin->company)->create(['permissions' => ['beneficiaries.view', 'opportunities.view', 'eligibility.decide']]);
        $this->actingAs($decider)->post("/app/eligibility/{$a->id}/decide", ['decision' => 'on_hold', 'reason' => 'Papers'])->assertSessionHasNoErrors();
        $this->assertSame('on_hold', $a->refresh()->result);
    }
}
