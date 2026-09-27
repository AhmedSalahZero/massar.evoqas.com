<?php

namespace Tests\Feature;

use App\Models\Backbone\EnocMarketProfile;
use App\Models\Backbone\LabourMarketEdition;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — LabourMarketTest
//  Location: tests/Feature/LabourMarketTest.php
//
//  Egypt labour market editions (Step 2), on sample files laid out
//  exactly like the Egypt Occupational Outlook:
//    • figures land on the right ENOC occupation, categories become
//      keys, "." and blanks become "no data" (never 0)
//    • impossible figures are kept as published and flagged
//    • the same file is never loaded twice; a newer file becomes a
//      new edition that waits for review, with a comparison
//    • `market:use` and the "Use this edition" button switch editions;
//      the button records who did it, refuses a failed edition, and
//      is for the Super Admin only
//    • unknown wording or a changed layout stops the import cleanly
//    • the admin pages carry the figures of the current edition
// ══════════════════════════════════════════════════════════════════

class LabourMarketTest extends TestCase
{
    use RefreshDatabase;
    use WritesBackboneSamples;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSamples();
        BackboneImporter::make()->run();
    }

    protected function tearDown(): void
    {
        $this->tearDownSamples();
        parent::tearDown();
    }

    private function profile(string $code, ?int $editionId = null): EnocMarketProfile
    {
        return EnocMarketProfile::query()
            ->whereHas('enoc', fn ($q) => $q->where('code', $code))
            ->where('edition_id', $editionId ?? LabourMarketEdition::current()->id)
            ->firstOrFail();
    }

    public function test_figures_are_loaded_onto_the_right_occupation(): void
    {
        ['edition' => $edition] = MarketImporter::make()->run();

        $this->assertTrue($edition->is_current, 'the first edition is used automatically');
        $this->assertSame(2, $edition->counts['occupations']);
        $this->assertSame(1, $edition->counts['wages_gender']);

        $p = $this->profile('2411');
        $this->assertSame(569316, $p->workers);
        $this->assertSame('faster', $p->workers_trend, '"إلى" and "إلي" both recognised');
        $this->assertSame(6740, $p->wage_avg);
        $this->assertSame(14860, $p->wage_public);
        $this->assertSame('higher', $p->education);
        $this->assertSame('add_10k_50k', $p->outlook_jobs);
        $this->assertSame('much_above', $p->regions['cairo']);
        $this->assertSame(77, $p->skill_groups['computer']);
        $this->assertSame(['فهم نص مقروء', 'التفكير النقدي'], $p->skills, 'double spaces tidied');
        $this->assertSame([], $p->flags ?? []);
    }

    public function test_missing_figures_are_no_data_and_impossible_ones_are_flagged(): void
    {
        MarketImporter::make()->run();
        $p = $this->profile('3221');

        $this->assertNull($p->wage_female, '"." means no data');
        $this->assertSame(408, $p->weekly_hours, 'kept exactly as published (above 255, which MySQL used to refuse)');
        $this->assertContains('hours', $p->flags);
        $this->assertNull($p->education);
        $this->assertSame(976.0, (float) $p->sectors['transport'], 'kept exactly as published');
        $this->assertContains('sectors', $p->flags);

        $quality = LabourMarketEdition::current()->quality;
        $this->assertTrue($quality['regions_outside_cairo_identical']);
        $this->assertSame(['3221'], $quality['flagged']['sectors']);
        $this->assertSame(['3221'], $quality['flagged']['hours']);
        $this->assertSame([], $quality['wage_copies_disagree']);
    }

    public function test_a_newer_file_becomes_a_new_edition_that_waits_for_review(): void
    {
        ['edition' => $first] = MarketImporter::make()->run();
        $this->assertTrue(MarketImporter::make()->run()['unchanged'], 'the same file is not loaded twice');

        $newer = self::ACCOUNTANT_FIGURES;
        $newer['wage_avg'] = 12000;
        $newer['check.wage_avg'] = '12000';
        $this->writeOutlook([$this->outlookRow('2411', 'محاسبون', 'وصف', $newer)]);

        ['edition' => $second] = MarketImporter::make()->run();

        $this->assertFalse($second->is_current, 'a later edition is not shown until chosen');
        $this->assertSame($first->id, LabourMarketEdition::current()->id);
        $this->assertSame(6740, $this->profile('2411')->wage_avg, 'screens still show the old edition');
        $this->assertSame(['3221'], $second->comparison['dropped']);
        $this->assertSame([['code' => '2411', 'field' => 'wage_avg', 'from' => 6740, 'to' => 12000]], $second->comparison['big_changes']);

        $this->artisan('market:use', ['edition' => $second->id])->assertSuccessful();
        $this->assertSame($second->id, LabourMarketEdition::current()->id);
        $this->assertSame(12000, $this->profile('2411')->wage_avg);
        $this->assertSame(1, LabourMarketEdition::where('is_current', true)->count());
        $this->assertSame(6740, $this->profile('2411', $first->id)->wage_avg, 'the old edition is kept');
    }

    public function test_unknown_wording_stops_the_import_and_changes_nothing(): void
    {
        $figures = self::ACCOUNTANT_FIGURES;
        $figures['education'] = 'دكتوراه';
        $this->writeOutlook([$this->outlookRow('2411', 'محاسبون', 'وصف', $figures)]);

        try {
            MarketImporter::make()->run();
            $this->fail('The import should have stopped.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('دكتوراه', $e->getMessage());
        }

        $this->assertSame(0, EnocMarketProfile::count());
        $this->assertSame(LabourMarketEdition::FAILED, LabourMarketEdition::latest('id')->value('status'));
        $this->assertNull(LabourMarketEdition::current());
    }

    public function test_a_changed_layout_is_refused_with_a_clear_message(): void
    {
        $this->writeOutlook([$this->outlookRow('2411', 'محاسبون', 'وصف', self::ACCOUNTANT_FIGURES)], [31 => 'عمود آخر']);

        $this->artisan('market:import')
            ->expectsOutputToContain('Nothing was changed')
            ->expectsOutputToContain('column 32')
            ->assertFailed();
    }

    public function test_the_market_needs_the_backbone_first(): void
    {
        \Illuminate\Support\Facades\DB::table('occupation_labels')->delete();
        \Illuminate\Support\Facades\DB::table('esco_occupations')->delete();
        \Illuminate\Support\Facades\DB::table('enoc_occupations')->delete();

        $this->expectExceptionMessage('backbone:import');
        MarketImporter::make()->run();
    }

    public function test_admin_pages_show_the_current_editions_figures(): void
    {
        MarketImporter::make()->run();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/backbone/units/2411')->assertOk()
            ->assertInertia(fn ($page) => $page->where('market.profile.wage_avg', 6740)
                ->where('market.regions_suspect', true)
                ->has('market.edition.reference_period'));

        $analyst = \App\Models\Backbone\EscoOccupation::where('code', '2411.1.1')->value('id');
        $this->actingAs($admin)->get("/admin/backbone/esco/{$analyst}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('market.profile.workers', 569316));

        $this->actingAs($admin)->get('/admin/backbone')->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.market.current.counts.occupations', 2)
                ->where('summary.market.current.quality.flagged.sectors.0.code', '3221'));
    }
    public function test_the_use_this_edition_button_switches_and_records_who(): void
    {
        ['edition' => $first] = MarketImporter::make()->run();
        $newer = self::ACCOUNTANT_FIGURES;
        $newer['wage_avg'] = 12000;
        $newer['check.wage_avg'] = '12000';
        $this->writeOutlook([$this->outlookRow('2411', 'محاسبون', 'وصف', $newer)]);
        ['edition' => $second] = MarketImporter::make()->run();
        $admin = User::factory()->superAdmin()->create(['name' => 'Rana']);

        // The waiting edition is listed with what to review first.
        $this->actingAs($admin)->get('/admin/backbone')->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.market.editions.0.id', $second->id)
                ->where('summary.market.editions.0.is_current', false)
                ->where('summary.market.editions.1.flagged.sectors', 1));

        $this->actingAs($admin)->from('/admin/backbone')
            ->patch("/admin/backbone/editions/{$second->id}/use")
            ->assertRedirect('/admin/backbone')->assertSessionHas('success');

        $second->refresh();
        $this->assertTrue($second->is_current);
        $this->assertSame($admin->id, $second->made_current_by);
        $this->assertNotNull($second->made_current_at);
        $this->assertSame(1, LabourMarketEdition::where('is_current', true)->count());
        $this->assertSame(12000, $this->profile('2411')->wage_avg);

        $this->actingAs($admin)->get('/admin/backbone')
            ->assertInertia(fn ($page) => $page->where('summary.market.editions.0.made_current_by', 'Rana'));

        // Switching back works the same way.
        $this->actingAs($admin)->patch("/admin/backbone/editions/{$first->id}/use")->assertSessionHas('success');
        $this->assertSame($first->id, LabourMarketEdition::current()->id);
    }

    public function test_a_failed_edition_cannot_be_used_and_partners_cannot_switch(): void
    {
        ['edition' => $good] = MarketImporter::make()->run();
        $failed = LabourMarketEdition::create(['name' => 'Broken', 'file_name' => 'x.xlsx', 'sha256' => str_repeat('0', 64),
            'status' => LabourMarketEdition::FAILED, 'message' => 'Out of range value']);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch("/admin/backbone/editions/{$failed->id}/use")->assertSessionHas('error');
        $this->assertSame($good->id, LabourMarketEdition::current()->id, 'nothing changed');

        $this->actingAs(User::factory()->companyAdmin()->create())
            ->patch("/admin/backbone/editions/{$good->id}/use")->assertForbidden();
        $this->assertNull($good->refresh()->made_current_by);
    }
}
