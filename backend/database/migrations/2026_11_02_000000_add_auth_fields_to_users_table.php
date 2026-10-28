<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 1 — Auth & Verifikasi (PEMBANGUNAN.md 4.1 & 5).
// Tanpa OTP/SMS pihak ketiga. Verifikasi identitas dilakukan operator di panel Filament
// (status pending/verified/rejected) — kolom status sudah ada di migrasi awal.
// consent_given_at + terms_version : persetujuan data (syarat Play Store / UU PDP).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('consent_given_at')->nullable()->after('status');
            $table->string('terms_version')->nullable()->after('consent_given_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['consent_given_at', 'terms_version']);
        });
    }
};
