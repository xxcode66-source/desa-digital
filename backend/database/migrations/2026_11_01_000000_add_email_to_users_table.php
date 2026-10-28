<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 0 — kolom email (nullable) untuk login staff/operator di panel Filament.
// Warga tetap login lewat NIK/HP (Fase 1); email di sini hanya utk akun perangkat desa.
// Verifikasi & RBAC sesungguhnya dikerjakan di Fase 1.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
