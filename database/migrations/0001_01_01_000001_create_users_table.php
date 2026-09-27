<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Users, Password Resets, Sessions
//  Location: database/migrations/0001_01_01_000001_create_users_table.php
//
//  Roles (App\Enums\UserRole — validated in PHP, stored as a plain
//  string so a new role never needs an ALTER TABLE):
//    super_admin   → Massar platform team. company_id is NULL; sits
//                    above the tenant boundary.
//    company_admin → a partner's administrator. Manages the team.
//    employee      → a partner's staff (case worker, officer …).
//                    Their free-text job title is in `job_title`.
//
//  Permissions (Scope v2 §5 — "permission backbone now, detailed
//  matrix at the end"):
//    permissions → JSON list of permission keys granted to this user.
//                  NULL means "use the role's defaults" from
//                  config/permissions.php, which is the state every
//                  user is in until the Permission Builder is built.
//
//  Preferences:
//    language            → en | ar   (drives dir="rtl" as well)
//    theme               → dark | light
//    occupation_standard → enoc | isco | esco — the standard switch
//                          in the top bar, remembered per user.
//
//  Login tracking (kept in sync by UserLoginFrequencyService):
//    login_count, last_login_at, last_activity_at
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // ── Tenant ─────────────────────────────────────────
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // ── Identity ───────────────────────────────────────
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('phone', 30)->nullable();

            // ── Role & access ──────────────────────────────────
            $table->string('role', 30)->default('employee');
            $table->string('job_title', 100)->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);

            // ── Preferences ────────────────────────────────────
            $table->string('language', 5)->default('en');
            $table->string('theme', 10)->default('dark');
            $table->string('occupation_standard', 10)->default('enoc');

            // ── Login tracking ─────────────────────────────────
            $table->unsignedInteger('login_count')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'role']);
        });

        // companies.created_by → users (the two tables reference each other)
        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // ── Password Reset ─────────────────────────────────────
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ── Sessions ───────────────────────────────────────────
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
