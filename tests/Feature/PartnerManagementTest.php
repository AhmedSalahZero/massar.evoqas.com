<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — PartnerManagementTest
//  Location: tests/Feature/PartnerManagementTest.php
//
//  The Super Admin's partner organisation screens (Scope v2 §4).
//  In the code a partner organisation is called a "Company".
//    • creating a partner also creates its first Company Admin
//    • partner staff cannot open the /admin area
//    • seats cannot be set below the accounts that already exist
//    • suspending a partner signs its staff out on their next click
// ══════════════════════════════════════════════════════════════════

class PartnerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_creates_a_partner_with_its_first_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post('/admin/companies', [
            'name'                        => 'Al-Amal Foundation',
            'type'                        => 'ngo',
            'seat_limit'                  => 5,
            'admin_name'                  => 'Rana Hassan',
            'admin_email'                 => 'rana@alamal.org',
            'admin_password'              => 'Strong#Pass1',
            'admin_password_confirmation' => 'Strong#Pass1',
            'admin_language'              => 'ar',
        ])->assertSessionHasNoErrors();

        $company = Company::where('name', 'Al-Amal Foundation')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'email'      => 'rana@alamal.org',
            'company_id' => $company->id,
            'role'       => 'company_admin',
        ]);
    }

    public function test_partner_staff_cannot_open_the_admin_area(): void
    {
        $user = User::factory()->companyAdmin()->create();

        $this->actingAs($user)->get('/admin/companies')->assertForbidden();
    }

    public function test_seats_cannot_go_below_existing_accounts(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::factory()->create(['seat_limit' => 5]);
        User::factory()->count(3)->employee($company)->create();

        $this->actingAs($admin)->patch("/admin/companies/{$company->id}", [
            'name'       => $company->name,
            'type'       => $company->type,
            'seat_limit' => 2,
        ])->assertSessionHasErrors('seat_limit');

        $this->assertSame(5, $company->fresh()->seat_limit);
    }

    public function test_suspending_a_partner_signs_its_staff_out(): void
    {
        $user = User::factory()->employee()->create();
        $user->company->update(['is_active' => false]);

        $this->actingAs($user)->get('/app/dashboard')->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
