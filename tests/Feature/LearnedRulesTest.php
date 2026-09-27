<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Company;
use App\Models\CvDocument;
use App\Models\LearnedRule;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use App\Services\Cv\CvReader;
use App\Services\Cv\LearnedRuleBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesCvFiles;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — LearnedRulesTest (Step 7 · Scope v2 §3 Learned Rules, two layers)
//  Location: tests/Feature/LearnedRulesTest.php
//
//    • a heading taught on the review screen works at once — this CV
//      and the other waiting CVs are read again — in THIS workspace only
//    • "Remember this occupation" on Approve makes a title rule; the
//      next CV with that title is matched by it (step 2) and added
//      automatically, with the occupation marked "by a learned rule"
//    • a skill word is found anywhere in a CV; "not a heading" is kept
//      as content
//    • propose → the Super Admin promotes (every partner uses it) or
//      declines with a note; only the words travel, never CV data
//    • permissions: teach, propose, promote; one partner never sees or
//      changes another's rules
// ══════════════════════════════════════════════════════════════════

class LearnedRulesTest extends TestCase
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

    private function upload(User $user, UploadedFile ...$files): array
    {
        $batch = $this->actingAs($user)->postJson('/app/cv-upload/batches', ['files' => count($files), 'client_id' => uniqid('b', true)])
            ->assertOk()->json('batch');
        $out = [];
        foreach ($files as $i => $file) {
            $out[] = $this->actingAs($user)->post("/app/cv-upload/batches/{$batch}/files", ['file' => $file, 'client_id' => 'c'.$i.uniqid()],
                ['Accept' => 'application/json'])->assertOk()->json('document');
        }

        return $out;
    }

    private function doc(string $uuid): CvDocument
    {
        return CvDocument::query()->withoutGlobalScopes()->where('uuid', $uuid)->firstOrFail();
    }

    /** The English sample CV with its work heading renamed, for another person (own mobile and email). */
    private function cvWithHeading(string $heading, int $person = 1): array
    {
        $cv = $this->englishCv();
        $cv[5] = '#'.$heading;
        $cv[2] = 'Nasr City, Cairo | +20 100 555 '.str_pad((string) (1000 + $person), 4, '0', STR_PAD_LEFT).' | person'.$person.'@example.com';

        return $cv;
    }

    // ── Layer 1: taught in the workspace ────────────────────────────

    public function test_a_heading_taught_on_the_review_screen_works_at_once_in_this_workspace_only(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $workerA = User::factory()->employee($a)->create();
        $workerB = User::factory()->employee($b)->create();

        [$first, $second] = $this->upload($workerA, $this->docx($this->cvWithHeading('Where I Worked', 1)), $this->docx($this->cvWithHeading('Where I Worked', 2), 'two.docx'));
        [$other] = $this->upload($workerB, $this->docx($this->cvWithHeading('Where I Worked', 3)));
        $this->assertSame('review', $first['status']);
        $this->assertCount(1, $this->doc($first['uuid'])->reading['marks_on_text']['unknown']);

        $this->actingAs($workerA)->post("/app/review-queue/{$first['uuid']}/teach", ['kind' => 'heading', 'phrase' => 'Where I Worked', 'section' => 'experience'])
            ->assertRedirect(route('app.review-queue.show', $first['uuid']))->assertSessionHas('success');

        $rule = LearnedRule::query()->sole();
        $this->assertSame($a->id, $rule->company_id);
        $this->assertSame('review', $rule->source);

        // This CV and the other waiting CV of the workspace were read again with the rule.
        foreach ([$first, $second] as $d) {
            $reading = $this->doc($d['uuid'])->reading;
            $this->assertSame([], $reading['marks_on_text']['unknown']);
            $this->assertSame('found', $reading['marks']['work_history']);
            $this->assertContains($rule->id, $reading['rules_used']);
        }
        $this->assertSame(2, $rule->fresh()->uses);

        // Another partner is not affected — not even for a new upload.
        $this->assertCount(1, $this->doc($other['uuid'])->reading['marks_on_text']['unknown']);
        [$new] = $this->upload($workerB, $this->docx($this->cvWithHeading('Where I Worked', 4), 'four.docx'));
        $this->assertCount(1, $this->doc($new['uuid'])->reading['marks_on_text']['unknown']);

        // The next CV of the workspace uses it from the start: nothing uncertain → added.
        [$third] = $this->upload($workerA, $this->docx($this->cvWithHeading('WHERE I WORKED:', 5), 'five.docx'));
        $this->assertSame('added', $third['status']);
    }

    public function test_remember_this_occupation_makes_a_title_rule_used_as_step_two(): void
    {
        foreach (['2411.1', '2411.1.1'] as $code) {
            $e = DB::table('esco_occupations')->where('code', $code)->first();
            DB::table('occupation_labels')->insert(['isco_group_id' => $e->isco_group_id, 'esco_occupation_id' => $e->id, 'source' => 'esco',
                'lang' => 'en', 'kind' => 'alt', 'label' => 'finance officer', 'normalized' => 'finance officer']);
        }
        $accountant = DB::table('esco_occupations')->where('code', '2411.1')->value('id');
        $worker = User::factory()->employee()->create();
        $cv = function (int $person) {
            $cv = $this->englishCv();
            $cv[1] = 'Finance Officer';
            $cv[2] = 'Nasr City, Cairo | +20 100 555 '.(2000 + $person).' | fo'.$person.'@example.com';
            $cv[6] = 'Finance Officer — Delta Trading | Mar 2021 – Present';
            $cv[8] = 'Finance Officer, Nile Foods (01/2018 - 02/2021)';

            return $cv;
        };
        [$first] = $this->upload($worker, $this->docx($cv(1)));
        $this->assertSame('ambiguous', $this->doc($first['uuid'])->reading['occupation']['status']);

        // The review screen offers to remember the title.
        $this->actingAs($worker)->get("/app/review-queue/{$first['uuid']}")
            ->assertInertia(fn (Assert $p) => $p->where('teach.title', 'Finance Officer')->where('teach.can', true));

        $form = $this->doc($first['uuid'])->reading['form'];
        $this->actingAs($worker)->post("/app/review-queue/{$first['uuid']}/approve", array_merge($form, [
            'esco_occupation_id' => $accountant, 'learn_title' => true,
        ]))->assertSessionHasNoErrors();

        $rule = LearnedRule::query()->sole();
        $this->assertSame(['title', 'Finance Officer', $accountant], [$rule->kind, $rule->phrase, $rule->esco_occupation_id]);

        // The next CV with that title: step 2 decides, and it is added automatically.
        [$second] = $this->upload($worker, $this->docx($cv(2), 'second.docx'));
        $this->assertSame('added', $second['status']);
        $occ = $this->doc($second['uuid'])->reading['occupation'];
        $this->assertSame('rule', $occ['status']);
        $this->assertSame($rule->id, $occ['rule_id']);
        $b = Beneficiary::query()->withoutGlobalScopes()->where('email', 'fo2@example.com')->sole();
        $this->assertSame([$accountant, 'cv_rule'], [$b->esco_occupation_id, $b->occupation_method]);
        $this->assertSame(1, $rule->fresh()->uses);

        // An exact single match still comes first (step 1 before step 2).
        $this->assertSame('exact', app(\App\Services\Cv\OccupationClassifier::class)
            ->classify(['Accountant'], null, app(LearnedRuleBook::class)->forReading($worker->company_id)['titles'])['status']);
    }

    public function test_skill_words_and_lines_that_are_not_headings(): void
    {
        $worker = User::factory()->employee()->create();
        $this->actingAs($worker)->post('/app/rules', ['kind' => 'skill', 'phrase' => 'Peachtree', 'skill_name' => 'Peachtree Accounting'])
            ->assertSessionHasNoErrors();
        $this->actingAs($worker)->post('/app/rules', ['kind' => 'heading', 'phrase' => 'Where I Worked', 'section' => 'none'])
            ->assertSessionHasNoErrors();

        $book = app(LearnedRuleBook::class)->forReading($worker->company_id);
        $r = app(CvReader::class)->withRules($book)->read("Omar Ali\nomar@example.com\n\nSummary\nFive years using Peachtree every day.\n\nWhere I Worked\nNile Foods\n\nSkills\nExcel");
        $this->assertSame(['Excel', 'Peachtree Accounting'], $r['form']['skills']);
        $this->assertSame([], $r['marks_on_text']['unknown']);
        $this->assertSame('none', app(CvReader::class)->withRules($book)->recognise('Where I Worked'));
        // Without the rules the same text is read as before.
        $this->assertSame(['Excel'], app(CvReader::class)->read("Omar Ali\n\nSummary\nFive years using Peachtree every day.\n\nSkills\nExcel")['form']['skills']);
    }

    public function test_the_rules_page_lists_changes_and_deletes_rules(): void
    {
        $worker = User::factory()->employee()->create();
        $this->actingAs($worker)->post('/app/rules', ['kind' => 'heading', 'phrase' => 'Career Journey', 'section' => 'experience'])->assertSessionHasNoErrors();
        // The same words again change the rule, they do not add a second one.
        $this->actingAs($worker)->post('/app/rules', ['kind' => 'heading', 'phrase' => 'CAREER JOURNEY:', 'section' => 'internships'])->assertSessionHasNoErrors();
        $rule = LearnedRule::query()->sole();
        $this->assertSame('internships', $rule->section);

        $this->actingAs($worker)->get('/app/rules')->assertInertia(fn (Assert $p) => $p->component('App/Rules/Index')
            ->where('list.data.0.phrase', 'Career Journey')->where('counts.ours', 1));

        $this->actingAs($worker)->patch("/app/rules/{$rule->id}", ['kind' => 'heading', 'phrase' => 'Career Journey', 'section' => 'experience'])->assertSessionHasNoErrors();
        $this->assertSame('experience', $rule->fresh()->section);

        // Checked like the form: a heading needs its meaning, a title its occupation.
        $this->actingAs($worker)->post('/app/rules', ['kind' => 'heading', 'phrase' => 'Something'])->assertSessionHasErrors('section');
        $this->actingAs($worker)->post('/app/rules', ['kind' => 'title', 'phrase' => 'مندوب مبيعات'])->assertSessionHasErrors('occupation');

        $this->actingAs($worker)->delete("/app/rules/{$rule->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, LearnedRule::query()->count());
    }

    public function test_changing_a_rule_on_the_rules_page_reads_the_waiting_cvs_again(): void
    {
        // The case from the field: "IFRS" alone on a line inside the skills, a page footer,
        // and a skill with its version after a dash.
        $worker = User::factory()->employee()->create();
        $cv = ['Amr Ibrahim Osman', 'amr@example.com', '01005557777', 'SKILLS', 'Persuasiveness', 'Page 3 of 4', 'ERP Sun System MFF - Version 4 & 5',
            'Adaptability', 'IFRS', 'Consolidation', 'Budging and Forecasting', 'LANGUAGES', 'English: Fluent'];
        [$up] = $this->upload($worker, $this->docx($cv));
        $reading = fn () => $this->doc($up['uuid'])->reading;
        $this->assertNotContains('Page 3 of 4', $reading()['form']['skills']);
        $this->assertContains('ERP Sun System MFF – Version 4 & 5', $reading()['form']['skills']);

        // 1. Taught on the review screen as a heading meaning Skills: the lines under it are skills, IFRS itself is not.
        $this->actingAs($worker)->post("/app/review-queue/{$up['uuid']}/teach", ['kind' => 'heading', 'phrase' => 'IFRS', 'section' => 'skills']);
        $this->assertContains('Consolidation', $reading()['form']['skills']);
        $this->assertNotContains('IFRS', $reading()['form']['skills']);

        // 2. Changed on the Learned Rules page into a skill word: the waiting CV is read again at once.
        $rule = LearnedRule::query()->sole();
        $this->actingAs($worker)->patch("/app/rules/{$rule->id}", ['kind' => 'skill', 'phrase' => 'IFRS', 'skill_name' => 'IFRS'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 waiting CVs'));
        $skills = $reading()['form']['skills'];
        $this->assertContains('IFRS', $skills);
        $this->assertContains('Consolidation', $skills);            // the IFRS line no longer cuts the skills in two
        $this->assertSame([], $reading()['marks_on_text']['unknown']);

        // "Read again" on the review screen, e.g. after a rule changed.
        $this->travel(10)->seconds();
        $this->actingAs($worker)->post("/app/review-queue/{$up['uuid']}/reread")->assertRedirect(route('app.review-queue.show', $up['uuid']));
        $this->assertContains('IFRS', $reading()['form']['skills']);
    }

    // ── Layer 2: proposed to Massar ─────────────────────────────────

    public function test_a_rule_is_proposed_then_promoted_for_every_partner(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $workerA = User::factory()->employee($a)->create();
        $workerB = User::factory()->employee($b)->create();
        $admin = User::factory()->superAdmin()->create();

        [$cvA] = $this->upload($workerA, $this->docx($this->cvWithHeading('Where I Worked', 1)));
        $this->actingAs($workerA)->post("/app/review-queue/{$cvA['uuid']}/teach", ['kind' => 'heading', 'phrase' => 'Where I Worked', 'section' => 'experience']);
        $rule = LearnedRule::query()->sole();

        $this->actingAs($workerA)->post("/app/rules/{$rule->id}/propose")->assertSessionHasNoErrors();
        $this->assertSame('pending', $rule->fresh()->proposal_status);

        // The Super Admin sees the words and their meaning — no CV, no person.
        $this->actingAs($admin)->get('/admin/rule-requests')->assertInertia(fn (Assert $p) => $p->component('Admin/RuleRequests/Index')
            ->where('counts.pending', 1)->where('list.data.0.phrase', 'Where I Worked')->where('list.data.0.company', $a->name)
            ->missing('list.data.0.text')->missing('list.data.0.reading'));

        $this->actingAs($admin)->post("/admin/rule-requests/{$rule->id}/promote", ['note' => 'Good one'])->assertSessionHasNoErrors();
        $massar = LearnedRule::query()->massar()->sole();
        $this->assertSame(['heading', 'Where I Worked', 'experience', 'promoted'], [$massar->kind, $massar->phrase, $massar->section, $massar->source]);
        $this->assertSame(['promoted', 'Good one'], [$rule->fresh()->proposal_status, $rule->fresh()->decision_note]);

        // Every partner now reads it — the other partner's new CV too.
        [$cvB] = $this->upload($workerB, $this->docx($this->cvWithHeading('Where I Worked', 2)));
        $this->assertSame([], $this->doc($cvB['uuid'])->reading['marks_on_text']['unknown']);
        $this->actingAs($workerB)->get('/app/rules?tab=massar')->assertInertia(fn (Assert $p) => $p->where('counts.massar', 1)->where('list.data.0.massar', true));

        // A partner's own rule for the same words wins over Massar's.
        $this->actingAs($workerB)->post('/app/rules', ['kind' => 'heading', 'phrase' => 'Where I Worked', 'section' => 'none']);
        $this->assertSame('none', app(CvReader::class)->withRules(app(LearnedRuleBook::class)->forReading($b->id))->recognise('Where I Worked'));
        $this->assertSame('experience', app(CvReader::class)->withRules(app(LearnedRuleBook::class)->forReading($a->id))->recognise('Where I Worked'));

        // Proposing what Massar already has, with the same meaning, is explained.
        $this->travel(10)->seconds();      // (the same click twice within 8 seconds is ignored on purpose)
        $this->actingAs($workerA)->post("/app/rules/{$rule->id}/propose")->assertSessionHasErrors('rule');
    }

    public function test_a_proposal_can_be_declined_with_a_note_or_withdrawn(): void
    {
        $worker = User::factory()->employee()->create();
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($worker)->post('/app/rules', ['kind' => 'skill', 'phrase' => 'Peachtree']);
        $rule = LearnedRule::query()->sole();

        $this->actingAs($worker)->post("/app/rules/{$rule->id}/propose");
        $this->actingAs($worker)->post("/app/rules/{$rule->id}/withdraw");
        $this->assertNull($rule->fresh()->proposal_status);

        $this->travel(10)->seconds();      // (the same click twice within 8 seconds is ignored on purpose)
        $this->actingAs($worker)->post("/app/rules/{$rule->id}/propose");
        $this->actingAs($admin)->post("/admin/rule-requests/{$rule->id}/decline", ['note' => 'Too specific'])->assertSessionHasNoErrors();
        $this->assertSame(['declined', 'Too specific'], [$rule->fresh()->proposal_status, $rule->fresh()->decision_note]);
        $this->assertSame(0, LearnedRule::query()->massar()->count());
        // A decided proposal cannot be decided again.
        $this->actingAs($admin)->post("/admin/rule-requests/{$rule->id}/promote")->assertNotFound();
    }

    // ── Permissions and partners ────────────────────────────────────

    public function test_permissions_and_partners_are_respected(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $owner = User::factory()->employee($a)->create();
        $this->actingAs($owner)->post('/app/rules', ['kind' => 'skill', 'phrase' => 'Peachtree']);
        $rule = LearnedRule::query()->sole();

        // Someone who reviews CVs but may not teach.
        $reviewer = User::factory()->employee($a)->create(['permissions' => ['beneficiaries.view', 'cv.review', 'rules.view']]);
        [$cv] = $this->upload($owner, $this->docx($this->cvWithHeading('Where I Worked', 1)));
        $this->actingAs($reviewer)->post("/app/review-queue/{$cv['uuid']}/teach", ['kind' => 'heading', 'phrase' => 'Where I Worked', 'section' => 'experience'])->assertForbidden();
        $this->actingAs($reviewer)->post('/app/rules', ['kind' => 'skill', 'phrase' => 'SAP'])->assertForbidden();
        $this->actingAs($reviewer)->post("/app/rules/{$rule->id}/propose")->assertForbidden();
        $this->actingAs($reviewer)->get('/app/rules')->assertOk();
        $this->actingAs($reviewer)->get("/app/review-queue/{$cv['uuid']}")->assertInertia(fn (Assert $p) => $p->where('teach.can', false));

        // Another partner cannot see, change or propose this partner's rule.
        $stranger = User::factory()->employee($b)->create();
        $this->actingAs($stranger)->get('/app/rules')->assertInertia(fn (Assert $p) => $p->where('counts.ours', 0));
        $this->actingAs($stranger)->patch("/app/rules/{$rule->id}", ['kind' => 'skill', 'phrase' => 'X y'])->assertNotFound();
        $this->actingAs($stranger)->delete("/app/rules/{$rule->id}")->assertNotFound();
        $this->actingAs($stranger)->post("/app/rules/{$rule->id}/propose")->assertNotFound();

        // Only the Super Admin decides proposals.
        $this->actingAs($owner)->post("/app/rules/{$rule->id}/propose");
        $this->actingAs($owner)->get('/admin/rule-requests')->assertForbidden();
        $this->actingAs($owner)->post("/admin/rule-requests/{$rule->id}/promote")->assertForbidden();
        $this->assertSame(0, LearnedRule::query()->massar()->count());

        // Deleting a partner deletes its rules; a Massar rule promoted from it stays.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->post("/admin/rule-requests/{$rule->id}/promote");
        $a->delete();
        $this->assertSame(0, LearnedRule::query()->whereNotNull('company_id')->count());
        $this->assertSame(1, LearnedRule::query()->massar()->count());
    }
}
