<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Company;
use App\Models\CvAccessLog;
use App\Models\CvDocument;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use App\Services\Cv\CvReader;
use App\Services\Cv\TextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesCvFiles;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvBankTest (Step 6 · CV upload, reading, review)
//  Location: tests/Feature/CvBankTest.php
//
//  With real Word and PDF files made by the test (MakesCvFiles), on
//  the sample backbone (2411 Accountants: accountant, accounting
//  analyst; 3221 Nursing, group level only):
//    • a complete English Word CV is read and registered automatically:
//      every field, the occupation (exact match, method cv_exact), the
//      history row, the file kept ENCRYPTED on the private disk
//    • an English PDF (needs Poppler) is read the same way
//    • an Arabic CV (female, Arabic digits, years without months) is
//      read but waits for review: the months would be a guess
//    • gender is never guessed from a name
//    • a title matching two occupations equally goes to review, never guessed
//    • scans, password-protected PDFs, old .doc files: "could not be
//      read" (each with its own reason), file still kept
//    • no PDF reader yet: "could not be read", never blamed on the file;
//      "Read again" reads it once the reader is set up, and the upload
//      page's warning goes as soon as it is
//    • duplicates: same mobile / email / file in THIS workspace only
//    • the review screen; approve (method cv_review), attach, reject
//      (file deleted, text wiped); a decided CV cannot be decided again
//    • downloads need cv.download and are logged
//    • PARTNER SEPARATION: another partner sees none of it
//    • limits: file type, batch size, files per batch
//    • reader rules: broken Arabic "لا", reversed Arabic years,
//      national ID, unknown headings
// ══════════════════════════════════════════════════════════════════

