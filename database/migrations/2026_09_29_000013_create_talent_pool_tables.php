<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Public Self-Registration + Public Talent Pool (Step 10)
//  Location: database/migrations/2026_09_29_000013_create_talent_pool_tables.php
//  Scope v2 §3 Module B (Public Talent Pool), §4, §7 Personal Data Protection
//
//  companies.is_pool       ONE hidden workspace, "the Talent Pool", holds
//                          the profiles job seekers make themselves on the
//                          public site. It is not a partner: it never shows
//                          in the Super Admin's lists or counts, nobody signs
//                          in to it, and it has no subscription. Keeping the
//                          profiles there means they are read, checked,
//                          stored (CVs encrypted) and searched by exactly the
//                          same code as the partners' own profiles.
//
//  job_seekers             the job seekers' own sign-in (email + password),
//                          completely separate from staff accounts (users).
//                          One account per email and per mobile.
//                            beneficiary_id  their profile in the pool workspace
//                            visible         shown to partners in the Talent Pool
//                                            (their consent; they can stop it)
//                            consented_at    when they agreed (privacy notice)
//                            notice_period   how soon they can start a new job
//                            code_*          the 6-digit email code (hashed)
//
//  job_seeker_password_resets   "Forgot your password?" links for job
//                          seekers (a separate table from the staff one, so
//                          the same email can be both, safely).
//
//  pool_additions          which partner added which job seeker, when and
//                          who. The partner gets its OWN copy of the profile
//                          (beneficiary_id, in the partner's workspace) and
//                          of the CV. The job seeker sees the list in "My
//                          profile"; they are not notified.
//
//  pool_access_logs        every time partner staff open a pool profile,
//                          download its CV or add it (Scope v2 §7).
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_pool')->default(false)->after('is_active');
            $table->index('is_pool');
        });

        // The one Talent Pool workspace (see above). Made here, once, so two
        // registrations at the same moment can never make two of them.
        DB::table('companies')->insert([
            'name' => 'Massar Talent Pool', 'name_ar' => 'مجمع مواهب مسار', 'type' => 'other',
            'seat_limit' => 0, 'subscription_ends_at' => null, 'is_active' => true, 'is_pool' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('job_seekers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                       // used in addresses; ids are never shown
            $table->string('email', 150)->unique();               // lower case — the sign-in
            $table->string('phone', 20)->nullable()->unique();    // 01XXXXXXXXX
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('language', 2)->default('ar');
            $table->foreignId('beneficiary_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('visible')->default(true);
            $table->timestamp('consented_at')->nullable();
            $table->string('notice_period', 20)->nullable();
            $table->string('code_hash')->nullable();
            $table->timestamp('code_expires_at')->nullable();
            $table->unsignedTinyInteger('code_attempts')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['visible', 'email_verified_at']);
        });

        Schema::create('job_seeker_password_resets', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('pool_additions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_seeker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('added_by_name', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['job_seeker_id', 'company_id']);
            $table->index(['company_id', 'id']);
        });

        Schema::create('pool_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_seeker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 100)->nullable();
            $table->string('action', 10);                         // view | cv | add
            $table->timestamp('created_at')->useCurrent();

            $table->index(['job_seeker_id', 'id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pool_access_logs');
        Schema::dropIfExists('pool_additions');
        Schema::dropIfExists('job_seeker_password_resets');
        Schema::dropIfExists('job_seekers');
        DB::table('companies')->where('is_pool', true)->delete();
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['is_pool']);
            $table->dropColumn('is_pool');
        });
    }
};
