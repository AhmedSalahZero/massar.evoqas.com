<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Egypt labour market data (Scope v2 §1 Labour Market Layer)
//  Location: database/migrations/2026_09_25_000005_create_labour_market_tables.php
//
//  labour_market_editions   one row per loaded file (an "edition"),
//                           with its source, reference period, what
//                           it covers, quality findings and a
//                           comparison with the edition before it.
//                           Exactly one edition is current.
//  enoc_market_profiles     the figures for one ENOC occupation in one
//                           edition. Old editions are kept, so reports
//                           stay traceable and change over time can
//                           be shown later.
//
//  Categories ("much faster than average", "higher education" …) are
//  stored as short keys and translated on screen, so Arabic and
//  English show the same fact. Missing figures are NULL — shown as
//  "no data", never as zero.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labour_market_editions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('reference_period')->nullable();          // '2017–2021'
            $table->boolean('reference_estimated')->default(false);  // period not yet confirmed by the source
            $table->string('source')->nullable();
            $table->string('file_name');
            $table->char('sha256', 64)->index();
            $table->string('status', 20)->default('running');  // running | completed | failed
            $table->boolean('is_current')->default(false)->index();
            $table->json('counts')->nullable();                // coverage per kind of figure
            $table->json('quality')->nullable();               // findings about the file itself
            $table->json('comparison')->nullable();            // vs the edition that was current
            $table->text('message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('made_current_at')->nullable();
            $table->timestamps();
        });

        Schema::create('enoc_market_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('labour_market_editions')->cascadeOnDelete();
            $table->foreignId('enoc_occupation_id')->constrained('enoc_occupations');
            $table->foreignId('isco_group_id')->constrained('isco_groups');   // same group, for fast joins

            // ── Workers ───────────────────────────────────────────
            $table->unsignedInteger('workers')->nullable();
            $table->string('workers_trend', 20)->nullable();              // much_faster … decline
            $table->decimal('share_of_employment', 6, 2)->nullable();     // % of all employed in Egypt
            $table->decimal('pct_paid', 5, 1)->nullable();
            $table->decimal('pct_unpaid', 5, 1)->nullable();
            $table->decimal('pct_formal', 5, 1)->nullable();
            $table->decimal('pct_regular', 5, 1)->nullable();
            $table->decimal('pct_women', 5, 1)->nullable();
            $table->decimal('pct_public', 5, 1)->nullable();
            $table->decimal('pct_private', 5, 1)->nullable();
            $table->json('sectors')->nullable();                          // {agriculture: 1.2, …} %
            $table->json('regions')->nullable();                          // {cairo: 'above', …}

            // ── Pay & hours ───────────────────────────────────────
            $table->unsignedInteger('wage_avg')->nullable();              // EGP / month
            $table->unsignedInteger('wage_male')->nullable();
            $table->unsignedInteger('wage_female')->nullable();
            $table->unsignedInteger('wage_public')->nullable();
            $table->unsignedInteger('wage_private')->nullable();
            $table->unsignedTinyInteger('weekly_hours')->nullable();

            // ── What the job needs ────────────────────────────────
            $table->string('education', 20)->nullable();                  // below_secondary | secondary | higher
            $table->json('knowledge')->nullable();                        // top 5, Arabic
            $table->json('abilities')->nullable();
            $table->json('skills')->nullable();
            $table->json('skill_groups')->nullable();                     // {computer: 84, …} 0–100

            // ── The future ────────────────────────────────────────
            $table->string('outlook_trend', 20)->nullable();              // growth to 2030
            $table->string('outlook_jobs', 20)->nullable();               // jobs added/lost per year
            $table->string('green', 20)->nullable();                      // link to the green transition

            $table->json('flags')->nullable();                            // figures to check, e.g. ['sectors']

            $table->unique(['edition_id', 'enoc_occupation_id']);
            $table->index(['edition_id', 'isco_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enoc_market_profiles');
        Schema::dropIfExists('labour_market_editions');
    }
};
