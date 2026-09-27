<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — CV Bank: uploads, reading results, download log
//  Location: database/migrations/2026_09_26_000010_create_cv_tables.php
//  Scope v2 §3 Bulk CV Upload · CV Reading Engine · Review Queue ·
//           Duplicate Detection, §7 Secure CV File Storage ·
//           Personal Data Protection
//
//  cv_batches       one row per upload (up to 50 files): who and when.
//  cv_documents     one row per CV file: where the original is kept
//                   (private disk, encrypted, never web-accessible),
//                   the text read from it, what the reading engine
//                   found, and what happened to it:
//                     added       read with nothing uncertain → profile created automatically
//                     review      waiting for a person (something missing or uncertain)
//                     duplicate   same mobile, email or file already in this workspace
//                     unreadable  a scan, a locked PDF, an old .doc …
//                     approved    a reviewer checked it and created the profile
//                     attached    a reviewer added it to an existing profile
//                     rejected    a reviewer rejected it: the file is deleted and
//                                 the text and reading are wiped; only the file
//                                 name and who rejected it are kept
//  cv_access_logs   every download or opening of an original CV file:
//                   who and when (Scope v2 §7 "access logs for CV downloads").
//
//  Everything carries company_id: a partner never sees another
//  partner's CVs. Deleting a partner deletes these rows (cascade); its
//  files are removed by the partner deletion (CompanyController).
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 100)->nullable();
            $table->string('client_id', 64);                          // set by the browser: one upload = one batch
            $table->unsignedSmallInteger('files_expected');
            $table->unsignedSmallInteger('files_received')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'client_id']);
            $table->index(['company_id', 'id']);
        });

        Schema::create('cv_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                           // used in addresses; ids are never shown
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('cv_batches')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploaded_by_name', 100)->nullable();

            $table->string('original_name', 200);
            $table->string('extension', 5);
            $table->unsignedInteger('size');                          // bytes
            $table->char('sha256', 64);                               // the same file uploaded twice
            $table->string('stored_path')->nullable();                // null once rejected

            $table->string('status', 12);                             // see above
            $table->string('problem', 20)->nullable();                // why it could not be read
            $table->string('language', 6)->nullable();                // ar | en | mixed
            $table->longText('text')->nullable();                     // the text read from the file
            $table->json('reading')->nullable();                      // CvReader + OccupationClassifier result
            $table->json('duplicates')->nullable();                   // who it may be the same person as
            $table->string('phone', 20)->nullable();                  // for duplicate checks
            $table->string('email', 150)->nullable();

            $table->foreignId('beneficiary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewed_by_name', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'id']);
            $table->index(['company_id', 'batch_id']);
            $table->index(['company_id', 'sha256']);
            $table->index(['company_id', 'phone']);
            $table->index(['company_id', 'email']);
            $table->index(['company_id', 'beneficiary_id']);
        });

        Schema::create('cv_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cv_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 100)->nullable();
            $table->string('action', 12);                             // download | view
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cv_document_id', 'id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_access_logs');
        Schema::dropIfExists('cv_documents');
        Schema::dropIfExists('cv_batches');
    }
};
