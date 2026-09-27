<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\BeneficiaryChange;
use App\Models\Company;
use App\Models\CvDocument;
use App\Models\JobSeeker;
use App\Models\PoolAccessLog;
use App\Models\PoolAddition;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailCodeNotification;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesCvFiles;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — TalentPoolTest (Step 10 · Public Self-Registration +
//  Public Talent Pool)
//  Location: tests/Feature/TalentPoolTest.php
//
//    • the public home page; staff still go to their workspace
//    • registering without / with a CV, the same rules as the form,
//      one account per email and mobile, consent, the email code
//    • a CV unread because the site has no PDF reader says so (the
//      job seeker's file is not blamed)
//    • job seekers' sign-in is separate from staff sign-in (both ways)
//    • "My profile": edit, leave the pool, replace the CV (only the
//      ticked changes), delete everything
//    • partners: only confirmed, visible job seekers are listed; add to
//      my workspace makes the partner's own copy (profile + CV); many
//      partners; the duplicate question; the access log; permissions
//    • the Talent Pool workspace never shows as a partner
//    • pool:prune removes unfinished registrations
// ══════════════════════════════════════════════════════════════════

class TalentPoolTest extends TestCase
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
        config(['auth_verification.enabled' => false]);   // tests that need the code turn it on
    }

    protected function tearDown(): void
    {
        $this->tearDownSamples();
        parent::tearDown();
    }

    private function answers(array $over = []): array
    {
        return array_merge([
            'name_en' => 'Omar Ali', 'gender' => 'male', 'governorate' => 'cai', 'phone' => '01001234567', 'email' => 'Omar@Example.com',
            'education_level' => 'university', 'work_history' => [], 'skills' => ['Excel', 'Payroll'], 'languages' => [['code' => 'ar', 'level' => 'native']],
            'expected_salary' => '9,000', 'job_type' => 'full_time', 'notice_period' => '1_month',
            'password' => 'Strong#Pass1', 'password_confirmation' => 'Strong#Pass1', 'privacy' => true, 'visible' => true,
        ], $over);
    }

    /** A registered, confirmed job seeker (signed out afterwards). */
    private function seeker(array $over = []): JobSeeker
    {
        $this->post('/join', $this->answers($over))->assertSessionHasNoErrors();
        $s = JobSeeker::query()->where('email', strtolower($over['email'] ?? 'omar@example.com'))->sole();
        $this->post('/sign-out');

        return $s;
    }

    /** Signs a job seeker in the way they do it (not actingAs: staff and job seekers stay apart). */
    private function signIn(string $email): void
    {
        $this->post('/sign-in', ['email' => $email, 'password' => 'Strong#Pass1'])->assertRedirect('/me');
    }

    public function test_the_public_home_page_and_the_staff_sign_in(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Public/Home'));
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Auth/Login'));

        $staff = User::factory()->employee()->create();
        $this->actingAs($staff)->get('/')->assertRedirect('/app/dashboard');
    }

    public function test_a_job_seeker_registers_without_a_cv_and_confirms_the_email(): void
    {
        config(['auth_verification.enabled' => true]);
        Notification::fake();

        $this->get('/join?door=questions')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/Join')->where('door', 'questions')->has('options.notice_periods'));

        // The same rules as the registration form, plus: mobile, email, password, privacy.
        $this->post('/join', $this->answers(['phone' => null, 'privacy' => false, 'password_confirmation' => 'x']))
            ->assertSessionHasErrors(['phone', 'privacy', 'password']);
        $this->post('/join', $this->answers(['governorate' => 'xx']))->assertSessionHasErrors(['governorate']);
        $this->assertSame(0, JobSeeker::query()->count());

        $this->post('/join', $this->answers())->assertRedirect('/join/verify');
        $s = JobSeeker::query()->sole();
        $this->assertAuthenticatedAs($s, 'seeker');
        $this->assertGuest('web');
        $this->assertSame('omar@example.com', $s->email);
        $this->assertSame('01001234567', $s->phone);
        $this->assertSame('1_month', $s->notice_period);
        $this->assertNotNull($s->consented_at);
        $this->assertNull($s->email_verified_at);

        // The profile lives in the Talent Pool workspace, made by the job seeker.
        $b = Beneficiary::query()->withoutGlobalScopes()->findOrFail($s->beneficiary_id);
        $this->assertSame(Company::poolId(), $b->company_id);
        $this->assertSame(9000, $b->expected_salary);
        $this->assertSame('omar@example.com', $b->email);

        // Not confirmed yet: "My profile" waits for the code, and partners cannot see them.
        $this->get('/me')->assertRedirect('/join/verify');
        $this->assertSame(0, JobSeeker::query()->inPool()->count());

        $code = null;
        Notification::assertSentTo($s, VerifyEmailCodeNotification::class, function ($n) use (&$code) {
            $code = (new \ReflectionProperty($n, 'plainCode'))->getValue($n);

            return true;
        });
        $this->post('/join/verify', ['code' => '000000' === $code ? '111111' : '000000'])->assertSessionHasErrors(['code']);
        $this->post('/join/verify', ['code' => $code])->assertRedirect('/me');
        $this->assertNotNull($s->fresh()->email_verified_at);
        $this->assertSame(1, JobSeeker::query()->inPool()->count());
        $this->get('/me')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Public/Me')
            ->where('profile.name_en', 'Omar Ali')->where('account.visible', true)->where('added_by', []));

        // One account per email and per mobile.
        $this->post('/sign-out');
        $this->post('/join', $this->answers(['phone' => '01009998887']))->assertSessionHasErrors(['email']);
        $this->post('/join', $this->answers(['email' => 'other@example.com']))->assertSessionHasErrors(['phone']);
    }

    public function test_with_a_cv_the_reading_fills_the_journey_and_the_cv_is_kept(): void
    {
        $r = $this->post('/join/cv', ['file' => $this->docx($this->englishCv(), 'Ahmed.docx')], ['Accept' => 'application/json'])
            ->assertOk()->json();
        $this->assertTrue($r['cv']['read']);
        $this->assertSame('Ahmed Hassan Mahmoud', $r['initial']['name_en']);
        $this->assertSame('2411.1', $r['initial']['occupation']['esco']['code']);
        $doc = CvDocument::query()->withoutGlobalScopes()->where('uuid', $r['cv']['uuid'])->sole();
        $this->assertSame(Company::poolId(), $doc->company_id);
        $this->assertNull($doc->beneficiary_id);

        $i = $r['initial'];
        $this->post('/join', array_merge($this->answers(), array_intersect_key($i, array_flip(['name_en', 'gender', 'date_of_birth', 'military_status', 'governorate', 'city', 'phone', 'email', 'education_level', 'education', 'skills', 'languages'])), [
            'work_history' => array_map(fn ($j) => array_diff_key($j, ['check' => 1]), $i['work_history']),
            'esco_occupation_id' => $i['occupation']['esco_id'], 'cv' => $r['cv']['uuid'],
        ]))->assertSessionHasNoErrors()->assertRedirect('/join/verify');

        $s = JobSeeker::query()->sole();
        $this->assertSame('ahmed.hassan@example.com', $s->email);
        $b = Beneficiary::query()->withoutGlobalScopes()->findOrFail($s->beneficiary_id);
        $this->assertSame('cv_exact', $b->occupation_method);
        $doc->refresh();
        $this->assertSame(CvDocument::APPROVED, $doc->status);
        $this->assertSame($b->id, $doc->beneficiary_id);
        $this->get('/me/cv')->assertOk()->assertHeader('Content-Disposition');

        // A CV this browser did not upload cannot be used.
        $this->post('/sign-out');
        $other = $this->post('/join/cv', ['file' => $this->docx($this->englishCv(), 'B.docx')], ['Accept' => 'application/json'])->json();
        $this->flushSession();
        $this->post('/join', $this->answers(['email' => 'x@example.com', 'phone' => '01112223334', 'cv' => $other['cv']['uuid']]))
            ->assertSessionHasErrors(['cv']);
    }

    public function test_a_cv_unread_for_lack_of_a_pdf_reader_is_not_blamed_on_the_job_seeker(): void
    {
        config(['cv.pdftotext' => base_path('no-such-folder/pdftotext')]);
        $r = $this->post('/join/cv', ['file' => $this->pdf($this->englishCv(), 'Ahmed.pdf')], ['Accept' => 'application/json'])
            ->assertOk()->json();
        $this->assertFalse($r['cv']['read']);
        // The journey shows "a problem on our side", not "a scan, a locked PDF or an old Word file".
        $this->assertSame('no_pdf_reader', $r['cv']['problem']);
    }

    public function test_job_seeker_and_staff_sign_ins_are_separate(): void
    {
        $s = $this->seeker();
        $staff = User::factory()->employee()->create(['email' => 'staff@example.com', 'password' => 'Strong#Pass1']);

        // A job seeker's email and password do not open the workspace, and the other way round.
        $this->post('/login', ['email' => 'omar@example.com', 'password' => 'Strong#Pass1'])->assertSessionHasErrors(['email']);
        $this->assertGuest('web');
        $this->post('/sign-in', ['email' => 'staff@example.com', 'password' => 'Strong#Pass1'])->assertSessionHasErrors(['email']);
        $this->assertGuest('seeker');

        $this->post('/sign-in', ['email' => 'OMAR@example.com', 'password' => 'Strong#Pass1'])->assertRedirect('/me');
        $this->assertAuthenticatedAs($s, 'seeker');
        $this->get('/app/dashboard')->assertRedirect('/login');           // still a guest for the workspace
        $this->get('/sign-in')->assertRedirect('/me');

        $this->post('/sign-out')->assertRedirect('/');
        $this->get('/me')->assertRedirect('/sign-in');
    }

    public function test_forgot_password_for_job_seekers(): void
    {
        Notification::fake();
        $s = $this->seeker();

        $this->post('/sign-in/forgot', ['email' => 'nobody@example.com'])->assertSessionHas('status');
        $this->post('/sign-in/forgot', ['email' => 'omar@example.com'])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($s, ResetPasswordNotification::class, function ($n) use (&$token, $s) {
            $token = (new \ReflectionProperty($n, 'token'))->getValue($n);
            $this->assertStringContainsString('/sign-in/reset/', $n->toMail($s)->viewData['url']);

            return true;
        });

        $this->post('/sign-in/reset', ['token' => $token, 'email' => 'omar@example.com', 'password' => 'Newer#Pass22', 'password_confirmation' => 'Newer#Pass22'])
            ->assertRedirect('/sign-in');
        $this->post('/sign-in', ['email' => 'omar@example.com', 'password' => 'Newer#Pass22'])->assertRedirect('/me');
    }

    public function test_my_profile_edit_leave_the_pool_and_delete(): void
    {
        $s = $this->seeker();
        $this->signIn('omar@example.com');

        $this->get('/me/edit')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Public/Edit')->where('initial.phone', '01001234567'));
        $this->patch('/me', $this->answers(['phone' => '01201234567', 'city' => 'Maadi', 'email' => 'changed@example.com', 'notice_period' => 'now']))
            ->assertSessionHasNoErrors()->assertRedirect('/me');
        $s->refresh();
        $b = Beneficiary::query()->withoutGlobalScopes()->findOrFail($s->beneficiary_id);
        $this->assertSame('01201234567', $s->phone);
        $this->assertSame('now', $s->notice_period);
        $this->assertSame('Maadi', $b->city);
        $this->assertSame('omar@example.com', $b->email);                 // the sign-in email is not changed here
        $this->assertSame(BeneficiaryChange::UPDATED, BeneficiaryChange::query()->withoutGlobalScopes()->where('beneficiary_id', $b->id)->latest('id')->value('action'));

        // Leave the pool, and come back.
        $this->patch('/me/visibility', ['visible' => false])->assertSessionHasNoErrors();
        $this->assertSame(0, JobSeeker::query()->inPool()->count());
        $this->patch('/me/visibility', ['visible' => true]);
        $this->assertSame(1, JobSeeker::query()->inPool()->count());

        // Delete: the password is needed; everything goes.
        $this->delete('/me', ['password' => 'wrong'])->assertSessionHasErrors(['password']);
        $this->delete('/me', ['password' => 'Strong#Pass1'])->assertRedirect('/');
        $this->assertSame(0, JobSeeker::query()->count());
        $this->assertNull(Beneficiary::query()->withoutGlobalScopes()->find($b->id));
        $this->assertGuest('seeker');
    }

    public function test_a_newer_cv_makes_only_the_ticked_changes_and_replaces_the_old_one(): void
    {
        $r = $this->post('/join/cv', ['file' => $this->docx($this->englishCv(), 'Old.docx')], ['Accept' => 'application/json'])->json();
        $i = $r['initial'];
        $this->post('/join', array_merge($this->answers(['email' => 'ahmed.hassan@example.com', 'phone' => '01005551234']), [
            'name_en' => $i['name_en'], 'skills' => ['Excel'], 'cv' => $r['cv']['uuid'],
        ]))->assertSessionHasNoErrors();
        $s = JobSeeker::query()->sole();
        $old = CvDocument::query()->withoutGlobalScopes()->where('uuid', $r['cv']['uuid'])->sole();

        $this->post('/me/cv', ['file' => $this->docx($this->englishCv(['#TRAINING', 'Power BI course, 2024']), 'New.docx')])
            ->assertRedirect();
        $new = CvDocument::query()->withoutGlobalScopes()->whereNull('beneficiary_id')->sole();
        $page = $this->get('/me/cv/'.$new->uuid)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Public/CvChanges'));
        $rows = collect($page->viewData('page')['props']['rows']);
        $this->assertFalse($rows->contains(fn ($row) => ($row['field'] ?? null) === 'email'));
        $sap = $rows->first(fn ($row) => $row['group'] === 'skills' && $row['new'] === 'SAP');
        $this->assertNotNull($sap);

        $this->post('/me/cv/'.$new->uuid, ['ticked' => [$sap['id']]])->assertSessionHasNoErrors()->assertRedirect('/me');
        $b = Beneficiary::query()->withoutGlobalScopes()->findOrFail($s->beneficiary_id);
        $this->assertSame(['Excel', 'SAP'], $b->skills);                   // only the ticked change
        $this->assertNull(CvDocument::query()->withoutGlobalScopes()->find($old->id));   // one CV kept
        $this->assertSame(CvDocument::ATTACHED, $new->fresh()->status);
        $this->assertSame($b->id, $new->fresh()->beneficiary_id);
    }

    public function test_partners_find_and_add_job_seekers(): void
    {
        $r = $this->post('/join/cv', ['file' => $this->docx($this->englishCv(), 'Ahmed.docx')], ['Accept' => 'application/json'])->json();
        $this->post('/join', array_merge($this->answers(['email' => 'ahmed.hassan@example.com', 'phone' => '01005551234', 'name_en' => 'Ahmed Hassan Mahmoud']), [
            'esco_occupation_id' => $r['initial']['occupation']['esco_id'], 'cv' => $r['cv']['uuid'],
        ]))->assertSessionHasNoErrors();
        $this->post('/sign-out');
        $ahmed = JobSeeker::query()->sole();
        $hidden = $this->seeker(['email' => 'hidden@example.com', 'phone' => '01100000001', 'visible' => false]);
        config(['auth_verification.enabled' => true]);
        Notification::fake();
        $this->seeker(['email' => 'unconfirmed@example.com', 'phone' => '01100000002']);

        $a = User::factory()->employee()->create();
        // Only confirmed job seekers who chose to be seen.
        $this->actingAs($a)->get('/app/pool')->assertOk()->assertInertia(fn (Assert $p) => $p->component('App/Pool/Index')
            ->where('total', 1)->has('list.data', 1)->where('list.data.0.uuid', $ahmed->uuid)->where('list.data.0.added', false));
        // The CV Bank's own search: a word inside the CV.
        $this->actingAs($a)->get('/app/pool?q=Delta')->assertInertia(fn (Assert $p) => $p->has('list.data', 1));
        $this->actingAs($a)->get('/app/pool?q=zzzz')->assertInertia(fn (Assert $p) => $p->has('list.data', 0));
        $this->actingAs($a)->get('/app/pool/'.$hidden->uuid)->assertNotFound();

        $this->actingAs($a)->get('/app/pool/'.$ahmed->uuid)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('App/Pool/Show')->where('copy', null)->where('cv.file', 'Ahmed.docx'));
        $this->actingAs($a)->get('/app/pool/'.$ahmed->uuid.'/cv')->assertOk();

        // Add: the partner's own copy of the profile and CV.
        $this->actingAs($a)->post('/app/pool/'.$ahmed->uuid.'/add')->assertRedirect('/app/beneficiaries/1');
        $copy = Beneficiary::query()->withoutGlobalScopes()->where('company_id', $a->company_id)->sole();
        $this->assertSame('Ahmed Hassan Mahmoud', $copy->name_en);
        $this->assertSame('cv_exact', $copy->occupation_method);
        $this->assertSame($a->id, $copy->created_by);
        $this->assertTrue(BeneficiaryChange::query()->withoutGlobalScopes()->where('beneficiary_id', $copy->id)->sole()->changes['_from_pool']);
        $cvCopy = CvDocument::query()->withoutGlobalScopes()->where('company_id', $a->company_id)->sole();
        $this->assertSame($copy->id, $cvCopy->beneficiary_id);
        $this->assertSame(CvDocument::ATTACHED, $cvCopy->status);
        $this->actingAs($a)->get('/app/cv-files/'.$cvCopy->uuid)->assertOk();
        $this->actingAs($a)->get('/app/beneficiaries/1')->assertInertia(fn (Assert $p) => $p->where('history.0.from_pool', true));

        // Adding again opens the same copy.
        $this->actingAs($a)->post('/app/pool/'.$ahmed->uuid.'/add')->assertRedirect('/app/beneficiaries/1');
        $this->assertSame(1, Beneficiary::query()->withoutGlobalScopes()->where('company_id', $a->company_id)->count());
        $this->actingAs($a)->get('/app/pool')->assertInertia(fn (Assert $p) => $p->where('list.data.0.added', true));

        // Another partner, with the same mobile already registered: the duplicate question first.
        $b = User::factory()->employee()->create();
        $this->actingAs($b)->post('/app/beneficiaries', ['name_en' => 'Ahmed H', 'gender' => 'male', 'governorate' => 'cai', 'phone' => '01005551234'])->assertSessionHasNoErrors();
        $this->actingAs($b)->post('/app/pool/'.$ahmed->uuid.'/add')->assertSessionHasErrors(['duplicate']);
        $this->actingAs($b)->post('/app/pool/'.$ahmed->uuid.'/add', ['confirm_duplicate' => true])->assertRedirect('/app/beneficiaries/2');

        // Many partners; the job seeker sees them in "My profile" (and is not notified).
        $this->assertSame(2, PoolAddition::query()->where('job_seeker_id', $ahmed->id)->count());
        $this->signIn('ahmed.hassan@example.com');
        $this->get('/me')->assertInertia(fn (Assert $p) => $p->has('added_by', 2));

        // Every opening, CV download and addition is logged.
        $this->assertSame(['view', 'cv', 'add', 'add'], PoolAccessLog::query()->where('job_seeker_id', $ahmed->id)->orderBy('id')->pluck('action')->all());

        // Copies stay with the partners when the job seeker deletes their account.
        $this->delete('/me', ['password' => 'Strong#Pass1']);
        $this->assertSame(3, Beneficiary::query()->withoutGlobalScopes()->where('company_id', '!=', Company::poolId())->count());   // 2 copies + B's own
        $this->assertSame(2, PoolAddition::query()->whereNull('job_seeker_id')->count());
    }

    public function test_permissions_and_the_pool_is_never_a_partner(): void
    {
        $s = $this->seeker();
        $company = Company::factory()->create();
        $noPool = User::factory()->employee($company)->create(['permissions' => ['beneficiaries.view', 'beneficiaries.create']]);
        $viewOnly = User::factory()->employee($company)->create(['permissions' => ['beneficiaries.view', 'pool.view']]);

        $this->actingAs($noPool)->get('/app/pool')->assertForbidden();
        $this->actingAs($viewOnly)->get('/app/pool')->assertOk();
        $this->actingAs($viewOnly)->get('/app/pool/'.$s->uuid)->assertOk()->assertInertia(fn (Assert $p) => $p->where('can_add', false));
        $this->actingAs($viewOnly)->post('/app/pool/'.$s->uuid.'/add')->assertForbidden();
        $this->actingAs($viewOnly)->get('/app/pool/'.$s->uuid.'/cv')->assertForbidden();

        // The partner's own lists never show the pool's profiles.
        $this->actingAs($viewOnly)->get('/app/beneficiaries')->assertInertia(fn (Assert $p) => $p->has('list.data', 0));
        $this->actingAs($viewOnly)->get('/app/cv-bank?q=Omar')->assertInertia(fn (Assert $p) => $p->where('total', 0));

        // The Talent Pool workspace is not a partner.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get('/admin/companies')->assertOk();
        $this->assertFalse(Company::query()->where('is_pool', true)->exists());
        $this->assertSame(1, Company::query()->withoutGlobalScope('partners')->where('is_pool', true)->count());
    }

    public function test_unfinished_registrations_are_removed(): void
    {
        config(['auth_verification.enabled' => true]);
        Notification::fake();
        $this->post('/join/cv', ['file' => $this->docx($this->englishCv(), 'Left.docx')], ['Accept' => 'application/json']);
        $this->seeker(['email' => 'late@example.com']);
        $keep = $this->seeker(['email' => 'new@example.com', 'phone' => '01100000009']);

        $this->travel(8)->days();
        $keep->forceFill(['email_verified_at' => now()])->save();
        $this->artisan('pool:prune')->assertSuccessful();

        $this->assertSame(['new@example.com'], JobSeeker::query()->pluck('email')->all());
        $this->assertSame(0, CvDocument::query()->withoutGlobalScopes()->whereNull('beneficiary_id')->count());
    }
}
