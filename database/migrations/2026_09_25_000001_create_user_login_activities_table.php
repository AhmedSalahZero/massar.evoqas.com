<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — User Login Activities
//  Location: database/migrations/2026_09_25_000001_create_user_login_activities_table.php
//
//  Append-only activity log, written by UserLoginFrequencyService
//  (App\Enums\LoginActivityType):
//    'login'        → one row per explicit sign-in.
//    'daily_access' → at most one row per user per calendar day,
//                     on their first page of the day (TrackDailyUserAccess).
//
//  Feeds the Super Admin "Activity" screen. No updated_at — rows
//  never change once written.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('login_at');
            $table->date('activity_date');
            $table->string('type', 20);
            $table->string('source', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'activity_date', 'type'], 'user_login_activities_user_day_type_idx');
            $table->index('activity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_activities');
    }
};
