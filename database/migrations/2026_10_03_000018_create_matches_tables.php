<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Matches (Step 13)
//  Location: database/migrations/2026_10_03_000018_create_matches_tables.php
//  Scope: docs/SCOPE_MATCHES.md
//
//  opportunity_matches        ONE person referred to ONE job or training
//                             of one workspace (one line per pair: a
//                             stopped match is restarted, never doubled).
//                             · stage   referred | accepted | in_progress | done
//                               (done = Hired for a job, Completed for a training)
//                             · status  active | stopped (with the reason)
//                             · changed_at: when the last change was
//                               recorded ("Needs follow-up" after 14 days)
//
//  opportunity_match_events   the match timeline, never edited: referred,
//                             moved (with the skipped stages), corrected
//                             (back one stage, with a reason), stopped,
//                             restarted. Each with the date it happened,
//                             the date it was recorded, who, the note or
//                             reason.
//
//  A profile, a job or a training, or a workspace deleted → its matches
//  go with it (cascade), as with its other records.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                                // job | training (a copy, for filtering)

            $table->string('stage', 12);                               // referred | accepted | in_progress | done
            $table->string('status', 8)->default('active');            // active | stopped
            $table->string('stop_reason', 20)->nullable();             // not_accepted | not_hired | dropped_out | withdrew | other
            $table->string('stop_note', 500)->nullable();

            $table->date('referred_on');                               // the date it happened
            $table->date('stage_on');                                  // the date the current stage (or the stop) happened
            $table->timestamp('changed_at');                           // the last change recorded (follow-up)
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referred_by_name', 100)->nullable();
            $table->timestamps();

            $table->unique(['opportunity_id', 'beneficiary_id']);
            $table->index(['company_id', 'status', 'stage']);
            $table->index(['company_id', 'beneficiary_id']);
            $table->index(['company_id', 'changed_at']);
        });

        Schema::create('opportunity_match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('opportunity_matches')->cascadeOnDelete();
            $table->string('action', 12);                              // referred | moved | corrected | stopped | restarted
            $table->string('from_stage', 12)->nullable();
            $table->string('to_stage', 12)->nullable();
            $table->json('skipped')->nullable();                       // stages jumped over: ['accepted', 'in_progress']
            $table->string('reason', 20)->nullable();                  // the stop reason
            $table->string('note', 500)->nullable();                   // the note, or the reason written for a correction / restart
            $table->date('happened_on');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 100)->nullable();
            $table->timestamp('created_at');                           // the date it was recorded

            $table->index(['match_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_match_events');
        Schema::dropIfExists('opportunity_matches');
    }
};
