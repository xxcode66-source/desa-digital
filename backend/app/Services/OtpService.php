<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;

/**
 * OTP verifikasi nomor HP (Fase 1). Pengiriman lewat WA/SMS di-*stub* dulu —
 * integrasi gateway (Fase 1 asumsi, lihat README) tinggal mengganti send().
 */
class OtpService
{
    public const TTL_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;

    /** Buat + "kirim" kode 6 digit untuk purpose tertentu. Mengembalikan plain utk test/dev. */
    public function issue(string $phone, string $purpose = 'register'): string
    {
        // Matikan kode lama yang belum terpakai untuk phone+purpose yang sama.
        OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->send($phone, $code);

        return $code;
    }

    /** Verifikasi kode aktif. Mengtrue kalau cocok & masih berlaku. */
    public function verify(string $phone, string $code, string $purpose = 'register'): bool
    {
        $otp = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || ! $otp->isUsable()) {
            return false;
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    protected function send(string $phone, string $code): void
    {
        // TODO integrasi WhatsApp/SMS gateway (Fase 1+). Untuk sekarang di-log.
        logger()->info("OTP Desa Digital utk {$phone}: {$code} (dev-only, jangan di produksi).");
    }
}
