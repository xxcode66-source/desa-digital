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
];
