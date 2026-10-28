<?php
// Fase 4 — PBB (PEMBANGUNAN.md 4.3, 7, 8). Database awal pajak bumi & bangunan desa.
// pbb_objects         : objek pajak (NOP unik per desa, nama WP tercatat, alamat, RT/RW, luas).
// pbb_bills           : tagihan per NOP per tahun (pokok + denda) + status (belum_bayar/
//                       disetor_pending/lunas). Unik (object, tahun) agar re-import tak duplikat.
// pbb_claims          : klaim kepemilikan warga atas NOP (wajib diverifikasi operator/RT).
// pbb_deposit_batches : setoran RT/Kolektor dikelompokkan per slip/batch (total & jumlah item).
// pbb_deposit_items   : satu NOP+tahun dalam batch — bisa di-ACC sebagian oleh bendahara.
// pbb_import_logs     : riwayat import CSV (versi data, baris dibuat/diupdate).
// TANPA uang di aplikasi: setoran mencatat fisik yang disetor ke bendahara; ACC = status
// internal desa, BUKAN bukti pelunasan resmi (label di UI).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pbb_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->string('nop', 18); // Nomor Objek Pajak 18 digit
            $table->string('wp_name'); // nama wajib pajak tercatat (WP)
            $table->string('address')->nullable();
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->decimal('land_area', 12, 2)->nullable(); // m2 tanah
            $table->decimal('building_area', 12, 2)->nullable(); // m2 bangunan
            $table->timestamps();
            $table->unique(['village_id', 'nop']);
            $table->index(['village_id', 'rt', 'rw']);
        });

        Schema::create('pbb_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pbb_object_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year'); // tahun pajak
            $table->decimal('amount', 14, 2)->default(0); // pokok PBB
            $table->decimal('penalty', 14, 2)->default(0); // denda
            $table->string('status', 20)->default('belum_bayar');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['pbb_object_id', 'year']);
            $table->index(['village_id', 'status']);
            $table->index(['village_id', 'year']);
        });

        Schema::create('pbb_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pbb_object_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending/approved/rejected
            $table->text('note')->nullable(); // alasan/clarification dari warga
            $table->string('disk')->default('local');
            $table->string('evidence_path')->nullable(); // foto SPPT / surat tanah
            $table->string('evidence_name')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['village_id', 'status']);
            $table->index(['user_id', 'status']);
            // Satu klaim aktif per (user, object) — cegah duplikat pengajuan.
            $table->unique(['user_id', 'pbb_object_id']);
        });

        Schema::create('pbb_deposit_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete(); // RT/kolektor
            $table->string('title'); // mis. "Setoran RT 03, 27 Okt"
            $table->decimal('total_amount', 16, 2)->default(0); // total uang fisik
            $table->unsignedSmallInteger('item_count')->default(0);
            $table->string('status', 24)->default('pending'); // pending/partially_approved/approved/rejected
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); // bendahara/admin
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['village_id', 'status']);
            $table->index(['collector_id']);
        });

        Schema::create('pbb_deposit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pbb_deposit_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pbb_bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_claimed', 14, 2)->default(0); // nominal yang disetor utk NOP ini
            $table->string('status', 20)->default('pending'); // pending/approved/rejected
            $table->string('disk')->default('local');
            $table->string('evidence_path')->nullable(); // foto kuitansi / serah terima
            $table->string('evidence_name')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['pbb_deposit_batch_id', 'status']);
        });

        Schema::create('pbb_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->string('status', 20)->default('success'); // success/partial/failed
            $table->text('message')->nullable();
            $table->timestamps();
            $table->index(['village_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pbb_import_logs');
        Schema::dropIfExists('pbb_deposit_items');
        Schema::dropIfExists('pbb_deposit_batches');
        Schema::dropIfExists('pbb_claims');
        Schema::dropIfExists('pbb_bills');
        Schema::dropIfExists('pbb_objects');
    }
};
