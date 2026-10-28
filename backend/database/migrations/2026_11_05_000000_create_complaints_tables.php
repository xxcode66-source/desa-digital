<?php
// Fase 3 — Pengaduan warga (PEMBANGUNAN.md 4.5, 7, 8).
// complaints         : laporan warga (kategori, judul, isi, lokasi opsional, foto) + status
//                      + opsi anonim (identitas pelapor disembunyikan dari papan publik).
// complaint_photos   : foto bukti di disk privat (mirip letter_attachments, hemat kuota).
// complaint_replies  : satu tabel percakapan + riwayat — balasan petugas & pesan warga,
//                      sekaligus mencatat perubahan status (from -> to) agar riwayat utuh.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('title');
            $table->text('description');
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('diterima');
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['village_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('complaint_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('complaint_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->boolean('is_staff')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_replies');
        Schema::dropIfExists('complaint_photos');
        Schema::dropIfExists('complaints');
    }
};