class CvBankTest extends TestCase
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

    /** Upload files the way the upload page does: one batch, then file by file. */
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

    // ── Reading and automatic registration ──────────────────────────

    public function test_a_complete_word_cv_is_read_and_registered_automatically(): void
    {
        $worker = User::factory()->employee()->create();
        [$result] = $this->upload($worker, $this->docx($this->englishCv(), 'Ahmed CV.docx'));

        $this->assertSame('added', $result['status']);
        $this->assertSame(1, $result['beneficiary']['number']);

        $b = Beneficiary::query()->withoutGlobalScopes()->sole();
        $this->assertSame($worker->company_id, $b->company_id);
        $this->assertSame('Ahmed Hassan Mahmoud', $b->name_en);
        $this->assertSame('male', $b->gender);
        $this->assertSame('completed', $b->military_status);
        $this->assertSame('1995-05-23', $b->date_of_birth->format('Y-m-d'));
        $this->assertSame('cai', $b->governorate);
        $this->assertSame('Nasr City', $b->city);
        $this->assertSame('01005551234', $b->phone);
        $this->assertSame('ahmed.hassan@example.com', $b->email);
        $this->assertSame('university', $b->education_level);
        $this->assertSame('Cairo University', $b->education[0]['institution']);
        $this->assertSame(2017, $b->education[0]['year']);
        $this->assertCount(2, $b->work_history);
        $this->assertSame(['title' => 'Accountant', 'employer' => 'Delta Trading', 'location' => null, 'from' => '2021-03', 'to' => null, 'current' => true,
            'responsibilities' => ['Prepared monthly financial statements'], 'country' => 'EG'], $b->work_history[0]);
        $this->assertSame(['title' => 'Junior Accountant', 'employer' => 'Nile Foods', 'location' => null, 'from' => '2018-01', 'to' => '2021-02', 'current' => false,
            'responsibilities' => [], 'country' => 'EG'], $b->work_history[1]);
        $this->assertSame(['Microsoft Excel', 'SAP', 'Budget planning'], $b->skills);
        $this->assertSame([['code' => 'ar', 'level' => 'native'], ['code' => 'en', 'level' => 'fluent']], $b->languages);

        // "Senior Accountant" → the ESCO job accountant, exactly, chosen by the engine.
        $this->assertSame('2411.1', $b->escoOccupation->code);
        $this->assertSame('cv_exact', $b->occupation_method);
        $this->assertSame('created', $b->history()->first()->action);

        // The original is kept, encrypted, on the private disk.
        $doc = CvDocument::query()->withoutGlobalScopes()->sole();
        $this->assertSame($b->id, $doc->beneficiary_id);
        Storage::disk('cvs')->assertExists($doc->stored_path);
        $this->assertStringStartsWith($worker->company_id.'/', $doc->stored_path);
        $this->assertStringNotContainsString('word/document.xml', Storage::disk('cvs')->get($doc->stored_path));
    }

    public function test_an_english_pdf_cv_is_read_with_poppler(): void
    {
        $this->needsPdfReader();
        $worker = User::factory()->employee()->create();
        [$result] = $this->upload($worker, $this->pdf($this->englishCv(), 'ahmed.pdf'));

        $this->assertSame('added', $result['status'], 'Problem: '.($result['problem'] ?? '-').' '.json_encode($this->doc($result['uuid'])->reading['attention'] ?? null)
            .' — run `php artisan cv:check` to see what Poppler says.');
        $b = Beneficiary::query()->withoutGlobalScopes()->sole();
        $this->assertSame('Ahmed Hassan Mahmoud', $b->name_en);
        $this->assertSame('2021-03', $b->work_history[0]['from']);
    }

    public function test_an_arabic_cv_is_read_but_years_without_months_wait_for_review(): void
    {
        $worker = User::factory()->employee()->create();
        [$result] = $this->upload($worker, $this->docx($this->arabicCv(), 'منى.docx'));

        $this->assertSame('review', $result['status']);
        $this->assertSame('منى عبد الرحمن السيد', $result['name']);
        $this->assertContains('work_history', $result['attention']);
        $this->assertSame(0, Beneficiary::query()->withoutGlobalScopes()->count());

        $r = $this->doc($result['uuid'])->reading;
        $this->assertSame('female', $r['form']['gender']);                    // from "عزباء"
        $this->assertSame('gender_from_marital', $r['notes']['gender']);
        $this->assertSame('01223456789', $r['form']['phone']);                // Arabic digits
        $this->assertStringContainsString('٠١٢٢٣٤٥٦٧٨٩', $this->doc($result['uuid'])->text);   // shown as written
        $this->assertSame('1998-03-12', $r['form']['date_of_birth']);
        $this->assertSame('cai', $r['form']['governorate']);
        $this->assertSame('المعادي', $r['form']['city']);
        $this->assertSame([['code' => 'ar', 'level' => 'native'], ['code' => 'en', 'level' => 'good']], $r['form']['languages']);
        $this->assertSame('جامعة عين شمس', $r['form']['education'][0]['institution']);
        $this->assertSame(['title' => 'محاسبة', 'employer' => 'شركة النيل للتجارة', 'location' => null, 'from' => '2020-01', 'to' => null, 'current' => true,
            'responsibilities' => [], 'check' => ['dates'], 'country' => 'EG'], $r['form']['work_history'][0]);
        $this->assertSame('check', $r['marks']['work_history']);
        // "محاسبة" is the Arabic ESCO title of accountant: an exact match.
        $this->assertSame('exact', $r['occupation']['status']);
        $this->assertSame('2411.1', $r['occupation']['choice']['esco']['code']);
    }

    public function test_gender_is_never_guessed_from_a_name(): void
    {
        $worker = User::factory()->employee()->create();
        [$result] = $this->upload($worker, $this->docx(['Sara Adel', 'Accountant', 'Giza | 0111 222 3344', '#Experience',
            'Accountant - Pixel Co, Jun 2019 - Dec 2023', '- Prepared the monthly accounts and the yearly budget for the studio']));

        $this->assertSame('review', $result['status']);
        $this->assertContains('gender', $result['attention']);
        $this->assertNull($this->doc($result['uuid'])->reading['form']['gender']);
    }

    public function test_a_title_matching_two_occupations_equally_is_never_guessed(): void
    {
        // "finance officer" is an alternative title of both ESCO jobs under 2411.
        foreach (['2411.1', '2411.1.1'] as $code) {
            $e = DB::table('esco_occupations')->where('code', $code)->first();
            DB::table('occupation_labels')->insert(['isco_group_id' => $e->isco_group_id, 'esco_occupation_id' => $e->id, 'source' => 'esco',
                'lang' => 'en', 'kind' => 'alt', 'label' => 'finance officer', 'normalized' => 'finance officer']);
        }
        $worker = User::factory()->employee()->create();
        $cv = $this->englishCv();
        $cv[1] = 'Finance Officer';
        $cv[6] = 'Finance Officer — Delta Trading | Mar 2021 – Present';
        $cv[8] = 'Finance Officer, Nile Foods (01/2018 - 02/2021)';
        [$result] = $this->upload($worker, $this->docx($cv));

        $this->assertSame('review', $result['status']);
        $occ = $this->doc($result['uuid'])->reading['occupation'];
        $this->assertSame('ambiguous', $occ['status']);
        $this->assertSame('Finance Officer', $occ['title']);
        $this->assertCount(2, $occ['candidates']);
        $this->assertNull($occ['choice']);
    }

    public function test_scans_locked_pdfs_and_old_word_files_cannot_be_read_but_are_kept(): void
    {
        $worker = User::factory()->employee()->create();
        [$old] = $this->upload($worker, $this->oldDoc());
        $this->assertSame('unreadable', $old['status']);
        $this->assertSame('old_word', $old['problem']);
        Storage::disk('cvs')->assertExists($this->doc($old['uuid'])->stored_path);

        $this->needsPdfReader();
        [$scan] = $this->upload($worker, $this->scannedPdf());
        $this->assertSame('unreadable', $scan['status']);
        $this->assertSame('scanned', $scan['problem'], 'Run `php artisan cv:check` to see what Poppler says.');

        // Password-protected: said so, not "damaged".
        [$locked] = $this->upload($worker, $this->lockedPdf());
        $this->assertSame('unreadable', $locked['status']);
        $this->assertSame('locked', $locked['problem'], 'Run `php artisan cv:check` to see what Poppler says.');
        Storage::disk('cvs')->assertExists($this->doc($locked['uuid'])->stored_path);
    }

    // ── No PDF reader yet ───────────────────────────────────────────

    public function test_a_missing_pdf_reader_is_never_blamed_on_the_file(): void
    {
        $worker = User::factory()->employee()->create();

        // A wrong path, and a path to something that is not pdftotext (a
        // folder): the errors differ, but the reader is missing either way.
        foreach ([base_path('no-such-folder/pdftotext'), base_path('tests')] as $wrong) {
            config(['cv.pdftotext' => $wrong]);
            [$result] = $this->upload($worker, $this->pdf($this->englishCv()));
            $this->assertSame('unreadable', $result['status'], $wrong);
            $this->assertSame('no_pdf_reader', $result['problem'], $wrong);
            Storage::disk('cvs')->assertExists($this->doc($result['uuid'])->stored_path);
        }
    }

    public function test_a_pdf_uploaded_before_the_reader_was_set_up_is_read_with_read_again(): void
    {
        $this->needsPdfReader();
        $reader = config('cv.pdftotext');
        $worker = User::factory()->employee()->create();

        config(['cv.pdftotext' => base_path('no-such-folder/pdftotext')]);
        [$result] = $this->upload($worker, $this->pdf($this->englishCv(), 'ahmed.pdf'));
        $this->assertSame('no_pdf_reader', $result['problem']);
        $this->actingAs($worker)->get("/app/review-queue/{$result['uuid']}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('doc.problem', 'no_pdf_reader')->where('doc.text', null));

        // Still missing: the reviewer is told so, not "read again".
        $this->actingAs($worker)->post("/app/review-queue/{$result['uuid']}/reread")
            ->assertRedirect(route('app.review-queue.show', $result['uuid']))->assertSessionHas('warning', __('cv.no_pdf_reader'));
        $this->assertSame('no_pdf_reader', $this->doc($result['uuid'])->problem);

        // Set up: the CV kept at the upload is read, and waits for the reviewer.
        config(['cv.pdftotext' => $reader]);
        $this->travel(10)->seconds();      // (the same click twice within 8 seconds is ignored on purpose)
        $this->actingAs($worker)->post("/app/review-queue/{$result['uuid']}/reread")->assertSessionHas('success', __('cv.reread'));
        $doc = $this->doc($result['uuid']);
        $this->assertSame('review', $doc->status);
        $this->assertNull($doc->problem);
        $this->assertStringContainsString('Ahmed Hassan Mahmoud', $doc->text);
        $this->assertSame('ahmed.hassan@example.com', $doc->email);
        $this->actingAs($worker)->get("/app/review-queue/{$result['uuid']}")
            ->assertInertia(fn (Assert $p) => $p->where('doc.problem', null)->where('initial.name_en', 'Ahmed Hassan Mahmoud'));
    }

    public function test_the_upload_page_warning_goes_as_soon_as_the_pdf_reader_is_set_up(): void
    {
        $worker = User::factory()->employee()->create();
        // Missing, then set up. twice(): the third visit must not start the reader again.
        $this->partialMock(TextExtractor::class, fn ($m) => $m->shouldReceive('pdfReaderReady')->twice()->andReturn(false, true));

        $page = fn () => $this->actingAs($worker)->get('/app/cv-upload')->assertOk();
        $page()->assertInertia(fn (Assert $p) => $p->where('pdf_ready', false));
        $page()->assertInertia(fn (Assert $p) => $p->where('pdf_ready', true));    // a "no" is not remembered
        $page()->assertInertia(fn (Assert $p) => $p->where('pdf_ready', true));    // a "yes" is, for 10 minutes
    }

    // ── Duplicates (this workspace only) ────────────────────────────

    public function test_duplicates_are_found_in_the_same_workspace_only(): void
    {
        $company = Company::factory()->create();
        $worker = User::factory()->employee($company)->create();
        [$first] = $this->upload($worker, $this->docx($this->englishCv()));
        $this->assertSame('added', $first['status']);

        // Same person again (same mobile), in the same workspace → duplicate.
        [$again] = $this->upload($worker, $this->docx($this->englishCv(), 'again.docx'));
        $this->assertSame('duplicate', $again['status']);
        $this->assertContains('profile', array_column($this->doc($again['uuid'])->duplicates, 'type'));

        // The very same file → also flagged as the same file.
        $file = $this->docx(['Omar Ali', 'Nurse', 'Cairo | 01098765432', 'Gender: Male']);
        copy($file->getRealPath(), $copy = tempnam(sys_get_temp_dir(), 'cv'));
        [, $sameFile] = $this->upload($worker, $file, new UploadedFile($copy, 'copy.docx', null, null, true));
        $this->assertContains('file', array_column($this->doc($sameFile['uuid'])->duplicates, 'type'));

        // Another partner uploading the same person: not a duplicate for them.
        $other = User::factory()->employee()->create();
        [$theirs] = $this->upload($other, $this->docx($this->englishCv()));
        $this->assertSame('added', $theirs['status']);
    }

    // ── Review ──────────────────────────────────────────────────────

    public function test_the_review_screen_and_approving_a_cv(): void
    {
        $worker = User::factory()->employee()->create();
        [$result] = $this->upload($worker, $this->docx($this->arabicCv()));

        $this->actingAs($worker)->get('/app/review-queue')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('App/Cv/Queue')->where('counts.review', 1)->has('list.data', 1));

        $this->actingAs($worker)->get("/app/review-queue/{$result['uuid']}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('App/Cv/Review')
                ->where('doc.marks.work_history', 'check')
                ->where('initial.name_ar', 'منى عبد الرحمن السيد')
                ->where('initial.occupation.esco.code', '2411.1')
                ->has('doc.text'));

        // The reviewer confirms the months and approves, choosing a different job.
        $analyst = DB::table('esco_occupations')->where('code', '2411.1.1')->value('id');
        $form = [
            'name_ar' => 'منى عبد الرحمن السيد', 'gender' => 'female', 'governorate' => 'cai', 'phone' => '01223456789',
            'work_history' => [['title' => 'محاسبة', 'employer' => 'شركة النيل للتجارة', 'from' => '2020-09', 'to' => '', 'current' => true]],
            'esco_occupation_id' => $analyst,
        ];
        $this->actingAs($worker)->post("/app/review-queue/{$result['uuid']}/approve", $form)
            ->assertRedirect('/app/review-queue')->assertSessionHas('success');

        $b = Beneficiary::query()->withoutGlobalScopes()->sole();
        $this->assertSame('cv_review', $b->occupation_method);
        $this->assertSame('2020-09', $b->work_history[0]['from']);
        $doc = $this->doc($result['uuid']);
        $this->assertSame('approved', $doc->status);
        $this->assertSame($b->id, $doc->beneficiary_id);
        $this->assertSame($worker->name, $doc->reviewed_by_name);

        // Decided once only.
        $this->actingAs($worker)->post("/app/review-queue/{$result['uuid']}/approve", $form + ['confirm_duplicate' => true])
            ->assertSessionHasErrors('cv');
        $this->assertSame(1, Beneficiary::query()->withoutGlobalScopes()->count());

        // The profile lists the original CV.
        $this->actingAs($worker)->get('/app/beneficiaries/1')
            ->assertInertia(fn (Assert $p) => $p->has('cvs', 1)->where('cvs.0.uuid', $result['uuid']));
    }

    public function test_attaching_to_an_existing_profile_and_rejecting(): void
    {
        $worker = User::factory()->employee()->create();
        [$first] = $this->upload($worker, $this->docx($this->englishCv()));
        [$dup, $junk] = $this->upload($worker, $this->docx($this->englishCv(), 'newer.docx'), $this->docx(['Shopping list', 'Eggs, milk and bread for the week ahead please', 'Tomatoes and potatoes and onions']));

        $this->actingAs($worker)->post("/app/review-queue/{$dup['uuid']}/attach", ['number' => 99])->assertSessionHasErrors('number');
        $this->actingAs($worker)->post("/app/review-queue/{$dup['uuid']}/attach", ['number' => 1])->assertSessionHas('success');
        $this->assertSame('attached', $this->doc($dup['uuid'])->status);
        $this->assertSame(1, Beneficiary::query()->withoutGlobalScopes()->count());

        $path = $this->doc($junk['uuid'])->stored_path;
        $this->actingAs($worker)->post("/app/review-queue/{$junk['uuid']}/reject")->assertSessionHas('success');
        $gone = $this->doc($junk['uuid']);
        $this->assertSame('rejected', $gone->status);
        $this->assertNull($gone->text);
        $this->assertNull($gone->reading);
        Storage::disk('cvs')->assertMissing($path);
    }

    public function test_downloads_need_permission_and_are_logged(): void
    {
        $worker = User::factory()->employee()->create();
        [$result] = $this->upload($worker, $this->docx($this->englishCv()));

        $this->actingAs($worker)->get("/app/cv-files/{$result['uuid']}")->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $log = CvAccessLog::sole();
        $this->assertSame([$worker->id, 'download'], [$log->user_id, $log->action]);

        // Someone without "Download original CV files".
        $limited = User::factory()->employee($worker->company)->create(['permissions' => ['beneficiaries.view', 'cv.review']]);
        $this->actingAs($limited)->get("/app/cv-files/{$result['uuid']}")->assertForbidden();
        $this->actingAs($limited)->get('/app/cv-upload')->assertForbidden();
    }

    public function test_another_partner_sees_nothing(): void
    {
        $worker = User::factory()->employee()->create();
        [$result] = $this->upload($worker, $this->docx($this->arabicCv()));
        $outsider = User::factory()->companyAdmin()->create();

        $this->actingAs($outsider)->get("/app/review-queue/{$result['uuid']}")->assertNotFound();
        $this->actingAs($outsider)->get("/app/cv-files/{$result['uuid']}")->assertNotFound();
        $this->actingAs($outsider)->post("/app/review-queue/{$result['uuid']}/reject")->assertNotFound();
        $this->actingAs($outsider)->get('/app/review-queue')
            ->assertInertia(fn (Assert $p) => $p->has('list.data', 0)->where('counts.review', 0));
        // Nor can they send files into someone else's upload.
        $batch = $this->doc($result['uuid'])->batch_id;
        $this->actingAs($outsider)->post("/app/cv-upload/batches/{$batch}/files", ['file' => $this->docx($this->englishCv()), 'client_id' => 'x'],
            ['Accept' => 'application/json'])->assertNotFound();
        $this->assertSame('review', $this->doc($result['uuid'])->status);
    }

    public function test_upload_limits(): void
    {
        $worker = User::factory()->employee()->create();
        $this->actingAs($worker)->get('/app/cv-upload')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('App/Cv/Upload')->where('limits.max_files', 50));

        $this->actingAs($worker)->postJson('/app/cv-upload/batches', ['files' => 51, 'client_id' => 'b2'])->assertStatus(422);
        $batch = $this->actingAs($worker)->postJson('/app/cv-upload/batches', ['files' => 1, 'client_id' => 'b3'])->json('batch');

        $txt = UploadedFile::fake()->createWithContent('cv.txt', 'Ahmed');
        $this->actingAs($worker)->post("/app/cv-upload/batches/{$batch}/files", ['file' => $txt, 'client_id' => 'f1'], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->actingAs($worker)->post("/app/cv-upload/batches/{$batch}/files", ['file' => $this->docx($this->englishCv()), 'client_id' => 'f2'], ['Accept' => 'application/json'])
            ->assertOk();
        // The batch said one file; a second one is refused.
        $this->actingAs($worker)->post("/app/cv-upload/batches/{$batch}/files", ['file' => $this->docx($this->arabicCv()), 'client_id' => 'f3'], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    // ── Reader rules ────────────────────────────────────────────────

    public function test_reader_rules_for_difficult_text(): void
    {
        $reader = app(CvReader::class);

        // Broken "لا" from some Arabic PDFs, reversed years, the national ID.
        $text = "كريم محمود\nالعنوان: أسيوط\nالرقم القومي: 29605141234512\n\nالخبرات العملية\nفني كهرباء - مصنع الأمل (2020 - 2018)\n\nاللغات\nاإلنجليزية: مبتدئ";
        [$repaired, $broken] = \App\Services\Cv\ArabicRepair::repair($text);
        $this->assertTrue($broken);
        $r = $reader->read($repaired, $broken);
        $this->assertSame('ast', $r['form']['governorate']);
        $this->assertSame('1996-05-14', $r['form']['date_of_birth']);
        $this->assertSame('male', $r['form']['gender']);                       // 13th digit 1 = odd = male
        $this->assertSame('gender_from_national_id', $r['notes']['gender']);
        $this->assertSame('2018-01', $r['form']['work_history'][0]['from']);
        $this->assertSame('2020-12', $r['form']['work_history'][0]['to']);
        $this->assertSame([['code' => 'en', 'level' => 'basic']], $r['form']['languages']);

        // An unknown heading is reported for the reviewer; its jobs are read but marked "check".
        // ("Career Path" used to be the example; it is in the reference files now, so it is known.)
        $r = $reader->read("Karim Fathy\nSales Executive\nAlexandria | 01011223344\n\nWhere I Worked\nSales Executive at Aramex (2022 – Present)");
        $this->assertCount(1, $r['marks_on_text']['unknown']);
        $this->assertSame('Aramex', $r['form']['work_history'][0]['employer']);
        $this->assertSame('check', $r['marks']['work_history']);

        // A word inside a skills list that is really a detail line is not a skill.
        $r = $reader->read("Omar Ali\nNurse\nCairo 01098765432\n\nSkills\nFirst aid, Patient care\nGender: Male");
        $this->assertSame(['First aid', 'Patient care'], $r['form']['skills']);
        $this->assertSame('male', $r['form']['gender']);

        $this->assertInstanceOf(TextExtractor::class, app(TextExtractor::class));
    }
}
