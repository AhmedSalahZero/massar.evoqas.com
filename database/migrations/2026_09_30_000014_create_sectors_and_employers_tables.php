<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Business sectors and employers (Step 10.5)
//  Location: database/migrations/2026_09_30_000014_create_sectors_and_employers_tables.php
//
//  sectors      ONE fixed list: 3 sectors (IND Industrial · TRD Trading ·
//               SRV Service) and their sub-sectors (I01 Food & Beverages,
//               S01 Banking …), in English and Arabic. Loaded from the
//               "Sectors" sheet of database/data/employers/employers.xlsx
//               by `php artisan employers:import`. A sector row has no
//               parent; a sub-sector row has parent = its sector code.
//
//  employers    known companies. company_id NULL = the Massar list for
//               everyone (from the Excel file); company_id set = learned by
//               ONE partner workspace (a caseworker saved a job at a company
//               not in the list and chose its sector) — never shown to
//               other partners.
//                 names       the English and Arabic names and every other
//                             name, normalised, one per line: what CV and
//                             search matching compare against
//                 sub_sector  the sub-sector code
//                 ownership   state | private | foreign | partial
//                 country     2-letter code (EG …)
//
//  Each job in a profile's work history now also keeps (inside the JSON,
//  no new columns): country, sub_sector and employer_id.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique();             // IND | I01 …
            $table->string('parent', 5)->nullable();         // the sector of a sub-sector
            $table->string('name_en', 120);
            $table->string('name_ar', 120);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['parent', 'sort']);
        });

        Schema::create('employers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name_en', 200)->nullable();
            $table->string('name_ar', 200)->nullable();
            $table->text('other_names')->nullable();          // as typed, "; " between names
            $table->text('names');                            // normalised, one per line (search)
            $table->string('sub_sector', 5)->nullable();
            $table->string('ownership', 10)->nullable();
            $table->string('country', 2)->default('EG');
            $table->string('governorate', 5)->nullable();
            $table->string('source', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'country']);
            $table->index('sub_sector');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employers');
        Schema::dropIfExists('sectors');
    }
};
