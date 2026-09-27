<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — sub-sectors proposed through "Other (not in the list)"
//  Location: database/migrations/2026_09_30_000015_create_sector_proposals_table.php
//
//  When a job's sub-sector is "Other" (typed), the text is counted here,
//  once per sector + wording. The Super Admin (Backbone permission)
//  decides: add it as a new official sub-sector, say it is an existing
//  one, or decline. Only the wording and how many jobs use it are kept —
//  never who or which profile (the platform sees aggregates only).
//    status   open | added | merged | declined
//    code     the official sub-sector it became / was merged into
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('sector', 5);                    // IND | TRD | SRV
            $table->string('key', 150);                     // normalised wording
            $table->string('label', 150);                   // as first typed
            $table->unsignedInteger('times')->default(0);   // jobs using it
            $table->string('status', 10)->default('open');
            $table->string('code', 5)->nullable();
            $table->timestamps();

            $table->unique(['sector', 'key']);
            $table->index(['status', 'times']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_proposals');
    }
};
