<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Jobs & Training, with eligibility inside (Step 12)
//  Location: database/migrations/2026_10_02_000017_create_opportunities_tables.php
//  Scope: docs/SCOPE_JOBS_AND_TRAINING.md
//
//  Agreed on 27 September 2026: eligibility is no longer created on its
//  own. It lives INSIDE each Job and each Training Program. So the three
//  Step 11 tables are replaced (their rows were test data only):
//
//  opportunities            a Job or a Training Program of ONE workspace.
//                           kind = job | training
//                           · the details (title, occupations, where,
//                             seats, dates, contact; employer and job
//                             type for a job; provider, duration, format,
//                             cost and certificate for a training)
//                           · its eligibility: rules (JSON, as in Step 11),
//                             eligible_from, check_from, rules_version
//                           · status open | closed, with a close reason
//                             (filled | cancelled | finished)
//                           · occupation_keys: every level each chosen
//                             occupation sits in, "|isco:2|isco:24|…|",
//                             so the list can be filtered by occupation
//                             at any level with a plain LIKE
//                           · history (JSON): created, rules changed,
//                             closed (with the reason), opened again
//
//  eligibility_assessments  one person checked against one opportunity
//                           (as in Step 11, with opportunity_id)
//  eligibility_runs         \"Check everyone\" (as in Step 11, with opportunity_id)
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('eligibility_runs');
        Schema::dropIfExists('eligibility_assessments');
        Schema::dropIfExists('eligibility_programs');

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                                // job | training
            $table->string('title', 150);                              // one field, any language
            $table->text('description')->nullable();

            // Where and how many.
            $table->json('occupations');                               // ['isco:24', 'esco:2411.1', …]
            $table->text('occupation_keys');                           // "|isco:2|isco:24|…|" (filtering)
            $table->json('governorates');                              // ['cai', 'giz']
            $table->string('city', 100)->nullable();
            $table->unsignedInteger('seats');
            $table->date('deadline')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('contact_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Job only.
            $table->string('employer', 200)->nullable();
            $table->foreignId('employer_id')->nullable()->constrained('employers')->nullOnDelete();
            $table->string('sub_sector', 5)->nullable();
            $table->string('job_type', 12)->nullable();                // full_time | part_time | temporary
            $table->unsignedInteger('salary_from')->nullable();        // EGP a month
            $table->unsignedInteger('salary_to')->nullable();

            // Training only.
            $table->string('provider', 200)->nullable();
            $table->unsignedSmallInteger('duration_value')->nullable();
            $table->string('duration_unit', 8)->nullable();            // hours | days | weeks | months
            $table->string('format', 10)->nullable();                  // in_person | online | mixed
            $table->string('cost_type', 5)->nullable();                // free | paid
            $table->unsignedInteger('cost_amount')->nullable();        // EGP
            $table->string('certificate', 200)->nullable();

            // Eligibility (Step 11's engine, unchanged).
            $table->unsignedTinyInteger('eligible_from');
            $table->unsignedTinyInteger('check_from');
            $table->json('rules');
            $table->unsignedInteger('rules_version')->default(1);

            // Status and history.
            $table->string('status', 10)->default('open');             // open | closed
            $table->string('close_reason', 10)->nullable();            // filled | cancelled | finished
            $table->timestamp('closed_at')->nullable();
            $table->json('history')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name', 100)->nullable();
            $table->string('updated_by_name', 100)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'kind', 'status']);
        });

        Schema::create('eligibility_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->cascadeOnDelete();

            // The automatic check.
            $table->unsignedTinyInteger('score');
            $table->string('auto_result', 15);                        // eligible | check | not_eligible
            $table->json('reasons');
            $table->string('inputs_hash', 40);
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

            $table->unique(['opportunity_id', 'beneficiary_id']);
            $table->index(['company_id', 'beneficiary_id']);
            $table->index(['opportunity_id', 'result', 'score']);
            $table->index(['company_id', 'result']);
        });

        Schema::create('eligibility_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->string('scope', 10);                               // all | search | outdated
            $table->json('filters')->nullable();
            $table->longText('ids')->nullable();
            $table->unsignedBigInteger('cursor')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('done')->default(0);
            $table->json('counts')->nullable();
            $table->string('status', 10)->default('running');          // running | done
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('started_by_name', 100)->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['opportunity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_runs');
        Schema::dropIfExists('eligibility_assessments');
        Schema::dropIfExists('opportunities');

        // Back to the Step 11 tables (empty).
        (require __DIR__.'/2026_10_01_000016_create_eligibility_tables.php')->up();
    }
};
