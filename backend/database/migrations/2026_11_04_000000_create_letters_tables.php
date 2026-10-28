<?php
// Fase 2 — Surat menyurat / E-Layanan (PEMBANGUNAN.md 4.2, 7, 8).
// letter_types        : jenis surat dinamis (admin bisa tambah tanpa ubah kode) via form_schema_json.
// letters             : pengajuan surat + alur status + penomoran otomatis + token verifikasi publik.
// letter_attachments  : lampiran (KTP/KK/foto usaha) di disk privat (signed URL).
// letter_status_logs  : riwayat tiap perubahan status (siapa, dari -> ke, catatan, field ditandai).
// letter_sequences    : counter penomoran per desa per tahun (anti-duplikat, aman balapan).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('needs_rt')->default(true); // perlu pengantar RT/RW?
            $table->json('form_schema_json');
            $table->json('required_attachments_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['village_id', 'is_active']);
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained();
            $table->foreignId('letter_type_id')->constrained('letter_types');
            $table->foreignId('user_id')->constrained();
            $table->json('data_json');
            $table->string('status')->default('diajukan');
            $table->string('number')->nullable()->unique();
            $table->uuid('public_token')->nullable()->unique();
            $table->text('note')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['village_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('letter_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('kind')->default('lampiran');
            $table->timestamps();
        });

        Schema::create('letter_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->json('flagged_fields_json')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('letter_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['village_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_sequences');
        Schema::dropIfExists('letter_status_logs');
        Schema::dropIfExists('letter_attachments');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_types');
    }
};
