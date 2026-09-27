<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Companies (Partner Organisations)
//  Location: database/migrations/0001_01_01_000000_create_companies_table.php
//
//  Every partner organisation on Massar (NGO, training provider,
//  employer, government programme) is a "company" — the tenant.
//  Everything a partner owns (beneficiaries, CVs, postings, notes,
//  workspace rules …) carries a company_id and is isolated to it
//  by App\Support\Concerns\BelongsToCompany.
//
//  Created BEFORE users on purpose: users.company_id points here.
//  companies.created_by → users is added in the users migration,
//  once that table exists (the two reference each other).
//
//  Subscription & seats (Scope v2 §4):
//    seat_limit            → how many user accounts the partner may
//                            have (company admin included). Enforced
//                            by App\Http\Controllers\App\TeamController.
//    subscription_ends_at  → access stops after this moment. NULL
//                            means no expiry (e.g. a paid annual
//                            contract managed outside the app).
//    expiry_notified_at    → last reminder email, so the daily
//                            reminder command does not email every day.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // ── Identity ───────────────────────────────────────
            $table->string('name');                         // English / official name
            $table->string('name_ar')->nullable();          // Arabic name
            $table->string('type', 30)->default('ngo');     // see Company::TYPES
            $table->string('governorate', 10)->nullable();  // head-office governorate key (cai, giz, alx …)

            // ── Contact ────────────────────────────────────────
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();

            // ── Subscription & seats ───────────────────────────
            $table->unsignedSmallInteger('seat_limit')->default(5);
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('expiry_notified_at')->nullable();

            // ── Status ─────────────────────────────────────────
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable(); // FK added in the users migration

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
