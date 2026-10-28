<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 1 — Auth & Verifikasi (PEMBANGUNAN.md 4.1 & 5).
// phone_verified_at : kebenaran nomor HP via OTP (bukan identitas).
// status (pending/verified/rejected) TETAP untuk verifikasi identitas oleh operator.
// consent_given_at + terms_version : persetujuan data (syarat Play Store / UU PDP).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('status');
            $table->timestamp('consent_given_at')->nullable()->after('phone_verified_at');
            $table->string('terms_version')->nullable()->after('consent_given_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'consent_given_at', 'terms_version']);
        });
    }
};
