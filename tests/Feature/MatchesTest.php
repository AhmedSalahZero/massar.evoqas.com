<?php

namespace Tests\Feature;

use App\Models\Backbone\EscoOccupation;
use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\EligibilityAssessment;
use App\Models\Opportunity;
use App\Models\OpportunityMatch;
use App\Models\OpportunityMatchEvent;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — MatchesTest (Step 13 · Matches)
//  Location: tests/Feature/MatchesTest.php
//  Scope: docs/SCOPE_MATCHES.md
//
//    • only Eligible people are referred (the case worker's "Eligible"
//      counts); someone not checked yet is checked first; one match
//      per person and job; several at once
//    • every stage, skipping stages, the dates, the final stage, the
//      correction (back one stage, with a reason)
//    • stop with its reason ("Other" needs words), restart with a reason
//    • the timeline and the profile history, nothing edited
//    • seats: taken from Accepted onwards; all taken → a warning
//    • a closed job: no new referrals, the matches continue
//    • "no longer eligible", "already hired at …"
//    • the fit (occupation and skills), information only
//    • the Matches page: counts, filters, "Needs follow-up" (14 days)
//    • the pipeline, the CV Bank stage, the Super Admin's counts
//    • PARTNER SEPARATION and permissions
// ══════════════════════════════════════════════════════════════════

