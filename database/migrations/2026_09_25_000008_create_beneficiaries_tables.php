<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Beneficiaries (Scope v2 §3 Module B · Registration & Profiles)
//  Location: database/migrations/2026_09_25_000008_create_beneficiaries_tables.php
//
//  beneficiaries          one structured record per person a partner
//                         works with. ALWAYS belongs to one partner
//                         (company_id) and is never visible to another
//                         (Scope v2 §4 "Fully Isolated Partner
//                         Workspaces"). The consent-based Public
//                         Talent Pool is a separate, later feature.
//  beneficiary_changes    the trail of who registered or changed a
//                         profile, when, and what changed (Scope v2 §7
//                         Personal Data Protection). Written by
//                         App\Services\Beneficiaries\BeneficiaryRecorder
//                         inside the same transaction as the change.
//
//  OCCUPATION (links to the shared backbone, see the backbone migration)
//    isco_group_id       the 4-digit unit group (= ENOC occupation),
//                        always set when an occupation is chosen
//    esco_occupation_id  the detailed ESCO job — the most detailed
//                        level. NULL only for the 10 unit groups that
//                        ESCO does not detail ("group level only").
//    isco_code           copy of the 4-digit code, so filtering by any
//                        level (major '2', sub-major '24' …) is one
//                        indexed prefix match
//    occupation_method   how it was chosen: 'manual' today; the CV
//                        reading engine will add 'cv_exact', 'rule' …
//
//  NUMBERING
//    number is the partner's own running number (1, 2, 3 …), shown to
//    staff and used in the address of the profile page. Database ids
//    are never shown, so one partner cannot guess how many
//    beneficiaries Massar holds in total.
//
//  DELETING A PARTNER deletes its beneficiaries and their history
//  (cascade) — "the partner and all its data" as the Super Admin's
//  delete dialog says. Deleting a staff account keeps the records;
//  the history keeps the person's name as it was.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');

            // ── Personal details ────────────────────────────────────
            $table->string('name_ar', 150)->nullable();
            $table->string('name_en', 150)->nullable();
            $table->string('gender', 10);                          // male | female
            $table->date('date_of_birth')->nullable();
            $table->string('military_status', 20)->nullable();     // men only, see config/beneficiaries.php
            $table->string('governorate', 5);                      // 'cai', 'giz' … (config/beneficiaries.php)
            $table->string('city', 100)->nullable();

            // ── Contact ─────────────────────────────────────────────
            $table->string('phone', 20)->nullable();               // Egyptian mobile, stored as 01XXXXXXXXX
            $table->string('email', 150)->nullable();              // lower case

            // ── Education, work, skills, languages ──────────────────
            $table->string('education_level', 30)->nullable();
            $table->json('education')->nullable();                 // [{qualification, field, institution, year}]
            $table->json('work_history')->nullable();              // [{title, employer, from: 'YYYY-MM', to: 'YYYY-MM'|null, current}]
            $table->unsignedSmallInteger('experience_months')->default(0);  // from work_history, overlaps counted once
            $table->json('skills')->nullable();                    // ['Excel', 'SAP', …]
            $table->json('languages')->nullable();                 // [{code, level}]

            // ── Occupation (shared backbone) ────────────────────────
            $table->foreignId('isco_group_id')->nullable()->constrained('isco_groups');
            $table->foreignId('esco_occupation_id')->nullable()->constrained('esco_occupations');
            $table->string('isco_code', 4)->nullable();
            $table->string('occupation_method', 20)->nullable();
            $table->foreignId('occupation_set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occupation_set_at')->nullable();

            // ── Preferences ─────────────────────────────────────────
            $table->unsignedInteger('expected_salary')->nullable(); // EGP per month
            $table->string('job_type', 20)->nullable();

            // ── Search & audit ──────────────────────────────────────
            $table->text('search_text')->nullable();               // normalised names, mobile, email (TextNormalizer)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Every list query starts with company_id (the partition key in
            // Scope v2 §7), so every index does too.
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'id']);
            $table->index(['company_id', 'governorate']);
            $table->index(['company_id', 'isco_code']);
            $table->index(['company_id', 'esco_occupation_id']);
            $table->index(['company_id', 'phone']);
            $table->index(['company_id', 'email']);
        });

        Schema::create('beneficiary_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 100)->nullable();          // as it was at the time
            $table->string('action', 20);                          // created | updated
            $table->json('changes')->nullable();                   // {field: {from, to}} or {field: {changed: true}}
            $table->timestamp('created_at')->useCurrent();

            $table->index(['beneficiary_id', 'id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_changes');
        Schema::dropIfExists('beneficiaries');
    }
};
