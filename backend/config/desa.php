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
    ],

    // Modul Surat (Fase 2, PEMBANGUNAN.md 4.2).
    'letters' => [
        'number_pad' => 3, // {seq} -> 001
        'max_attachment_kb' => (int) env('DESA_LETTER_MAX_ATT_KB', 3072), // hemat kuota (±3 MB)
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
];
