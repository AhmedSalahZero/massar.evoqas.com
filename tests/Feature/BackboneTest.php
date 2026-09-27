<?php

namespace Tests\Feature;

use App\Models\Backbone\BackboneImport;
use App\Models\Backbone\EnocOccupation;
use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\OccupationSearch;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — BackboneTest
//  Location: tests/Feature/BackboneTest.php
//
//  The occupation backbone (Scope v2 §1), on small sample files laid
//  out exactly like the official ones:
//    • the import links ENOC ↔ ISCO-08 ↔ ESCO and splits Arabic
//      masculine / feminine titles
//    • re-importing is skipped when nothing changed, and never
//      changes IDs when forced
//    • a broken file stops the import and changes nothing
//    • search works in Arabic (with or without "ال", either gender)
//      and English
//    • only the Super Admin can open the Backbone screens
//    • the ENOC / ISCO-08 / ESCO switch is saved without disturbing
//      the page (it used to snap back to the saved standard)
//  The real files are never touched by the tests.
// ══════════════════════════════════════════════════════════════════

class BackboneTest extends TestCase
{
    use RefreshDatabase;
    use WritesBackboneSamples;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSamples();
    }

    protected function tearDown(): void
    {
        $this->tearDownSamples();
        parent::tearDown();
    }

    // ── Import ───────────────────────────────────────────────────────

    public function test_import_links_the_three_standards(): void
    {
        $import = BackboneImporter::make()->run();

        $this->assertSame(BackboneImport::COMPLETED, $import->status);
        $this->assertSame(1, $import->counts['esco_duplicate_rows_skipped']);
        $this->assertSame(['3221'], $import->counts['units_without_esco']);
        $this->assertSame(['0110'], $import->counts['units_without_enoc']);

        $unit = IscoGroup::where('code', '2411')->firstOrFail();
        $this->assertSame(4, $unit->level);
        $this->assertSame('241', $unit->parent->code);
        $this->assertSame('المحاسبون', $unit->title_ar);
        $this->assertSame('0110', IscoGroup::where('code', '0110')->value('code'), 'leading zeros are kept');

        $enoc = EnocOccupation::where('code', '2411')->firstOrFail();
        $this->assertSame($unit->id, $enoc->isco_group_id);
        $this->assertSame('محاسبون', $enoc->title_ar, 'extra spaces are tidied');

        $accountant = EscoOccupation::where('code', '2411.1')->firstOrFail();
        $analyst = EscoOccupation::where('code', '2411.1.1')->firstOrFail();
        $this->assertSame($unit->id, $accountant->isco_group_id);
        $this->assertSame($accountant->id, $analyst->parent_id);
        $this->assertSame('محاسب', $accountant->title_ar_male);
        $this->assertSame('محاسبة', $accountant->title_ar_female);

        $this->assertDatabaseHas('occupation_labels', ['esco_occupation_id' => $accountant->id, 'kind' => 'female', 'label' => 'محاسبة']);
        $this->assertDatabaseHas('occupation_labels', ['esco_occupation_id' => $accountant->id, 'kind' => 'alt', 'label' => 'bookkeeper']);
    }

    public function test_reimport_is_skipped_when_unchanged_and_keeps_ids_when_forced(): void
    {
        BackboneImporter::make()->run();
        $ids = EscoOccupation::orderBy('uri')->pluck('id', 'uri')->all();

        $this->assertSame(BackboneImport::UNCHANGED, BackboneImporter::make()->run()->status);

        $forced = BackboneImporter::make()->run(force: true);
        $this->assertSame(BackboneImport::COMPLETED, $forced->status);
        $this->assertSame($ids, EscoOccupation::orderBy('uri')->pluck('id', 'uri')->all());
        $this->assertSame(0, EscoOccupation::where('is_active', false)->count());
    }

    public function test_a_broken_file_stops_the_import_and_changes_nothing(): void
    {
        BackboneImporter::make()->run();
        $before = DB::table('occupation_labels')->count();

        // An ENOC code that does not exist in ISCO-08.
        $this->writeOutlook([$this->outlookRow('9999', 'مهنة غير موجودة', 'وصف', [])]);

        try {
            BackboneImporter::make()->run();
            $this->fail('The import should have stopped.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('9999', $e->getMessage());
        }

        $this->assertSame(BackboneImport::FAILED, BackboneImport::latest('id')->value('status'));
        $this->assertSame($before, DB::table('occupation_labels')->count());
        $this->assertDatabaseMissing('enoc_occupations', ['code' => '9999']);
    }

    public function test_the_command_reports_a_missing_file_clearly(): void
    {
        unlink($this->sampleDir.'/esco/occupations_ar.csv');

        $this->artisan('backbone:import')
            ->expectsOutputToContain('Nothing was changed')
            ->expectsOutputToContain('occupations_ar.csv')
            ->assertFailed();
    }

    // ── Search ───────────────────────────────────────────────────────

    public function test_search_finds_occupations_in_arabic_and_english(): void
    {
        BackboneImporter::make()->run();
        $search = new OccupationSearch;
        $accountant = EscoOccupation::where('code', '2411.1')->value('id');
        $unit = IscoGroup::where('code', '2411')->value('id');

        foreach (['محاسبة', 'المحاسب', 'مُحاسِب', 'Accountant', 'bookkeeper'] as $text) {
            $this->assertSame($accountant, array_key_first($search->esco($text)), "ESCO search for \"{$text}\"");
            $this->assertArrayHasKey($unit, $search->units($text), "unit search for \"{$text}\"");
        }

        $this->assertSame([], $search->esco('طيار'));
    }

    public function test_arabic_text_is_normalised_the_same_way_everywhere(): void
    {
        $this->assertSame('محاسبه قانونيه', TextNormalizer::normalize('المُحاسِبة  القانونيّة'));
        $this->assertSame('مدير فني', TextNormalizer::normalize('المدير الفني'));
        $this->assertSame('الوان', TextNormalizer::normalize('ألوان'));
        $this->assertSame('senior accountant 12', TextNormalizer::normalize('Senior  Accountant, ١٢'));
    }

    // ── Screens ──────────────────────────────────────────────────────

    public function test_super_admin_can_browse_the_backbone(): void
    {
        BackboneImporter::make()->run();
        $admin = User::factory()->superAdmin()->create();
        $analyst = EscoOccupation::where('code', '2411.1.1')->value('id');

        foreach (['enoc', 'isco', 'esco'] as $standard) {
            $this->actingAs($admin)->get("/admin/backbone?standard={$standard}")->assertOk();
            $this->actingAs($admin)->get("/admin/backbone?standard={$standard}&q=".urlencode('محاسبة'))->assertOk();
        }
        $this->actingAs($admin)->get('/admin/backbone/units/2411')->assertOk();
        $this->actingAs($admin)->get('/admin/backbone/units/3221')->assertOk();
        $this->actingAs($admin)->get("/admin/backbone/esco/{$analyst}")->assertOk();
        $this->actingAs($admin)->get('/admin/backbone/units/9999')->assertNotFound();
    }

    public function test_the_screen_explains_how_to_load_an_empty_backbone(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/backbone')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Backbone/Index')->where('loaded', false));
    }

    public function test_partner_staff_cannot_open_the_backbone_admin(): void
    {
        $user = User::factory()->companyAdmin()->create();

        $this->actingAs($user)->get('/admin/backbone')->assertForbidden();
    }

    public function test_the_standard_switch_is_saved_in_the_background(): void
    {
        $admin = User::factory()->superAdmin()->create(['occupation_standard' => 'enoc']);

        // The switch saves with a plain background request: it must answer
        // "done" (no page), so it cannot disturb the list reloading.
        $this->actingAs($admin)
            ->patchJson('/preferences/standard', ['standard' => 'esco'])
            ->assertNoContent();
        $this->assertSame('esco', $admin->fresh()->occupation_standard);

        $this->actingAs($admin)
            ->patchJson('/preferences/standard', ['standard' => 'nonsense'])
            ->assertUnprocessable();
        $this->assertSame('esco', $admin->fresh()->occupation_standard);
    }
}
