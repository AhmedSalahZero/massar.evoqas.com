<?php

namespace Tests\Feature;

use App\Models\Backbone\EscoOccupation;
use App\Models\Beneficiary;
use App\Models\Company;
use App\Models\CvDocument;
use App\Models\Opportunity;
use App\Models\OpportunityMatch;
use App\Models\User;
use App\Services\Backbone\BackboneImporter;
use App\Services\Backbone\MarketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WritesBackboneSamples;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — DashboardTest (Step 15 · the dashboards, as the agreed demo)
//  Location: tests/Feature/DashboardTest.php
//
//    • the five numbers at the top, the journey, "needs your attention"
//    • new people by month (by how they joined), placements by month
//    • gender, age, governorates, industry
//    • the top occupations with the real market wage and outlook, the
//      expected salary, the skills most lacking among Eligible people
//    • open jobs and trainings, follow-ups, team activity
//    • the date range; parts a person may not see are not sent
//    • PARTNER SEPARATION; the Super Admin: counts only, never the pool
// ══════════════════════════════════════════════════════════════════

class DashboardTest extends TestCase
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
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $when): void
    {
        Carbon::setTestNow($when);
    }

    private function person(User $w, array $o = []): Beneficiary
    {
        static $phone = 1003000000;
        $this->actingAs($w)->post('/app/beneficiaries', array_merge([
            'name_en' => 'Sara Mostafa', 'gender' => 'female', 'date_of_birth' => '1999-04-12', 'governorate' => 'cai',
            'phone' => '0'.(++$phone), 'education_level' => 'university', 'expected_salary' => '7000',
            'esco_occupation_id' => EscoOccupation::where('code', '2411.1')->value('id'), 'confirm_duplicate' => true,
        ], $o))->assertSessionHasNoErrors();
        Carbon::setTestNow(now()->addSeconds(10));

        return Beneficiary::withoutGlobalScopes()->where('company_id', $w->company_id)->orderByDesc('id')->first();
    }

    private function dash(User $u, string $range = 'm12', string $area = 'app'): array
    {
        $out = [];
        $this->actingAs($u)->get("/$area/dashboard?range=$range")->assertOk()->assertInertia(function (Assert $p) use (&$out) {
            $out = $p->toArray()['props'];
        });

        return $out;
    }

    /** A partner with a year of activity. */
    private function world(): array
    {
        $w = User::factory()->employee()->create(['name' => 'Karim Adel']);
        $this->at('2025-12-10 10:00:00');
        $old = $this->person($w, ['name_en' => 'Old One', 'gender' => 'male', 'military_status' => 'completed', 'date_of_birth' => '1980-01-01']);
        $this->at('2026-09-05 10:00:00');
        $sara = $this->person($w);
        $mona = $this->person($w, ['name_en' => 'Mona Adel', 'governorate' => 'giz', 'expected_salary' => '9000']);
        DB::table('beneficiaries')->where('id', $mona->id)->update(['source' => 'cv_upload']);
        CvDocument::withoutGlobalScopes()->forceCreate(['uuid' => (string) Str::uuid(), 'company_id' => $w->company_id, 'original_name' => 'a.pdf', 'extension' => 'pdf',
            'size' => 1, 'sha256' => str_repeat('a', 64), 'status' => 'added', 'beneficiary_id' => $mona->id]);
        CvDocument::withoutGlobalScopes()->forceCreate(['uuid' => (string) Str::uuid(), 'company_id' => $w->company_id, 'original_name' => 'b.pdf', 'extension' => 'pdf',
            'size' => 1, 'sha256' => str_repeat('b', 64), 'status' => 'review']);

        // A job with two seats; Sara and Old are eligible and referred; Old is hired.
        $this->actingAs($w)->post('/app/jobs', ['title' => 'Junior accountant', 'occupations' => ['esco:2411.1'], 'governorates' => ['cai', 'giz'], 'seats' => 1,
            'job_type' => 'full_time', 'deadline' => '2026-09-01', 'eligible_from' => 60, 'check_from' => 30,
            'rules' => [['type' => 'governorate', 'mode' => 'must', 'values' => ['cai']]]])->assertSessionHasNoErrors();
        $o = Opportunity::withoutGlobalScopes()->sole();
        foreach ([$old, $sara, $mona] as $b) {
            Carbon::setTestNow(now()->addSeconds(10));
            $this->actingAs($w)->post("/app/beneficiaries/{$b->number}/eligibility", ['opportunity_id' => $o->id]);
        }
        Carbon::setTestNow(now()->addSeconds(10));
        $this->actingAs($w)->post('/app/matches', ['opportunity_id' => $o->id, 'numbers' => [$old->number, $sara->number], 'on' => '2026-09-05']);
        $this->at('2026-09-16 10:00:00');
        $m = OpportunityMatch::withoutGlobalScopes()->where('beneficiary_id', $old->id)->sole();
        $this->actingAs($w)->post("/app/matches/{$m->id}/move", ['to' => 'done', 'on' => '2026-09-16']);
        $this->at('2026-09-27 10:00:00');

        return compact('w', 'old', 'sara', 'mona', 'o');
    }

    public function test_the_partner_dashboard_numbers(): void
    {
        ['w' => $w] = $this->world();
        $d = $this->dash($w)['dash'];

        // The five numbers.
        $k = $d['kpis'];
        $this->assertSame([3, 3, 1, 50, 1, 0, 1, 1, 0], [$k['people'], $k['people_new'], $k['cvs'], $k['cvs_auto'], $k['jobs'], $k['trainings'], $k['seats'], $k['hired'], $k['completed']]);
        $this->assertSame(33.3, $k['rate']);      // 1 placed of 3 assessed
        $this->assertSame(11, $k['days']);        // referred 5 Sept, hired 16 Sept
        $this->assertCount(12, $k['people_spark']);
        $this->assertSame(2, end($k['people_spark']));

        // The journey, and what needs attention.
        $this->assertSame(['registered' => 3, 'assessed' => 3, 'eligible' => 2, 'matched' => 2, 'placed' => 1], $d['funnel']);
        // Sara was referred on 5 September with no news since: a follow-up (more than 14 days).
        $this->assertSame(['follow' => 1, 'look' => 0, 'queue' => 1, 'deadline' => 1, 'full' => 1], $d['attention']);

        // By month: September has two new people (one by CV upload); one hire.
        $this->assertSame('2026-09', end($d['months']));
        $this->assertSame([1, 1], [end($d['months_new']['manual']), end($d['months_new']['cv_upload'])]);
        $this->assertSame(1, end($d['months_placed']['job']));

        // Your people.
        $this->assertSame(['female' => 2, 'male' => 1], $d['people']['gender']);
        $this->assertSame(2, $d['people']['age']['25_29']);
        $this->assertSame(1, $d['people']['age']['45p']);
        $this->assertSame(['cai', 2], [$d['people']['governorate'][0]['key'], $d['people']['governorate'][0]['n']]);

        // The market: accountants with the real Egypt figures.
        $row = $d['market']['rows'][0];
        $this->assertSame(['2411', 3, 2, 1, 6740, 'faster', 7000], [$row['code'], $row['people'], $row['eligible'], $row['placed'], $row['wage'], $row['trend'], $row['expected']]);

        // The tables.
        $this->assertSame(['Junior accountant', 3 - 1, 2, 1, 'full'], [$d['opportunities']['rows'][0]['title'], $d['opportunities']['rows'][0]['eligible'],
            $d['opportunities']['rows'][0]['referred'], $d['opportunities']['rows'][0]['taken'], $d['opportunities']['rows'][0]['state']]);
        $this->assertSame(['Karim Adel', 3, 3, 2, 1], [$d['team'][0]['name'], $d['team'][0]['registered'], $d['team'][0]['checked'], $d['team'][0]['referred'], $d['team'][0]['placed']]);
        $this->assertSame(1, $d['follow_up']['total']);
        $this->assertSame(['Sara Mostafa', 22], [$d['follow_up']['rows'][0]['person']['name'], $d['follow_up']['rows'][0]['days']]);

        // Before 14 days had passed, nothing needed a follow-up.
        $this->at('2026-09-15 10:00:00');
        $this->assertSame(0, $this->dash($w)['dash']['attention']['follow']);
    }

    public function test_the_date_range(): void
    {
        ['w' => $w] = $this->world();
        $m1 = $this->dash($w, 'm1')['dash'];
        $all = $this->dash($w, 'all')['dash'];
        $this->assertSame(2, $m1['kpis']['people_new']);           // registered in the last 30 days
        $this->assertSame(2, $m1['funnel']['registered']);
        $this->assertSame(3, $all['funnel']['registered']);
        $this->assertSame('2025-12', $all['months'][0]);             // from the first registration
        $this->assertSame(['2026-08', '2026-09'], $m1['months']);
        $this->assertSame('m12', $this->dash($w, 'nonsense')['dash']['range']);
    }

    public function test_the_skills_most_lacking_among_eligible_people(): void
    {
        $now = now();
        $s = DB::table('esco_skills')->insertGetId(['uri' => 's/tax', 'title_en' => 'calculate tax', 'title_ar' => 'حساب الضرائب', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('skill_labels')->insert(['skill_id' => $s, 'lang' => 'en', 'kind' => 'preferred', 'label' => 'calculate tax', 'normalized' => 'calculate tax']);
        DB::table('esco_occupation_skills')->insert(['esco_occupation_id' => EscoOccupation::where('code', '2411.1')->value('id'), 'skill_id' => $s, 'is_essential' => true]);
        $this->at('2026-09-27 10:00:00');
        ['w' => $w, 'o' => $o] = $this->world();
        $o->forceFill(['status' => 'open'])->save();

        $d = $this->dash($w)['dash'];
        $this->assertSame([['en' => 'calculate tax', 'ar' => 'حساب الضرائب', 'n' => 2]], $d['skills']['rows']);
    }

    public function test_parts_a_person_may_not_see_are_not_sent(): void
    {
        ['w' => $w] = $this->world();
        $limited = User::factory()->employee($w->company)->create(['permissions' => ['beneficiaries.view']]);
        $d = $this->dash($limited)['dash'];
        $this->assertNull($d['opportunities']);
        $this->assertNull($d['follow_up']);
        $this->assertNull($d['skills']);
        $this->assertSame(['follow' => null, 'look' => null, 'queue' => null, 'deadline' => null, 'full' => null], $d['attention']);
        $this->assertNotNull($d['funnel']);

        $none = User::factory()->employee($w->company)->create(['permissions' => []]);
        $d = $this->dash($none)['dash'];
        $this->assertNull($d['funnel']);
        $this->assertNull($d['people']);
        $this->assertNull($d['market']);
        $this->assertNull($d['team']);
    }

    public function test_another_partner_is_never_counted_and_the_super_admin_sees_counts_only(): void
    {
        ['w' => $w] = $this->world();
        $other = User::factory()->companyAdmin()->create();
        $this->at('2026-09-20 10:00:00');
        $this->person($other, ['name_en' => 'Theirs']);
        $this->at('2026-09-27 10:00:00');

        $d = $this->dash($other)['dash'];
        $this->assertSame([1, 0, 0], [$d['kpis']['people'], $d['kpis']['hired'], $d['opportunities']['total']]);
        $this->assertSame([], $d['team'][0]['checked'] ? ['x'] : []);
        $this->assertSame(3, $this->dash($w)['dash']['kpis']['people']);

        // The public pool's own workspace is not a partner.
        $pool = Company::query()->withoutGlobalScope('partners')->forceCreate(['name' => 'Pool', 'is_pool' => true, 'is_active' => true, 'seat_limit' => 0]);
        Beneficiary::withoutGlobalScopes()->forceCreate(['company_id' => $pool->id, 'number' => 1, 'name_en' => 'Seeker', 'gender' => 'female', 'governorate' => 'cai']);

        $admin = User::factory()->superAdmin()->create();
        $props = $this->dash($admin, 'm12', 'admin');
        $a = $props['dash'];
        $this->assertSame([2, 4, 1, 1], [$a['kpis']['partners'], $a['kpis']['people'], $a['kpis']['cvs'], $a['kpis']['hired']]);
        $this->assertCount(2, $a['partners']);
        $this->assertSame(3, collect($a['partners'])->firstWhere('id', $w->company_id)['people']);
        $this->assertSame(4, array_sum($a['growth']));
        // Counts only: no names of people anywhere in what the page receives.
        $json = json_encode($props);
        foreach (['Sara Mostafa', 'Mona Adel', 'Old One', 'Theirs', 'Seeker'] as $name) {
            $this->assertStringNotContainsString($name, $json);
        }
        $this->assertArrayHasKey('eligibility', $props['stats']);
    }
}
