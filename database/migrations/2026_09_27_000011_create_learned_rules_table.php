<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Learned Rules (two layers)
//  Location: database/migrations/2026_09_27_000011_create_learned_rules_table.php
//  Scope v2 §3 Learned Rules · Rule promotion
//
//  One row = one thing a person taught the CV reading engine:
//      heading  "Where I Worked"  → a section (experience, skills …),
//                                   'responsibilities' (a sub-heading
//                                   inside a job) or 'none' (not a heading)
//      title    "مندوب مبيعات"    → an occupation (ESCO job, or an
//                                   ISCO-08 group ESCO does not detail)
//      skill    "Peachtree"       → a skill, found anywhere in a CV
//
//  Layer 1  company_id = the partner: works at once, for that
//           workspace only.
//  Layer 2  company_id = NULL: a Massar rule, for every partner. It
//           exists only when the Super Admin PROMOTED a partner's
//           proposal (proposal_status on the partner's row).
//  Only the words and their meaning are kept here — never a CV, a
//  name or any beneficiary data.
//  Deleting a partner deletes its rules; a Massar rule promoted from
//  one of them stays (promoted_from_id becomes NULL).
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learned_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();   // NULL = Massar rule
            $table->string('kind', 10);                                  // heading | title | skill | employer
            $table->string('phrase', 120);                               // as the person taught it
            $table->string('normalized', 191);                           // how it is matched
            $table->string('section', 30)->nullable();                   // heading → section key / responsibilities / none
            $table->foreignId('esco_occupation_id')->nullable()->constrained('esco_occupations')->nullOnDelete();
            $table->string('occupation_unit', 4)->nullable();            // title → ISCO-08 group, when ESCO does not detail it
            $table->string('skill_name', 100)->nullable();               // skill → the name added to the profile
            $table->string('source', 10)->default('page');               // review | page | promoted
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name', 100)->nullable();
            $table->unsignedInteger('uses')->default(0);                 // CVs read with this rule
            $table->timestamp('last_used_at')->nullable();

            // Layer 2: the partner's proposal, and the Super Admin's decision.
            $table->string('proposal_status', 10)->nullable();           // pending | promoted | declined
            $table->string('proposed_by_name', 100)->nullable();
            $table->timestamp('proposed_at')->nullable();
            $table->string('decided_by_name', 100)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 300)->nullable();
            $table->foreignId('promoted_from_id')->nullable()->constrained('learned_rules')->nullOnDelete();
            $table->string('promoted_from_company', 150)->nullable();    // partner name, for the Super Admin only

            $table->timestamps();

            $table->index(['company_id', 'kind', 'normalized']);
            $table->index(['proposal_status', 'proposed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learned_rules');
    }
};
