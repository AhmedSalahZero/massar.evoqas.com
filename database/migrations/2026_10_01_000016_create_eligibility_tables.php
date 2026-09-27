<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Eligibility Assessment (Step 11)
//  Location: database/migrations/2026_10_01_000016_create_eligibility_tables.php
//  Scope: docs/SCOPE_ELIGIBILITY_ASSESSMENT.md (Scope v2 §3 Eligibility Assessment Engine)
//
//  eligibility_programs     a partner's named set of rules
//                           ("Youth Technician Training 2026"). Owned by
//                           ONE workspace; never seen by another partner.
//                           rules (JSON) = [{type, mode: must|counts,
//                           points, …the rule's own settings}]. The
//                           "Counts" points add up to 100. Two levels:
//                           eligible_from and check_from (0–100).
//                           rules_version goes up each time the rules or
//                           the levels change, so older results show
//                           "the rules changed".
//
//  eligibility_assessments  one person checked against one program:
//                           the automatic score and result, every rule's
//                           reason, and the case worker's decision (with
//                           its required reason). `result` is the one
//                           that counts: the decision when there is one,
//                           otherwise the automatic result.
//
//  eligibility_runs         "Check everyone": one run over the whole
//                           workspace, a CV Bank search result, or the
//                           results the new rules made out of date. The
//                           browser moves it forward a few hundred
//                           profiles at a time, so thousands of people
//                           never block the screen.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibility_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name_en', 150)->nullable();
            $table->string('name_ar', 150)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 10)->default('active');           // active | closed
            $table->unsignedTinyInteger('eligible_from');              // score 0–100
            $table->unsignedTinyInteger('check_from');                 // score 0–100, never above eligible_from
            $table->json('rules');
            $table->unsignedInteger('rules_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name', 100)->nullable();
            $table->string('updated_by_name', 100)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('eligibility_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('eligibility_programs')->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->cascadeOnDelete();

            // The automatic check.
            $table->unsignedTinyInteger('score');
            $table->string('auto_result', 15);                        // eligible | check | not_eligible
            $table->json('reasons');                                   // one line per rule: the rule, pass | fail | missing | na, the value
            $table->string('inputs_hash', 40);                         // the profile details the rules read, to notice a change
            $table->unsignedInteger('rules_version');
            $table->timestamp('checked_at');
            $table->string('checked_by_name', 100)->nullable();

            // The case worker's decision (override).
            $table->string('decision', 15)->nullable();                // eligible | not_eligible | on_hold
            $table->string('decision_reason', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decided_by_name', 100)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_flag', 20)->nullable();           // profile_changed | rules_changed

            $table->string('result', 15);                              // the decision, or the automatic result
            $table->text('notes')->nullable();
            $table->string('notes_by_name', 100)->nullable();
            $table->timestamp('notes_at')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'beneficiary_id']);
            $table->index(['company_id', 'beneficiary_id']);
            $table->index(['program_id', 'result', 'score']);
            $table->index(['company_id', 'result']);
        });

        Schema::create('eligibility_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('eligibility_programs')->cascadeOnDelete();
            $table->string('scope', 10);                               // all | search | outdated
            $table->json('filters')->nullable();                       // the CV Bank search, for "search"
            $table->longText('ids')->nullable();                       // the people found by that search (JSON list)
            $table->unsignedBigInteger('cursor')->default(0);          // last beneficiary id done (all | outdated)
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('done')->default(0);
            $table->json('counts')->nullable();                        // {eligible, check, not_eligible}
            $table->string('status', 10)->default('running');          // running | done
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('started_by_name', 100)->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_runs');
        Schema::dropIfExists('eligibility_assessments');
        Schema::dropIfExists('eligibility_programs');
    }
};
