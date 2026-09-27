<?php

namespace Tests\Feature;

use App\Models\Backbone\EscoOccupation;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — OccupationsTest
//  Location: tests/Feature/OccupationsTest.php
//
//  The partner Occupations page (Step 3), on the same sample files as
//  BackboneTest and LabourMarketTest:
//    • partner staff search and browse in ENOC, ISCO-08 and ESCO and
//      open occupations and ESCO jobs, with the market panel
//    • figures flagged at import (hours 408, a sector share of 976%)
//      and the six suspect regions NEVER reach a partner — while the
//      Super Admin still sees them, with the flags
//    • the page needs the occupations.view permission
//    • before any data is loaded the page says so plainly
// ══════════════════════════════════════════════════════════════════

class OccupationsTest extends TestCase
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

    private function loadEverything(): void
    {
        BackboneImporter::make()->run();
        MarketImporter::make()->run();
    }

    public function test_partner_staff_search_and_browse_in_all_three_standards(): void
    {
        $this->loadEverything();
        $staff = User::factory()->employee()->create();
        $analyst = EscoOccupation::where('code', '2411.1.1')->value('id');

        foreach (['enoc', 'isco', 'esco'] as $standard) {
            $this->actingAs($staff)->get("/app/occupations?standard={$standard}")->assertOk()
                ->assertInertia(fn ($page) => $page->component('App/Occupations/Index')->where('standard', $standard)->where('loaded', true));
        }

        $this->actingAs($staff)->get('/app/occupations?standard=enoc&q='.urlencode('محاسبة'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('browse.enoc.data.0.code', '2411'));
        $this->actingAs($staff)->get('/app/occupations?standard=esco&q=accountant')->assertOk()
            ->assertInertia(fn ($page) => $page->where('browse.esco.data.0.code', '2411.1'));
        $this->actingAs($staff)->get('/app/occupations?standard=isco')->assertOk()
            ->assertInertia(fn ($page) => $page->has('browse.tree'));

        $this->actingAs($staff)->get('/app/occupations/units/2411')->assertOk()
            ->assertInertia(fn ($page) => $page->component('App/Occupations/Unit')
                ->where('enoc.code', '2411')
                ->where('market.profile.wage_avg', 6740)
                ->has('esco', 2));
        $this->actingAs($staff)->get('/app/occupations/units/0110')->assertOk()
            ->assertInertia(fn ($page) => $page->where('enoc', null));
        $this->actingAs($staff)->get("/app/occupations/esco/{$analyst}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('App/Occupations/Esco')
                ->where('unit.code', '2411')
                ->where('market.profile.workers', 569316));
        $this->actingAs($staff)->get('/app/occupations/units/9999')->assertNotFound();
    }

    public function test_partners_never_receive_flagged_figures_but_the_super_admin_does(): void
    {
        $this->loadEverything();
        $staff = User::factory()->companyAdmin()->create();

        // 3221 is published with 408 hours a week and a sector share of 976%.
        $this->actingAs($staff)->get('/app/occupations/units/3221')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('market.profile.weekly_hours', null)
                ->where('market.profile.sectors', null)
                ->where('market.profile.workers', 1000)
                ->missing('market.profile.flags')
                ->where('market.profile.hidden', ['weekly_hours', 'sectors', 'regions_outside_cairo'])
                ->has('market.profile.regions', 1)
                ->where('market.profile.regions.cairo', 'below'));

        // A clean occupation loses only the six suspect regions.
        $this->actingAs($staff)->get('/app/occupations/units/2411')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('market.profile.hidden', ['regions_outside_cairo'])
                ->where('market.profile.weekly_hours', 46)
                ->where('market.profile.sectors.finance', 9.9));

        // The Super Admin still sees exactly what was published, with the flags.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get('/admin/backbone/units/3221')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('market.profile.weekly_hours', 408)
                ->where('market.profile.flags', ['hours', 'sectors'])
                ->has('market.profile.regions', 7)
                ->missing('market.profile.hidden'));
    }

    public function test_the_occupations_page_needs_its_permission(): void
    {
        $this->loadEverything();
        $without = User::factory()->employee()->create(['permissions' => ['beneficiaries.view']]);

        $this->actingAs($without)->get('/app/occupations')->assertForbidden();
        $this->actingAs($without)->get('/app/occupations/units/2411')->assertForbidden();
    }

    public function test_the_page_says_plainly_when_nothing_is_loaded_yet(): void
    {
        $staff = User::factory()->employee()->create();

        $this->actingAs($staff)->get('/app/occupations')->assertOk()
            ->assertInertia(fn ($page) => $page->component('App/Occupations/Index')->where('loaded', false)->where('browse', null));
    }
}
