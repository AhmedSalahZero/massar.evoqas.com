<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — TeamTest
//  Location: tests/Feature/TeamTest.php
//
//  The partner's Team page (Scope v2 §5 "Seat-Limited User Creation")
//  and workspace isolation (Scope v2 §4):
//    • a Company Admin can add staff while seats are free
//    • nobody can be added once every seat is used
//    • employees cannot manage the team
//    • you cannot deactivate yourself
//    • one partner can never change another partner's people
// ══════════════════════════════════════════════════════════════════

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private function newMember(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'Ahmed Hassan',
            'email'                 => 'ahmed@alamal.org',
            'role'                  => 'employee',
            'password'              => 'Strong#Pass1',
            'password_confirmation' => 'Strong#Pass1',
        ], $overrides);
    }

    public function test_company_admin_adds_a_staff_member(): void
    {
        $admin = User::factory()->companyAdmin()->create();

        $this->actingAs($admin)->post('/app/team', $this->newMember())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'ahmed@alamal.org', 'company_id' => $admin->company_id]);
    }

    public function test_no_one_can_be_added_when_all_seats_are_used(): void
    {
        $company = Company::factory()->create(['seat_limit' => 1]);
        $admin = User::factory()->companyAdmin($company)->create();

        $this->actingAs($admin)->post('/app/team', $this->newMember())->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'ahmed@alamal.org']);
    }

    public function test_employees_cannot_manage_the_team(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)->get('/app/team')->assertForbidden();
    }

    public function test_you_cannot_deactivate_yourself(): void
    {
        $admin = User::factory()->companyAdmin()->create();

        $this->actingAs($admin)->patch("/app/team/{$admin->id}/toggle-active")->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_one_partner_cannot_touch_another_partners_people(): void
    {
        $admin = User::factory()->companyAdmin()->create();
        $stranger = User::factory()->employee()->create(); // a different company

        $this->actingAs($admin)->patch("/app/team/{$stranger->id}/toggle-active")->assertNotFound();

        $this->assertTrue($stranger->fresh()->is_active);
    }
}
