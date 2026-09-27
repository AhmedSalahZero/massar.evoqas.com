<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — ESCO skills pillar (Scope v2 §1 "Skills & Interests Layer")
//  Location: database/migrations/2026_09_26_000009_create_esco_skills_tables.php
//
//  Part of the shared, platform-owned backbone. Read-only for
//  partners; filled only by `php artisan skills:import`.
//
//      esco_skill_groups ──┐ parent_id     the skills tree: 4 pillars
//           │              └─ (self)       (skills S, knowledge K,
//           │                              language L, transversal T),
//           │                              then up to 3 levels below
//      esco_skill_parents  a skill sits under one or more groups
//           │              and/or broader skills (ESCO allows several)
//      esco_skills ─────── ~14,000 skills and knowledge items, Arabic + English
//           │
//           ├──< esco_occupation_skills >── esco_occupations
//           │        essential or optional, ~126,000 links
//           ├──< esco_skill_relations       skill ↔ skill (essential / optional)
//           └──< skill_labels               every searchable form (Arabic,
//                                           English, alternative terms),
//                                           pre-normalised like occupation_labels
//
//      skill_imports   one row per skills:import run (files, checksums,
//                      counts, status) → the version history.
//
//  IDs are never reused: skills and groups are updated in place by
//  their permanent ESCO URI and marked inactive when a newer edition
//  drops them, so anything that points at a skill later (a
//  beneficiary's skills, a CV, a job) never breaks. The link tables
//  hold nothing but links and are rebuilt on every import.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_imports', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('running');   // running | completed | failed | unchanged
            $table->string('edition')->nullable();              // config('backbone.editions.esco') at the time
            $table->json('files')->nullable();                  // [key => [name, sha256, bytes]]
            $table->unsignedBigInteger('backbone_import_id')->nullable();  // the occupation import it was linked to
            $table->json('counts')->nullable();                 // what was imported / found
            $table->text('message')->nullable();                // error or note, in plain words
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('triggered_via', 20)->default('console');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });

        // ── The skills tree (skill groups) ──────────────────────────
        Schema::create('esco_skill_groups', function (Blueprint $table) {
            $table->id();
            $table->string('uri')->unique();                    // ESCO's permanent identifier
            $table->string('code', 20)->index();                // 'S', 'S1', 'S1.2', 'K', '07', '0715', 'L', 'T1'
            $table->char('pillar', 1)->index();                 // S | K | L | T
            $table->unsignedTinyInteger('level');               // 0 = pillar, 1–3 below it
            $table->foreignId('parent_id')->nullable()->constrained('esco_skill_groups')->nullOnDelete();
            $table->string('sort_key', 60)->index();            // 'S0001.0012' — natural order (S1.2 before S1.12)
            $table->string('title_en');
            $table->string('title_ar')->nullable();             // ESCO has no Arabic for the ISCED knowledge fields
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('scope_note_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('last_import_id')->nullable()->constrained('skill_imports')->nullOnDelete();
            $table->timestamps();
        });

        // ── Skills and knowledge ────────────────────────────────────
        Schema::create('esco_skills', function (Blueprint $table) {
            $table->id();
            $table->string('uri')->unique();
            $table->string('type', 10)->nullable()->index();    // skill | knowledge (null: not set in ESCO)
            $table->string('reuse_level', 20)->nullable()->index();  // transversal | cross-sector | sector-specific | occupation-specific
            $table->string('title_en');
            $table->string('title_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('scope_note_en')->nullable();
            $table->string('esco_version', 20)->nullable();
            $table->timestamp('esco_modified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('last_import_id')->nullable()->constrained('skill_imports')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'type']);
        });

        // ── Where a skill sits: under groups and/or broader skills ──
        Schema::create('esco_skill_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained('esco_skills')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('esco_skill_groups')->cascadeOnDelete();
            $table->foreignId('broader_skill_id')->nullable()->constrained('esco_skills')->cascadeOnDelete();

            $table->index('skill_id');
            $table->index('group_id');
            $table->index('broader_skill_id');
        });

        // ── Which skills each ESCO occupation needs ─────────────────
        Schema::create('esco_occupation_skills', function (Blueprint $table) {
            $table->foreignId('esco_occupation_id')->constrained('esco_occupations')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('esco_skills')->cascadeOnDelete();
            $table->boolean('is_essential');                    // true = essential, false = optional

            $table->primary(['esco_occupation_id', 'skill_id']);
            $table->index(['skill_id', 'is_essential']);
        });

        // ── Skill ↔ skill (e.g. a skill that needs some knowledge) ──
        Schema::create('esco_skill_relations', function (Blueprint $table) {
            $table->foreignId('skill_id')->constrained('esco_skills')->cascadeOnDelete();
            $table->foreignId('related_skill_id')->constrained('esco_skills')->cascadeOnDelete();
            $table->boolean('is_essential');

            $table->primary(['skill_id', 'related_skill_id']);
            $table->index('related_skill_id');
        });

        // ── Every searchable form of every skill ────────────────────
        Schema::create('skill_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained('esco_skills')->cascadeOnDelete();
            $table->char('lang', 2);                            // ar | en
            $table->string('kind', 10);                         // preferred | alt | hidden
            $table->string('label');
            $table->string('normalized')->index();              // see App\Support\TextNormalizer

            $table->index(['skill_id', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_labels');
        Schema::dropIfExists('esco_skill_relations');
        Schema::dropIfExists('esco_occupation_skills');
        Schema::dropIfExists('esco_skill_parents');
        Schema::dropIfExists('esco_skills');
        Schema::dropIfExists('esco_skill_groups');
        Schema::dropIfExists('skill_imports');
    }
};