class MatchesTest extends TestCase
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

    // ── Helpers ──────────────────────────────────────────────────────

    /** The double-submit guard blocks the same form for a few seconds: move the clock on. */
    private function later(): void
    {
        Carbon::setTestNow(now()->addSeconds(10));
    }

    private function person(User $worker, array $overrides = []): Beneficiary
    {
        static $phone = 1001230000;
        $data = array_merge([
            'name_ar'         => 'سارة مصطفى',
            'name_en'         => 'Sara Mostafa',
            'gender'          => 'female',
            'date_of_birth'   => '1999-04-12',
            'governorate'     => 'cai',
            'phone'           => '0'.(++$phone),
            'education_level' => 'university',
            'skills'          => ['Microsoft Excel'],
            'esco_occupation_id' => EscoOccupation::where('code', '2411.1')->value('id'),
            'confirm_duplicate' => true,
        ], $overrides);
        $this->actingAs($worker)->post('/app/beneficiaries', $data)->assertSessionHasNoErrors();
        $this->later();

        return Beneficiary::withoutGlobalScopes()->where('company_id', $worker->company_id)->orderByDesc('id')->first();
    }

    /** A job in Cairo: musts are age 18–35 and Cairo. */
    private function job(User $worker, array $overrides = [], string $section = 'jobs'): Opportunity
    {
        $data = array_merge([
            'title'         => 'Junior accountant',
            'occupations'   => ['esco:2411.1'],
            'governorates'  => ['cai'],
            'seats'         => 2,
            'job_type'      => 'full_time',
            'provider'      => 'Nile Skills',
            'eligible_from' => 70,
            'check_from'    => 50,
            'rules'         => [
                ['type' => 'age', 'mode' => 'must', 'min' => 18, 'max' => 35],
                ['type' => 'governorate', 'mode' => 'must', 'values' => ['cai']],
            ],
        ], $overrides);
        $this->actingAs($worker)->post("/app/$section", $data)->assertSessionHasNoErrors();
        $this->later();

        return Opportunity::withoutGlobalScopes()->where('company_id', $worker->company_id)->orderByDesc('id')->first();
    }

    private function check(User $worker, Beneficiary $b, Opportunity $o): EligibilityAssessment
    {
        $this->actingAs($worker)->post("/app/beneficiaries/{$b->number}/eligibility", ['opportunity_id' => $o->id])->assertSessionHasNoErrors();
        $this->later();

        return EligibilityAssessment::withoutGlobalScopes()->where('opportunity_id', $o->id)->where('beneficiary_id', $b->id)->firstOrFail();
    }

    private function refer(User $worker, Opportunity $o, array $numbers, array $extra = [])
    {
        $r = $this->actingAs($worker)->post('/app/matches', ['opportunity_id' => $o->id, 'numbers' => $numbers] + $extra);
        $this->later();

        return $r;
    }

    private function matchOf(Opportunity $o, Beneficiary $b): ?OpportunityMatch
    {
        return OpportunityMatch::withoutGlobalScopes()->where('opportunity_id', $o->id)->where('beneficiary_id', $b->id)->first();
    }

    private function act(User $worker, OpportunityMatch $m, string $what, array $data = [])
    {
        $r = $this->actingAs($worker)->post("/app/matches/{$m->id}/$what", $data);
        $this->later();

        return $r;
    }

    /** One accepted person, ready to go further. */
    private function referred(User $worker, Opportunity $o, Beneficiary $b): OpportunityMatch
    {
        $this->check($worker, $b, $o);
        $this->refer($worker, $o, [$b->number])->assertSessionHas('success');

        return $this->matchOf($o, $b);
    }

    // ── Who can be referred ──────────────────────────────────────────

    public function test_only_eligible_people_are_referred_once(): void
    {
        $worker = User::factory()->employee()->create();
        $o = $this->job($worker);
        $sara = $this->person($worker);
        $mona = $this->person($worker, ['name_en' => 'Mona Adel', 'governorate' => 'alx']);   // fails the Cairo must
        $omar = $this->person($worker, ['name_en' => 'Omar Ali', 'gender' => 'male', 'military_status' => 'completed']);   // not checked yet

        $this->check($worker, $sara, $o);
        $monaResult = $this->check($worker, $mona, $o);
        $this->assertSame('not_eligible', $monaResult->result);

        // Not eligible: refused, with the reason.
        $this->refer($worker, $o, [$mona->number])->assertSessionHas('error');
        $this->assertNull($this->matchOf($o, $mona));

        // Eligible: referred, with the date it happened and a note.
        $this->refer($worker, $o, [$sara->number], ['on' => '2026-09-20', 'note' => 'CV sent by email'])->assertSessionHas('success');
        $m = $this->matchOf($o, $sara);
        $this->assertSame(['referred', 'active', 'job', '2026-09-20'], [$m->stage, $m->status, $m->kind, $m->referred_on->toDateString()]);
        $e = OpportunityMatchEvent::withoutGlobalScopes()->where('match_id', $m->id)->sole();
        $this->assertSame(['referred', 'CV sent by email', '2026-09-20', $worker->name], [$e->action, $e->note, $e->happened_on->toDateString(), $e->user_name]);

        // Once per person and job.
        $this->refer($worker, $o, [$sara->number])->assertSessionHas('error');
        $this->assertSame(1, OpportunityMatch::withoutGlobalScopes()->count());

        // Not checked yet: checked first, then referred because Eligible.
        $this->refer($worker, $o, [$omar->number])->assertSessionHas('success');
        $this->assertSame('eligible', EligibilityAssessment::withoutGlobalScopes()->where('beneficiary_id', $omar->id)->value('result'));
        $this->assertNotNull($this->matchOf($o, $omar));

        // The case worker decided Mona is Eligible (with a reason): she can be referred now.
        $this->actingAs($worker)->post("/app/eligibility/{$monaResult->id}/decide", ['decision' => 'eligible', 'reason' => 'Moving to Cairo next month']);
        $this->later();
        // Several at once: 1 new, 2 already referred.
        $this->refer($worker, $o, [$sara->number, $mona->number, $omar->number])
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, '1 referred') && str_contains($msg, '2 already referred'));
        $this->assertSame(3, OpportunityMatch::withoutGlobalScopes()->count());

        // Not a date in the future.
        $other = $this->job($worker, ['title' => 'Cashier']);
        $this->check($worker, $sara, $other);
        $this->refer($worker, $other, [$sara->number], ['on' => '2026-09-28'])->assertSessionHasErrors('on');
        $this->assertNull($this->matchOf($other, $sara));
    }

    // ── Stages ───────────────────────────────────────────────────────

    public function test_stages_skipping_dates_the_final_stage_and_corrections(): void
    {
        $worker = User::factory()->employee()->create();
        $o = $this->job($worker);
        $b = $this->person($worker);
        $m = $this->referred($worker, $o, $b);

        // Backwards or the same stage: refused. A future date: refused.
        $this->act($worker, $m, 'move', ['to' => 'referred'])->assertSessionHasErrors('to');
        $this->act($worker, $m, 'move', ['to' => 'accepted', 'on' => '2026-10-01'])->assertSessionHasErrors('on');
        // Before the current stage: refused.
        $this->act($worker, $m, 'move', ['to' => 'accepted', 'on' => '2026-09-01'])->assertSessionHasErrors('on');

        // Skipping "Accepted": straight to interviews, on an earlier date.
        $this->act($worker, $m, 'move', ['to' => 'in_progress', 'on' => '2026-09-27', 'note' => 'Interview on Sunday'])->assertSessionHas('success');
        $m->refresh();
        $this->assertSame('in_progress', $m->stage);
        $moved = OpportunityMatchEvent::withoutGlobalScopes()->where('match_id', $m->id)->where('action', 'moved')->sole();
        $this->assertSame(['accepted'], $moved->skipped);

        // Hired: final.
        $this->act($worker, $m, 'move', ['to' => 'done'])->assertSessionHas('success');
        $this->assertSame('done', $m->refresh()->stage);
        $this->act($worker, $m, 'move', ['to' => 'done'])->assertSessionHasErrors('to');
        $this->act($worker, $m, 'stop', ['reason' => 'withdrew'])->assertSessionHasErrors('reason');

        // A correction needs a reason, and goes back ONE stage.
        $this->act($worker, $m, 'back', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->act($worker, $m, 'back', ['reason' => 'Recorded by mistake'])->assertSessionHas('success');
        $this->assertSame('in_progress', $m->refresh()->stage);
        $this->act($worker, $m, 'back', ['reason' => 'Not interviewed yet'])->assertSessionHas('success');
        $this->assertSame('accepted', $m->refresh()->stage);   // one stage at a time, even over a skipped one
        $this->act($worker, $m, 'back', ['reason' => 'Not accepted yet'])->assertSessionHas('success');
        $this->assertSame('referred', $m->refresh()->stage);
        $this->act($worker, $m, 'back', ['reason' => 'Again'])->assertSessionHasErrors('reason');

        // The timeline keeps every line; corrections are new lines.
        $this->assertSame(['referred', 'moved', 'moved', 'corrected', 'corrected', 'corrected'],
            OpportunityMatchEvent::withoutGlobalScopes()->where('match_id', $m->id)->orderBy('id')->pluck('action')->all());
        // … and the profile history the same.
        $this->assertSame(6, BeneficiaryChange::withoutGlobalScopes()->where('beneficiary_id', $b->id)->where('action', 'match')->count());
        $this->actingAs($worker)->get("/app/beneficiaries/{$b->number}")->assertInertia(fn (Assert $p) => $p
            ->where('matches.0.stage', 'referred')->has('matches.0.events', 6)
            ->where('matches.0.events.0.action', 'corrected')->where('matches.0.events.0.note', 'Not accepted yet')
            ->where('history.0.match.action', 'corrected')->where('history.0.match.title', 'Junior accountant'));
        $this->actingAs($worker)->getJson("/app/matches/{$m->id}/timeline")->assertOk()->assertJsonCount(6, 'events')
            ->assertJsonPath('events.4.skipped', ['accepted']);
    }

    public function test_stop_with_a_reason_and_restart(): void
    {
        $worker = User::factory()->employee()->create();
        $o = $this->job($worker);
        $b = $this->person($worker);
        $m = $this->referred($worker, $o, $b);

        // The reasons of a job; "dropped out" is a training's.
        $this->act($worker, $m, 'stop', ['reason' => 'dropped_out'])->assertSessionHasErrors('reason');
        $this->act($worker, $m, 'stop', ['reason' => 'other'])->assertSessionHasErrors('note');
        $this->act($worker, $m, 'stop', ['reason' => 'not_accepted', 'note' => 'Needs more experience'])->assertSessionHas('success');
        $m->refresh();
        $this->assertSame(['stopped', 'not_accepted', 'Needs more experience', 'referred'], [$m->status, $m->stop_reason, $m->stop_note, $m->stage]);

        // Stopped: no moves, and no second match — it is restarted instead.
        $this->act($worker, $m, 'move', ['to' => 'accepted'])->assertSessionHasErrors('to');
        $this->refer($worker, $o, [$b->number])->assertSessionHas('error');
        $this->assertSame(1, OpportunityMatch::withoutGlobalScopes()->count());

        $this->act($worker, $m, 'restart', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->act($worker, $m, 'restart', ['reason' => 'The employer called back'])->assertSessionHas('success');
        $this->assertSame(['active', 'referred', null], [$m->refresh()->status, $m->stage, $m->stop_reason]);

        // A training has its own words and reasons.
        $t = $this->job($worker, ['title' => 'ICDL', 'job_type' => null], 'training');
        $mt = $this->referred($worker, $t, $b);
        $this->act($worker, $mt, 'stop', ['reason' => 'not_hired'])->assertSessionHasErrors('reason');
        $this->act($worker, $mt, 'stop', ['reason' => 'dropped_out'])->assertSessionHas('success');

        // Restart only while it is open and the person is still Eligible.
        $this->actingAs($worker)->post("/app/training/{$t->id}/close", ['reason' => 'finished']);
        $this->later();
        $this->act($worker, $mt, 'restart', ['reason' => 'Back again'])->assertSessionHasErrors('reason');
        $this->actingAs($worker)->post("/app/training/{$t->id}/reopen");
        $this->later();
        $a = EligibilityAssessment::withoutGlobalScopes()->where('opportunity_id', $t->id)->sole();
        $this->actingAs($worker)->post("/app/eligibility/{$a->id}/decide", ['decision' => 'on_hold', 'reason' => 'Papers missing']);
        $this->later();
        $this->act($worker, $mt, 'restart', ['reason' => 'Back again'])->assertSessionHasErrors('reason');
        $this->assertSame('stopped', $mt->refresh()->status);
    }

    // ── Seats, closed jobs, eligibility changes ─────────────────────

    public function test_seats_are_taken_from_accepted_and_a_full_job_warns(): void
    {
        $worker = User::factory()->employee()->create();
        $o = $this->job($worker, ['seats' => 2]);
        $people = [$this->person($worker), $this->person($worker, ['name_en' => 'Mona Adel']), $this->person($worker, ['name_en' => 'Hoda Samir'])];
        $ms = array_map(fn ($b) => $this->referred($worker, $o, $b), $people);

        // Referred people take no seat.
        $this->actingAs($worker)->get("/app/jobs/{$o->id}")->assertInertia(fn (Assert $p) => $p
            ->where('seats', ['taken' => 0, 'seats' => 2, 'full' => false])->has('matches', 3));

        $this->act($worker, $ms[0], 'move', ['to' => 'accepted'])->assertSessionMissing('warning');
        $this->act($worker, $ms[1], 'stop', ['reason' => 'withdrew']);
        $this->act($worker, $ms[2], 'move', ['to' => 'done'])->assertSessionHas('warning');   // all seats taken: warned, not blocked
        $this->actingAs($worker)->get("/app/jobs/{$o->id}")->assertInertia(fn (Assert $p) => $p
            ->where('seats', ['taken' => 2, 'seats' => 2, 'full' => true]));
        $this->actingAs($worker)->get('/app/jobs')->assertInertia(fn (Assert $p) => $p->where('list.data.0.seats_taken', 2));

        // An employer may take more: moving on is still allowed.
        $this->act($worker, $ms[1], 'restart', ['reason' => 'Changed his mind']);
        $this->act($worker, $ms[1], 'move', ['to' => 'accepted'])->assertSessionHas('success');
        $this->assertSame(3, OpportunityMatch::withoutGlobalScopes()->seated()->count());
    }

    public function test_a_closed_job_takes_nobody_new_but_its_matches_continue(): void
    {
        $worker = User::factory()->employee()->create();
        $o = $this->job($worker);
        $sara = $this->person($worker);
        $mona = $this->person($worker, ['name_en' => 'Mona Adel']);
        $m = $this->referred($worker, $o, $sara);
        $this->check($worker, $mona, $o);

        $this->actingAs($worker)->post("/app/jobs/{$o->id}/close", ['reason' => 'filled']);
        $this->later();
        $this->refer($worker, $o, [$mona->number])->assertSessionHasErrors('opportunity_id');
        $this->assertNull($this->matchOf($o, $mona));
        $this->act($worker, $m, 'move', ['to' => 'accepted'])->assertSessionHas('success');
        $this->act($worker, $m, 'move', ['to' => 'done'])->assertSessionHas('success');
    }

    public function test_no_longer_eligible_is_marked_and_already_hired_is_a_reminder(): void
    {
        $worker = User::factory()->employee()->create();
        $o = $this->job($worker);
        $b = $this->person($worker);
        $m = $this->referred($worker, $o, $b);
        $this->act($worker, $m, 'move', ['to' => 'done']);

        // The profile changes: the result follows, the match stays and is marked.
        $this->actingAs($worker)->patch("/app/beneficiaries/{$b->number}", [
            'name_ar' => 'سارة مصطفى', 'name_en' => 'Sara Mostafa', 'gender' => 'female', 'date_of_birth' => '1999-04-12',
            'governorate' => 'alx', 'phone' => $b->phone, 'education_level' => 'university', 'skills' => ['Microsoft Excel'],
            'esco_occupation_id' => $b->esco_occupation_id, 'confirm_duplicate' => true,
        ])->assertSessionHasNoErrors();
        $this->later();
        $this->assertSame('not_eligible', EligibilityAssessment::withoutGlobalScopes()->where('opportunity_id', $o->id)->value('result'));
        $this->assertSame(['active', 'done'], [$m->refresh()->status, $m->stage]);
        $this->actingAs($worker)->get("/app/jobs/{$o->id}")->assertInertia(fn (Assert $p) => $p
            ->where('matches.0.no_longer_eligible', true)->where('matches.0.result', 'not_eligible'));

        // Hired at this job; referring to another one is allowed, with a reminder.
        $other = $this->job($worker, ['title' => 'Bookkeeper', 'governorates' => ['alx'], 'rules' => [['type' => 'age', 'mode' => 'must', 'min' => 18, 'max' => 35]]]);
        $this->check($worker, $b, $other);
        $this->refer($worker, $other, [$b->number])->assertSessionHas('success')
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'already hired') && str_contains($w, 'Junior accountant'));
    }

    // ── The fit ──────────────────────────────────────────────────────

    public function test_the_fit_informs_and_never_decides(): void
    {
        // Two essential ESCO skills for the ESCO job 2411.1 (accountant).
        $now = now();
        $skill = fn ($uri, $en, $ar, $alt = []) => tap(DB::table('esco_skills')->insertGetId(['uri' => $uri, 'title_en' => $en, 'title_ar' => $ar, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]),
            function ($id) use ($en, $ar, $alt) {
                foreach ([['en', 'preferred', $en], ['ar', 'preferred', $ar], ...array_map(fn ($a) => ['en', 'alt', $a], $alt)] as [$lang, $kind, $label]) {
                    DB::table('skill_labels')->insert(['skill_id' => $id, 'lang' => $lang, 'kind' => $kind, 'label' => $label, 'normalized' => TextNormalizer::normalize($label)]);
                }
            });
        $tax = $skill('s/tax', 'calculate tax', 'حساب الضرائب', ['compute tax']);
        $excel = $skill('s/ss', 'use spreadsheets software', 'استخدام برمجيات الجداول', ['microsoft excel']);
        $acc = EscoOccupation::where('code', '2411.1')->value('id');
        DB::table('esco_occupation_skills')->insert([
            ['esco_occupation_id' => $acc, 'skill_id' => $tax, 'is_essential' => true],
            ['esco_occupation_id' => $acc, 'skill_id' => $excel, 'is_essential' => true],
        ]);

        $worker = User::factory()->employee()->create();
        $o = $this->job($worker);
        $none = $this->person($worker, ['name_en' => 'Hoda Samir', 'esco_occupation_id' => EscoOccupation::where('code', '0110.1')->value('id'), 'skills' => ['حساب الضرائب']]);
        $same = $this->person($worker, ['skills' => ['Advanced Microsoft Excel', 'Driving']]);
        // An accounting analyst (ESCO 2411.1.1) is inside the ESCO job asked for (2411.1): the same occupation.
        $analyst = $this->person($worker, ['name_en' => 'Mona Adel', 'esco_occupation_id' => EscoOccupation::where('code', '2411.1.1')->value('id'), 'skills' => []]);
        foreach ([$none, $same, $analyst] as $b) {
            $this->check($worker, $b, $o);
        }

        // Best fit first: the two in the occupation (by score, then number), then the one not related.
        $this->actingAs($worker)->get("/app/jobs/{$o->id}?sort=fit")->assertInertia(fn (Assert $p) => $p
            ->where('skills_asked', 2)
            ->where('list.data.0.person.number', $same->number)
            ->where('list.data.0.fit.occupation.level', 'same')
            ->where('list.data.0.fit.skills.state', 'ok')
            ->where('list.data.0.fit.skills.total', 2)
            ->where('list.data.0.fit.skills.found.0.en', 'use spreadsheets software')
            ->where('list.data.0.fit.skills.lacking.0.en', 'calculate tax')
            ->where('list.data.1.person.number', $analyst->number)
            ->where('list.data.1.fit.occupation.level', 'same')
            ->where('list.data.1.fit.skills.state', 'no_person_skills')
            ->where('list.data.2.person.number', $none->number)
            ->where('list.data.2.fit.occupation.level', 'none')
            ->where('list.data.2.fit.skills.found.0.en', 'calculate tax'));
        // The usual order (by score, then number) is unchanged.
        $this->actingAs($worker)->get("/app/jobs/{$o->id}")->assertInertia(fn (Assert $p) => $p->where('list.data.0.person.number', $none->number));

        // A job for the accounting analyst: the accountant is in the same 4-digit group, not the same job.
        $analystJob = $this->job($worker, ['title' => 'Accounting analyst', 'occupations' => ['esco:2411.1.1']]);
        $this->check($worker, $same, $analystJob);
        $this->actingAs($worker)->get("/app/jobs/{$analystJob->id}")->assertInertia(fn (Assert $p) => $p
            ->where('list.data.0.fit.occupation', ['level' => 'unit', 'rank' => 4, 'code' => '2411']));

        // Information only: the one whose occupation is not related is still Eligible and can be referred.
        $this->assertSame('eligible', EligibilityAssessment::withoutGlobalScopes()->where('beneficiary_id', $none->id)->value('result'));
        $this->refer($worker, $o, [$none->number])->assertSessionHas('success');

        // A broad group (ISCO-08 2) has no skills list: said so, nothing guessed.
        $broad = $this->job($worker, ['title' => 'Any professional', 'occupations' => ['isco:2']]);
        $this->check($worker, $same, $broad);
        $this->actingAs($worker)->get("/app/jobs/{$broad->id}")->assertInertia(fn (Assert $p) => $p
            ->where('skills_asked', 0)->where('list.data.0.fit.skills.state', 'no_job_skills')->where('list.data.0.fit.occupation.level', 'same'));

        // Suggested on the profile: open jobs whose occupations fit the person (not the ones already referred).
        $this->actingAs($worker)->get("/app/beneficiaries/{$analyst->number}")->assertInertia(fn (Assert $p) => $p
            ->where('suggested', fn ($s) => collect($s)->pluck('id')->sort()->values()->all() === [$o->id, $analystJob->id, $broad->id])
            ->where('suggested', fn ($s) => collect($s)->firstWhere('id', $o->id)['result'] === 'eligible'));
        $this->refer($worker, $o, [$analyst->number]);
        $this->actingAs($worker)->get("/app/beneficiaries/{$analyst->number}")->assertInertia(fn (Assert $p) => $p
            ->where('suggested', fn ($s) => ! collect($s)->contains('id', $o->id)));
        $this->actingAs($worker)->get("/app/beneficiaries/{$none->number}")->assertInertia(fn (Assert $p) => $p->where('suggested', []));
    }

    // ── The Matches page ─────────────────────────────────────────────

    public function test_the_matches_page_counts_filters_and_follow_up(): void
    {
        $worker = User::factory()->employee()->create();
        $o = $this->job($worker);
        $t = $this->job($worker, ['title' => 'ICDL', 'job_type' => null], 'training');
        $sara = $this->person($worker);
        $mona = $this->person($worker, ['name_en' => 'Mona Adel', 'phone' => '01112223334']);
        $m1 = $this->referred($worker, $o, $sara);
        $m2 = $this->referred($worker, $t, $mona);
        $this->referred($worker, $t, $sara);
        $this->act($worker, $m1, 'move', ['to' => 'done']);
        $this->act($worker, $m2, 'stop', ['reason' => 'withdrew']);

        $this->actingAs($worker)->get('/app/matches')->assertInertia(fn (Assert $p) => $p->component('App/Matches/Index')
            ->has('list.data', 3)
            ->where('counts', ['referred' => 1, 'accepted' => 0, 'in_progress' => 0, 'done' => 1, 'stopped' => 1, 'total' => 3, 'follow' => 0]));
        $this->actingAs($worker)->get('/app/matches?stage=done')->assertInertia(fn (Assert $p) => $p->has('list.data', 1)->where('list.data.0.id', $m1->id));
        $this->actingAs($worker)->get('/app/matches?stage=stopped')->assertInertia(fn (Assert $p) => $p->has('list.data', 1)->where('list.data.0.id', $m2->id));
        $this->actingAs($worker)->get('/app/matches?kind=training')->assertInertia(fn (Assert $p) => $p->has('list.data', 2));
        $this->actingAs($worker)->get("/app/matches?opportunity={$o->id}")->assertInertia(fn (Assert $p) => $p->has('list.data', 1));
        $this->actingAs($worker)->get('/app/matches?q=mona')->assertInertia(fn (Assert $p) => $p->has('list.data', 1)->where('list.data.0.person.name', 'Mona Adel'));
        $this->actingAs($worker)->get('/app/matches?q=01112223334')->assertInertia(fn (Assert $p) => $p->has('list.data', 1));
        $this->actingAs($worker)->get('/app/matches?q='.$sara->number)->assertInertia(fn (Assert $p) => $p->has('list.data', 2));
        $this->actingAs($worker)->get('/app/matches?governorate=alx')->assertInertia(fn (Assert $p) => $p->has('list.data', 0));
        $this->actingAs($worker)->get('/app/matches?occ=isco:24')->assertInertia(fn (Assert $p) => $p->has('list.data', 3));
        $this->actingAs($worker)->get('/app/matches?occ=isco:3')->assertInertia(fn (Assert $p) => $p->has('list.data', 0));

        // 14 days without a change: only the active, unfinished one needs a follow-up.
        Carbon::setTestNow(now()->addDays(15));
        $this->actingAs($worker)->get('/app/matches?follow=1')->assertInertia(fn (Assert $p) => $p
            ->has('list.data', 1)->where('list.data.0.follow_up', true)->where('list.data.0.days', 15)->where('counts.follow', 1));
        $this->actingAs($worker)->get('/app/dashboard')->assertInertia(fn (Assert $p) => $p->where('pipeline.follow', 1));
    }

    public function test_the_pipeline_the_cv_bank_stage_and_the_super_admin_counts(): void
    {
        $worker = User::factory()->employee()->create();
        $o = $this->job($worker);
        $sara = $this->person($worker);
        $mona = $this->person($worker, ['name_en' => 'Mona Adel']);
        $this->person($worker, ['name_en' => 'Hoda Samir']);
        $m = $this->referred($worker, $o, $sara);
        $this->referred($worker, $o, $mona);
        $this->act($worker, $m, 'move', ['to' => 'done']);

        $this->actingAs($worker)->get('/app/dashboard')->assertInertia(fn (Assert $p) => $p
            ->where('pipeline', ['registered' => 3, 'assessed' => 2, 'eligible' => 2, 'matched' => 2, 'active' => 1, 'placed' => 1, 'follow' => 0]));
        $this->actingAs($worker)->get('/app/cv-bank?stage=matched')->assertInertia(fn (Assert $p) => $p->has('list.data', 2));
        $this->actingAs($worker)->get('/app/cv-bank?stage=placed')->assertInertia(fn (Assert $p) => $p->has('list.data', 1)->where('list.data.0.number', $sara->number));

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get('/admin/dashboard')->assertInertia(fn (Assert $p) => $p
            ->where('stats.matches', ['matches' => 2, 'matched' => 2, 'placed' => 1]));
        $this->actingAs($admin)->get('/app/matches')->assertRedirect();   // the partner area is not the Super Admin's

        // A profile deleted: its matches go with it.
        $sara->delete();
        $this->assertSame(1, OpportunityMatch::withoutGlobalScopes()->count());
        $this->assertSame(0, OpportunityMatchEvent::withoutGlobalScopes()->where('match_id', $m->id)->count());
    }

    // ── Separation and permissions ───────────────────────────────────

    public function test_another_partner_never_sees_or_touches_a_match(): void
    {
        $worker = User::factory()->employee()->create();
        $o = $this->job($worker);
        $m = $this->referred($worker, $o, $this->person($worker));

        $outsider = User::factory()->companyAdmin()->create();
        $theirs = $this->person($outsider);
        $this->actingAs($outsider)->get('/app/matches')->assertInertia(fn (Assert $p) => $p->has('list.data', 0)->where('counts.total', 0)->where('opportunities', []));
        $this->actingAs($outsider)->getJson("/app/matches/{$m->id}/timeline")->assertNotFound();
        foreach (['move' => ['to' => 'accepted'], 'back' => ['reason' => 'x'], 'stop' => ['reason' => 'withdrew'], 'restart' => ['reason' => 'x']] as $what => $data) {
            $this->act($outsider, $m, $what, $data)->assertNotFound();
        }
        // Their own person to our job: refused. Our person to their job: not found.
        $this->refer($outsider, $o, [$theirs->number])->assertSessionHasErrors('opportunity_id');
        $this->assertSame(['referred', 'active'], [$m->refresh()->stage, $m->status]);
        $this->assertSame(1, OpportunityMatch::withoutGlobalScopes()->count());
        $this->actingAs($outsider)->get("/app/beneficiaries/{$theirs->number}")->assertInertia(fn (Assert $p) => $p->where('matches', []));
    }

    public function test_permissions(): void
    {
        $admin = User::factory()->companyAdmin()->create();
        $o = $this->job($admin);
        $b = $this->person($admin);
        $m = $this->referred($admin, $o, $b);

        // See only.
        $viewer = User::factory()->employee($admin->company)->create(['permissions' => ['beneficiaries.view', 'opportunities.view', 'matches.view']]);
        $this->actingAs($viewer)->get('/app/matches')->assertOk();
        $this->actingAs($viewer)->getJson("/app/matches/{$m->id}/timeline")->assertOk();
        $this->actingAs($viewer)->get("/app/jobs/{$o->id}")->assertInertia(fn (Assert $p) => $p->has('matches', 1));
        $this->actingAs($viewer)->post('/app/matches', ['opportunity_id' => $o->id, 'numbers' => [$b->number]])->assertForbidden();
        $this->actingAs($viewer)->post("/app/matches/{$m->id}/move", ['to' => 'accepted'])->assertForbidden();
        $this->actingAs($viewer)->post("/app/matches/{$m->id}/stop", ['reason' => 'withdrew'])->assertForbidden();

        // Neither: no page, and no Matches on the job or the profile.
        $nobody = User::factory()->employee($admin->company)->create(['permissions' => ['beneficiaries.view', 'opportunities.view']]);
        $this->actingAs($nobody)->get('/app/matches')->assertForbidden();
        $this->actingAs($nobody)->getJson("/app/matches/{$m->id}/timeline")->assertForbidden();
        $this->actingAs($nobody)->get("/app/jobs/{$o->id}")->assertInertia(fn (Assert $p) => $p->where('matches', null)->where('list.data.0.fit', null));
        $this->actingAs($nobody)->get("/app/beneficiaries/{$b->number}")->assertInertia(fn (Assert $p) => $p->where('matches', null)->where('suggested', []));

        // Every partner user holds both during the build.
        $employee = User::factory()->employee($admin->company)->create();
        $this->assertTrue($employee->can('matches.view') && $employee->can('matches.manage'));
        $this->act($employee, $m, 'move', ['to' => 'accepted'])->assertSessionHas('success');
    }
}
