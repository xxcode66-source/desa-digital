<?php
// Fase 0 — fondasi backend (lihat PEMBANGUNAN.md bagian 8, Prompt Fase 0).
// Hanya tabel dasar: villages, users, roles, user_roles, audit_logs, sessions.
// Tabel fitur (letters, pbb, umkm, complaints) baru dibuat pada fasenya masing-masing.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('letter_number_format')->nullable(); // mis. 470/{seq}/DS/{month}/{year}
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained();
            $table->string('nik', 16)->nullable()->unique();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('password');
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // warga, umkm, rt, kolektor, operator, kades, bendahara_pbb, admin
            $table->string('label')->nullable(); // nama tampilan bahasa Indonesia sederhana
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained();
            $table->string('scope_rt', 5)->nullable();
            $table->string('scope_rw', 5)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'role_id', 'scope_rt', 'scope_rw']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');            // mis. pbb.deposit.approved
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('villages');
    }
};
