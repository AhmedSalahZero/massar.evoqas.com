<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — let weekly hours hold the figures exactly as published
//  Location: database/migrations/2026_09_25_000006_widen_weekly_hours_on_market_profiles.php
//
//  The April 2025 Egypt Occupational Outlook shows a few occupations
//  with 403–507 hours a week (a lost decimal point). The column only
//  took numbers up to 255, so MySQL refused the whole import. The
//  figures are kept as published and flagged ("hours") by MarketFile,
//  so the column must be able to hold them. Up to 65,535 now.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enoc_market_profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('weekly_hours')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('enoc_market_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('weekly_hours')->nullable()->change();
        });
    }
};
