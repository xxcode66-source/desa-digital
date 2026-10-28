<?php
// Fase 5 — Marketplace UMKM (PEMBANGUNAN.md 4.4, 7, 8). TANPA keranjang/pembayaran:
// aplikasi hanya menampilkan toko & produk warga + tombol "Chat WhatsApp" (pesan otomatis)
// sebagai jembatan transaksi offline.
//
// shops            : toko pelaku UMKM (punya pemilik user, kategori, lokasi/RT, WA, foto) +
//                    status verifikasi operator (pending/aktif/ditolak/nonaktif) + is_hidden
//                    utk moderasi. Produk hanya tampil bila toko aktif & tidak disembunyikan.
// products         : barang/jasa milik satu toko (nama, harga, deskripsi, stok tersedia/habis).
// product_photos   : foto produk (maks 5) di disk privat — pola sama dgn complaint_photos.
// product_reports  : laporan warga atas produk/toko (moderasi); salah satu product/shop terisi.
// shop_stats       : counter agregat per toko (jumlah dilihat & klik WA) utk statistik penjual.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('address')->nullable();
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('opening_hours')->nullable();
            $table->string('wa_number');
            $table->string('disk')->default('local');
            $table->string('photo_path')->nullable();
            $table->string('photo_name')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_hidden')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            // Satu pemilik (user) cukup satu toko agar "toko saya" tak ambigu.
            $table->unique(['village_id', 'user_id']);
            $table->index(['village_id', 'status']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->index(['village_id', 'is_available']);
            $table->index('shop_id');
        });

        Schema::create('product_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('product_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->string('status')->default('baru');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('handle_note')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('shop_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('wa_clicks_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_stats');
        Schema::dropIfExists('product_reports');
        Schema::dropIfExists('product_photos');
        Schema::dropIfExists('products');
        Schema::dropIfExists('shops');
    }
};
