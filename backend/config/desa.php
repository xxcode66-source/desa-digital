<?php
// Konstanta domain Fase 1+ (Auth & Verifikasi). Versi terms dilacak agar persetujuan
// data (UU PDP) bisa diminta ulang saat berubah — lihat users.terms_version.

return [
    'terms_version' => env('DESA_TERMS_VERSION', '2026-10'),

    // Rate limit (percobaan per menit) untuk endpoint sensitif.
    'throttle' => [
        'register' => (int) env('DESA_THROTTLE_REGISTER', 8),
        'letter_submit' => (int) env('DESA_THROTTLE_LETTER_SUBMIT', 10),
        'letter_verify' => (int) env('DESA_THROTTLE_LETTER_VERIFY', 30),
        'complaint_submit' => (int) env('DESA_THROTTLE_COMPLAINT_SUBMIT', 10),
        'complaint_browse' => (int) env('DESA_THROTTLE_COMPLAINT_BROWSE', 30),
        'pbb_nop_check' => (int) env('DESA_THROTTLE_PBB_NOP_CHECK', 10),
        'pbb_deposit_submit' => (int) env('DESA_THROTTLE_PBB_DEPOSIT_SUBMIT', 12),
        'umkm_browse' => (int) env('DESA_THROTTLE_UMKM_BROWSE', 60),
        'umkm_report' => (int) env('DESA_THROTTLE_UMKM_REPORT', 10),
    ],

    // Modul Surat (Fase 2, PEMBANGUNAN.md 4.2).
    'letters' => [
        'number_pad' => 3, // {seq} -> 001
        'max_attachment_kb' => (int) env('DESA_LETTER_MAX_ATT_KB', 3072), // hemat kuota (±3 MB)
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],

    // Modul Pengaduan (Fase 3, PEMBANGUNAN.md 4.5).
    'complaints' => [
        'max_photo_kb' => (int) env('DESA_COMPLAINT_MAX_PHOTO_KB', 3072),
        'max_photos' => 3,
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp'], // pengaduan = foto bukti
        // Kategori baku (code => label). Ditetapkan desa agar formulir konsisten & bisa difilter.
        'categories' => [
            'jalan_rusak' => 'Jalan Rusak',
            'sampah' => 'Sampah / Lingkungan',
            'keamanan' => 'Keamanan',
            'penerangan' => 'Lampu / Penerangan Umum',
            'pelayanan' => 'Pelayanan Desa',
            'saluran_air' => 'Saluran / Drainase',
            'lainnya' => 'Lainnya',
        ],
    ],

    // Modul PBB (Fase 4, PEMBANGUNAN.md 4.3). TANPA pembayaran in-app: setoran/ACC hanya
    // pencatatan internal desa, bukan bukti pelunasan resmi.
    'pbb' => [
        'evidence_max_kb' => (int) env('DESA_PBB_MAX_EVIDENCE_KB', 3072), // foto SPPT/kuitansi (±3 MB)
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp'], // bukti = foto, bukan PDF
        'pending_reminder_days' => (int) env('DESA_PBB_PENDING_REMINDER_DAYS', 7), // setoran pending > N hari -> pengingat
        // Lebar kolom nop di DB; input cek NOP dibersihkan & dibatasi sebatas ini.
        'nop_max_len' => 18,
    ],

    // Modul Marketplace UMKM (Fase 5, PEMBANGUNAN.md 4.4). TANPA keranjang/pembayaran —
    // katalog + jembatan chat WhatsApp; transaksi & harga disepakati offline.
    'umkm' => [
        'max_photo_kb' => (int) env('DESA_UMKM_MAX_PHOTO_KB', 3072), // foto toko/produk (±3 MB)
        'max_product_photos' => 5,
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp'], // katalog = foto, bukan PDF
        // Kategori usaha baku (code => label) — ditetapkan desa agar filter & pencarian konsisten.
        'categories' => [
            'makanan' => 'Makanan',
            'minuman' => 'Minuman',
            'jasa' => 'Jasa',
            'kerajinan' => 'Kerajinan / Tangan',
            'pertanian' => 'Pertanian',
            'lainnya' => 'Lainnya',
        ],
    ],
];
