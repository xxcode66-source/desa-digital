<?php
// Konstanta domain Fase 1+ (Auth & Verifikasi). Versi terms dilacak agar persetujuan
// data (UU PDP) bisa diminta ulang saat berubah — lihat users.terms_version.

return [
    'terms_version' => env('DESA_TERMS_VERSION', '2026-10'),

    // Rate limit (percobaan per menit) untuk endpoint sensitif.
    'throttle' => [
        'otp_send' => (int) env('DESA_THROTTLE_OTP_SEND', 5),
        'otp_verify' => (int) env('DESA_THROTTLE_OTP_VERIFY', 10),
        'register' => (int) env('DESA_THROTTLE_REGISTER', 8),
    ],
];
