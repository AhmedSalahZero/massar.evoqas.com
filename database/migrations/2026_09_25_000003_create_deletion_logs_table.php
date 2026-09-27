<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Deletion Logs
//  Location: database/migrations/2026_09_25_000003_create_deletion_logs_table.php
//
//  A permanent trace of every record deleted inside a partner
//  workspace: who deleted it, when, and a snapshot of what it held
//  (App\Support\DeletionLogger). Beneficiary data is sensitive, so
//  "gone with no trace" is never acceptable (Scope v2 §7 —
//  Personal Data Protection & Consent).
//
//  company_id is kept even after the partner is deleted (no FK
//  cascade) so the platform can still answer "what happened".
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('summary');
            $table->json('payload')->nullable();
            $table->timestamp('deleted_at');

            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_logs');
    }
};
