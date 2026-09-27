<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — SignInTest
//  Location: tests/Feature/SignInTest.php
//
//  Who can sign in, and where they land:
//    • the sign-in screen opens
//    • Super Admin → /admin, partner staff → /app
//    • a wrong password is refused
//    • a first-time (unverified) user is sent to the email-code step
//    • a suspended partner or an ended subscription blocks sign-in
//    • signing out ends the session and opens the sign-in screen
// ══════════════════════════════════════════════════════════════════

class SignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_screen_opens(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_super_admin_lands_on_the_platform_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_partner_staff_land_on_the_workspace_dashboard(): void
    {
        $user = User::factory()->companyAdmin()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('app.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_refused(): void
    {
        $user = User::factory()->employee()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'Wrong#Pass1'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unverified_user_is_asked_for_the_email_code(): void
    {
        config(['auth_verification.enabled' => true]);

        $user = User::factory()->employee()->unverified()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('needs_verification');

        $this->assertGuest();
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_suspended_partner_cannot_sign_in(): void
    {
        $user = User::factory()->employee(Company::factory()->suspended()->create())->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_ended_subscription_cannot_sign_in(): void
    {
        $user = User::factory()->employee(Company::factory()->lapsed()->create())->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_sign_out_ends_the_session(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_sign_out_from_inside_the_app_reloads_the_sign_in_screen(): void
    {
        $user = User::factory()->companyAdmin()->create();

        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/logout')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('login'));

        $this->assertGuest();
    }
}
