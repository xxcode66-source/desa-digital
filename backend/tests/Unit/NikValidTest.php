<?php
// Unit test aturan struktur NIK (Fase 1, PEMBANGUNAN.md 4.1). Bukan validasi Dukcapil,
// hanya penyaringan format 16 digit + provinsi + tanggal lahir.

namespace Tests\Unit;

use App\Rules\NikValid;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class NikValidTest extends TestCase
{
    private function passes(string $nik): bool
    {
        $v = Validator::make(['nik' => $nik], ['nik' => ['digits:16', new NikValid]]);

        return $v->passes();
    }

    public function test_valid_male_nik_passes(): void
    {
        $this->assertTrue($this->passes('3201001003900001')); // day 10, month 03
    }

    public function test_valid_female_nik_day_above_40_passes(): void
    {
        $this->assertTrue($this->passes('3201005003900001')); // day 50 => 10 (perempuan)
    }

    public function test_nik_wrong_length_fails(): void
    {
        $this->assertFalse($this->passes('320100100390')); // bukan 16 digit
    }

    public function test_nik_invalid_province_fails(): void
    {
        $this->assertFalse($this->passes('9901001003900001')); // provinsi 99 di luar 11..96
    }

    public function test_nik_invalid_month_fails(): void
    {
        $this->assertFalse($this->passes('3201001013900001')); // month 13
    }

    public function test_nik_invalid_day_fails(): void
    {
        $this->assertFalse($this->passes('3201004003900001')); // day 40 (bukan 1-31 dan bukan 41-71)
    }
}
