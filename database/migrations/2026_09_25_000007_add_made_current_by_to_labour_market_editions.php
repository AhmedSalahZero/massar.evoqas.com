<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — who approved a labour market edition
//  Location: database/migrations/2026_09_25_000007_add_made_current_by_to_labour_market_editions.php
//
//  Editions can now be put in use from the admin screen ("Use this
//  edition"). Next to the time (made_current_at) we keep WHO did it,
//  so every switch can be explained later. Empty when it was done
//  from the command line or automatically (the first edition).
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labour_market_editions', function (Blueprint $table) {
            $table->foreignId('made_current_by')->nullable()->after('made_current_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('labour_market_editions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('made_current_by');
        });
    }
};
