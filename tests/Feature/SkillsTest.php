<?php

namespace Tests\Feature;

use App\Models\Backbone\SkillImport;
use App\Models\User;
use App\Services\Backbone\SkillImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — Step 5 tests: ESCO skills
//  Location: tests/Feature/SkillsTest.php
//
//  Uses a tiny set of ESCO-shaped files (tests/Fixtures/esco_skills)
//  so the tests run in seconds: 3 skills (one listed twice, like the
//  real file), 4 groups in two pillars (one knowledge group with no
//  Arabic name, like the real file), 2 ESCO jobs, and one skill linked
//  to itself (also like the real file).
// ══════════════════════════════════════════════════════════════════

class SkillsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['backbone.path' => base_path('tests/Fixtures/esco_skills')]);
        $this->withoutVite();
    }

    /** Two ESCO jobs in unit group 2411, as backbone:import would leave them. */
    private function occupations(): array
    {
        $now = now();
        DB::table('backbone_imports')->insert(['status' => 'completed', 'started_at' => $now, 'finished_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $unit = DB::table('isco_groups')->insertGetId(['code' => '2411', 'level' => 4, 'major_code' => '2', 'title_en' => 'Accountants', 'title_ar' => 'المحاسبون', 'created_at' => $now, 'updated_at' => $now]);
        $row = fn ($uri, $code, $title) => ['uri' => 'http://data.europa.eu/esco/occupation/'.$uri, 'code' => $code, 'sort_key' => $code, 'isco_group_id' => $unit,
            'isco_code' => '2411', 'title_en' => $title, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];

        return [
            'auditor' => DB::table('esco_occupations')->insertGetId($row('auditor', '2411.1', 'financial auditor')),
            'clerk'   => DB::table('esco_occupations')->insertGetId($row('clerk', '2411.2', 'tax clerk')),
        ];
    }

    public function test_import_loads_skills_groups_and_links(): void
    {
        $jobs = $this->occupations();
        $import = SkillImporter::make()->run();

        $this->assertSame(SkillImport::COMPLETED, $import->status);
        $c = $import->counts;
        $this->assertSame(3, $c['skills']);
        $this->assertSame(1, $c['skills_duplicate_rows_skipped']);
        $this->assertSame(4, $c['groups']);
        $this->assertSame(1, $c['groups_without_arabic']);
        $this->assertSame(4, $c['occupation_links']);
        $this->assertSame(3, $c['occupation_links_essential']);
        $this->assertSame(1, $c['related_links']);
        $this->assertSame(1, $c['related_self_links_skipped']);

        // The newest of the two duplicate rows is kept.
        $tax = DB::table('esco_skills')->where('title_en', 'calculate tax')->first();
        $this->assertSame('Calculate the taxes owed.', $tax->description_en);
        $this->assertSame('حساب الضرائب', $tax->title_ar);
        $this->assertSame('skill', $tax->type);

        // Groups get their pillar and level.
        $k04 = DB::table('esco_skill_groups')->where('code', '04')->first();
        $this->assertSame('K', $k04->pillar);
        $this->assertSame(1, (int) $k04->level);

        $this->assertTrue((bool) DB::table('esco_occupation_skills')->where('esco_occupation_id', $jobs['auditor'])->where('skill_id', $tax->id)->value('is_essential'));
        $this->assertSame(2, DB::table('esco_skill_parents')->where('skill_id', DB::table('esco_skills')->where('title_en', 'work in teams')->value('id'))->count());
    }

    public function test_running_again_keeps_ids_and_skips_unchanged_files(): void
    {
        $this->occupations();
        SkillImporter::make()->run();
        $ids = DB::table('esco_skills')->orderBy('uri')->pluck('id', 'uri')->all();

        $this->assertSame(SkillImport::UNCHANGED, SkillImporter::make()->run()->status);

        SkillImporter::make()->run(force: true);
        $this->assertSame($ids, DB::table('esco_skills')->orderBy('uri')->pluck('id', 'uri')->all());
        $this->assertSame(4, DB::table('esco_occupation_skills')->count());
    }

    public function test_import_stops_and_changes_nothing_when_an_occupation_is_unknown(): void
    {
        $this->occupations();
        SkillImporter::make()->run();
        DB::table('esco_occupations')->where('code', '2411.2')->delete();   // cascades its links

        try {
            SkillImporter::make()->run(force: true);
            $this->fail('The import should have stopped.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('different ESCO edition', $e->getMessage());
        }

        $this->assertSame(SkillImport::FAILED, SkillImport::query()->latest('id')->value('status'));
        $this->assertSame(3, DB::table('esco_skills')->count());
        $this->assertSame(2, DB::table('esco_occupation_skills')->count());   // the auditor's links, untouched
    }

    public function test_import_needs_the_occupation_backbone_first(): void
    {
        $this->expectExceptionMessage('backbone:import');
        SkillImporter::make()->run();
    }

    public function test_super_admin_sees_skills_and_can_search_them(): void
    {
        $jobs = $this->occupations();
        SkillImporter::make()->run();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/backbone?skill_q=الضرائب')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Backbone/Index')
                ->where('skills.counts.skills', 3)
                ->where('skillSearch.results.0.title_en', 'calculate tax'));

        // Arabic without "ال" and with a different hamza form finds the same skill.
        $this->actingAs($admin)->get('/admin/backbone?skill_q=محاسبه')
            ->assertInertia(fn (Assert $page) => $page->where('skillSearch.results.0.title_en', 'accounting'));

        $this->actingAs($admin)->get("/admin/backbone/esco/{$jobs['auditor']}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Backbone/Esco')
                ->has('skills.essential', 1)
                ->has('skills.optional', 1));

        $tax = DB::table('esco_skills')->where('title_en', 'calculate tax')->value('id');
        $this->actingAs($admin)->get("/admin/backbone/skills/{$tax}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Backbone/Skill')
                ->where('occupations.essential.total', 2)
                ->has('needs', 1)
                ->has('narrower', 1)
                ->where('paths.0.0.code', 'S'));
    }

    public function test_partners_see_skills_on_occupation_pages(): void
    {
        $this->occupations();
        SkillImporter::make()->run();
        $partner = User::factory()->companyAdmin()->create();

        $this->actingAs($partner)->get('/app/occupations/units/2411')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('App/Occupations/Unit')
                ->where('skills.jobs', 2)
                ->where('skills.skills.0.title_en', 'calculate tax')
                ->where('skills.skills.0.jobs', 2));

        $skill = DB::table('esco_skills')->where('title_en', 'accounting')->value('id');
        $this->actingAs($partner)->get("/app/occupations/skills/{$skill}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('App/Occupations/Skill')->where('skill.title_ar', 'المحاسبة'));

        DB::table('esco_skills')->where('id', $skill)->update(['is_active' => false]);
        $this->actingAs($partner)->get("/app/occupations/skills/{$skill}")->assertNotFound();
    }

    public function test_pages_still_work_before_the_skills_are_imported(): void
    {
        $jobs = $this->occupations();
        $partner = User::factory()->companyAdmin()->create();

        $this->actingAs($partner)->get("/app/occupations/esco/{$jobs['clerk']}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('skills', null));
    }
}
