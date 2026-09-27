<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  Massar — Email Verification Codes (OTP)
//  Location: database/migrations/2026_09_25_000002_create_email_verification_codes_table.php
//
//  One-time codes emailed to a user to confirm their address
//  (App\Services\Auth\EmailVerificationService). Only the HASH of
//  the code is stored, never the code itself. Switched on/off by
//  AUTH_EMAIL_VERIFICATION_ENABLED (config/auth_verification.php).
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
    }
};
