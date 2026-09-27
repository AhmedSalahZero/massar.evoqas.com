<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Searchable CV Bank: the search index
//  Location: database/migrations/2026_09_28_000012_create_cv_bank_index_table.php
//  Scope v2 §3 Searchable CV Bank
//
//  One row per profile: every word of the profile (names, jobs and
//  their responsibilities, education, skills, city …) and of every
//  original CV attached to it, written the way searches are compared
//  (TextNormalizer: lower case, أ/إ/آ → ا, ة → ه, ى → ي, no "ال",
//  Arabic digits → 0-9). cv_languages: the languages of those CVs.
//  Kept up to date automatically (CvBankIndex) whenever a profile is
//  saved or a CV is added to it; `php artisan cvbank:index` fills it
//  for profiles that existed before.
//  Same workspace rule as everywhere: company_id, cascade on delete.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_bank_index', function (Blueprint $table) {
            $table->foreignId('beneficiary_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->longText('body');
            $table->string('cv_languages', 20)->nullable();      // "ar", "en", "ar,en"
            $table->unsignedSmallInteger('cv_count')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_bank_index');
    }
};
