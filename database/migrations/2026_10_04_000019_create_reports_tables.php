<?php

use App\Services\Beneficiaries\BeneficiaryRecorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Reports and the Dashboard (Steps 14 and 15)
//  Location: database/migrations/2026_10_04_000019_create_reports_tables.php
//  Scope: docs/SCOPE_REPORTS.md · the dashboard demo (docs/dashboard-demo.html)
//
//  beneficiaries (two new columns, filled for everyone already there)
//    · industry   the sub-sector (I01, T02, S05 …) of the person's current
//                 job, or of the most recent one with a sector. "Industry"
//                 in Reports. Kept up to date on every save.
//    · source     how the person joined: manual (the form) · intake (Guided
//                 Intake) · cv_upload (Upload CVs) · pool (added from the
//                 Public Talent Pool) · self (a job seeker's own profile in
//                 the public pool). The dashboard's "New people by month".
//                 For people registered before this step it is worked out
//                 from what is known: added from the pool → pool; a CV
//                 uploaded in a batch → cv_upload; a CV given in Guided
//                 Intake → intake; otherwise → manual.
//
//  saved_reports     a report's question (filters, count or average, split),
//                    saved under a name for the team of ONE workspace.
//                    The numbers are always worked out again when opened.
//  report_downloads  every Excel / PDF download: who, when, which report.
//                    company_id is null for the Super Admin's reports.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('industry', 5)->nullable()->after('experience_months');
            $table->string('source', 12)->nullable()->after('occupation_set_at');
            $table->index(['company_id', 'industry']);
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('params');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name', 100)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });

        Schema::create('report_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 100)->nullable();
            $table->string('format', 5);                                // xlsx | pdf
            $table->json('params');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
        });

        $this->fillExisting();
    }

    /** Industry and source for the people already registered. */
    private function fillExisting(): void
    {
        $pool = DB::table('pool_additions')->whereNotNull('beneficiary_id')->pluck('beneficiary_id')->flip();
        $poolCompanies = DB::table('companies')->where('is_pool', true)->pluck('id')->flip();
        $cvs = DB::table('cv_documents')->whereNotNull('beneficiary_id')->whereIn('status', ['added', 'approved'])
            ->select('beneficiary_id', DB::raw('MAX(CASE WHEN batch_id IS NULL THEN 0 ELSE 1 END) as batched'))
            ->groupBy('beneficiary_id')->pluck('batched', 'beneficiary_id');

        DB::table('beneficiaries')->select('id', 'company_id', 'work_history')->orderBy('id')
            ->chunk(500, function ($rows) use ($pool, $poolCompanies, $cvs) {
                foreach ($rows as $r) {
                    $jobs = json_decode((string) $r->work_history, true);
                    $source = match (true) {
                        isset($poolCompanies[$r->company_id]) => 'self',
                        isset($pool[$r->id])                  => 'pool',
                        isset($cvs[$r->id])                   => (int) $cvs[$r->id] === 1 ? 'cv_upload' : 'intake',
                        default                               => 'manual',
                    };
                    DB::table('beneficiaries')->where('id', $r->id)->update([
                        'industry' => BeneficiaryRecorder::industryOf(is_array($jobs) ? $jobs : []),
                        'source'   => $source,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_downloads');
        Schema::dropIfExists('saved_reports');
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'industry']);
            $table->dropIndex(['company_id', 'created_at']);
            $table->dropColumn(['industry', 'source']);
        });
    }
};
