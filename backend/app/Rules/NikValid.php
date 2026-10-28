<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validasi struktur NIK Indonesia (16 digit) sesuai PEMBANGUNAN.md 4.1.
 * Bukan validasi ke Dukcapil (butuh layanan berbayar) — ini penyaringan struktur:
 *   - tepat 16 digit angka;
 *   - kode provinsi (2 digit pertama) dalam rentang 11..96;
 *   - bagian tanggal lahir (digit 7-12, format DDMMYY) valid.
 *   - hari 41..71 menandakan perempuan (+40).
 */
class NikValid implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nik = (string) $value;

        if (! preg_match('/^\d{16}$/', $nik)) {
            $fail('NIK harus 16 digit angka.');

            return;
        }

        $province = (int) substr($nik, 0, 2);
        if ($province < 11 || $province > 96) {
            $fail('Kode provinsi pada NIK tidak valid.');

            return;
        }

        $day = (int) substr($nik, 6, 2);
        $month = (int) substr($nik, 8, 2);

        // Hari: 01-31 (laki-laki) atau 41-71 (perempuan, +40).
        $realDay = $day > 40 ? $day - 40 : $day;
        $isFemaleRange = $day >= 41 && $day <= 71;

        if ($realDay < 1 || $realDay > 31 || (! ($day >= 1 && $day <= 31) && ! $isFemaleRange)) {
            $fail('Tanggal lahir pada NIK tidak valid.');

            return;
        }

        if ($month < 1 || $month > 12) {
            $fail('Bulan lahir pada NIK tidak valid.');

            return;
        }
    }
}
