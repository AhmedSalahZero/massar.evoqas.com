<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Occupation Backbone (Scope v2 §1 Data Backbone)
//  Location: database/migrations/2026_09_25_000004_create_occupation_backbone_tables.php
//
//  The shared, platform-owned reference layer. Read-only for
//  partners; filled only by `php artisan backbone:import`.
//
//      isco_groups ──< enoc_occupations      (same 4-digit code, 1:1)
//           │
//           └────────< esco_occupations      (each ESCO occupation sits
//                          │                   in exactly one unit group)
//                          └── parent_id      (ESCO's own finer levels,
//                                              e.g. 2654.1 → 2654.1.7)
//
//      occupation_labels   every searchable title (Arabic masculine
//                          and feminine, English, alternative titles)
//                          from all three standards, pre-normalised
//                          for fast Arabic/English search.
//      backbone_imports    one row per import run: files, editions,
//                          checksums, counts, status → the version
//                          history of the backbone.
//
//  HOW OTHER MODULES LINK TO IT (important for scale)
//  Anything classified later (CVs, beneficiaries, jobs) stores
//  isco_group_id (the 4-digit unit group, always known) and, when
//  known, esco_occupation_id. ENOC codes equal ISCO-08 unit codes, so
//  filtering/reporting in ENOC, ISCO-08 or ESCO is a plain indexed
//  lookup on those two columns — no mapping tables, no re-coding,
//  fast at millions of records.
//
//  IDs are never reused: an import updates rows in place by their
//  official code/URI and marks vanished ones inactive instead of
//  deleting them, so old links never break.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backbone_imports', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('running');   // running | completed | failed | unchanged
            $table->json('editions')->nullable();               // config('backbone.editions') at the time
            $table->json('files')->nullable();                  // [key => [name, sha256, bytes]]
            $table->json('counts')->nullable();                 // what was imported / checked
            $table->text('message')->nullable();                // error or note, in plain words
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('triggered_via', 20)->default('console');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });

        // ── ISCO-08: 10 major · 43 sub-major · 130 minor · 436 unit ──
        Schema::create('isco_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique();                // '2', '26', '265', '2654' (leading zeros kept: '0110')
            $table->unsignedTinyInteger('level');               // 1..4 = code length
            $table->foreignId('parent_id')->nullable()->constrained('isco_groups')->nullOnDelete();
            $table->char('major_code', 1)->index();             // first digit, for fast grouping
            $table->string('title_en');
            $table->string('title_ar')->nullable();
            $table->text('definition_en')->nullable();
            $table->text('tasks_en')->nullable();
            $table->text('included_en')->nullable();
            $table->text('excluded_en')->nullable();
            $table->text('notes_en')->nullable();
            $table->foreignId('last_import_id')->nullable()->constrained('backbone_imports')->nullOnDelete();
            $table->timestamps();

            $table->index(['level', 'code']);
        });

        // ── ENOC: 9 Egyptian major groups (Arabic names) ─────────────
        Schema::create('enoc_major_groups', function (Blueprint $table) {
            $table->id();
            $table->char('code', 1)->unique();
            $table->string('title_ar');
            $table->timestamps();
        });

        // ── ENOC: 426 official Egyptian occupations (4-digit) ────────
        Schema::create('enoc_occupations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique();                // = ISCO-08 unit group code
            $table->foreignId('isco_group_id')->constrained('isco_groups');
            $table->char('major_code', 1)->index();
            $table->string('title_ar');
            $table->text('description_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('last_import_id')->nullable()->constrained('backbone_imports')->nullOnDelete();
            $table->timestamps();

            $table->unique('isco_group_id');
        });

        // ── ESCO: ~3,000 detailed occupations ───────────────────────
        Schema::create('esco_occupations', function (Blueprint $table) {
            $table->id();
            $table->string('uri')->unique();                    // ESCO's permanent identifier
            $table->string('code', 40)->index();                // '2654.1.7'
            $table->string('sort_key', 80)->index();            // '2654.0001.0007' — natural order (…1.2 before …1.10)
            $table->foreignId('isco_group_id')->constrained('isco_groups');
            $table->string('isco_code', 4)->index();
            $table->foreignId('parent_id')->nullable()->constrained('esco_occupations')->nullOnDelete();
            $table->unsignedTinyInteger('depth')->default(1);   // 1 = directly under the unit group
            $table->string('title_en');
            $table->string('title_ar')->nullable();             // as published: 'محاسب / محاسبة'
            $table->string('title_ar_male')->nullable();
            $table->string('title_ar_female')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('definition_en')->nullable();
            $table->text('scope_note_en')->nullable();
            $table->boolean('is_regulated')->default(false);
            $table->string('nace_codes')->nullable();
            $table->string('esco_version', 20)->nullable();
            $table->timestamp('esco_modified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('last_import_id')->nullable()->constrained('backbone_imports')->nullOnDelete();
            $table->timestamps();

            $table->index(['isco_group_id', 'is_active']);
        });

        // ── Every searchable title, from all three standards ────────
        Schema::create('occupation_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isco_group_id')->constrained('isco_groups')->cascadeOnDelete();
            $table->foreignId('esco_occupation_id')->nullable()->constrained('esco_occupations')->cascadeOnDelete();
            $table->string('source', 4);                        // isco | enoc | esco
            $table->char('lang', 2);                            // ar | en
            $table->string('kind', 10);                         // preferred | male | female | alt | hidden
            $table->string('label');
            $table->string('normalized')->index();              // see App\Support\TextNormalizer

            $table->index(['source', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occupation_labels');
        Schema::dropIfExists('esco_occupations');
        Schema::dropIfExists('enoc_occupations');
        Schema::dropIfExists('enoc_major_groups');
        Schema::dropIfExists('isco_groups');
        Schema::dropIfExists('backbone_imports');
    }
};
