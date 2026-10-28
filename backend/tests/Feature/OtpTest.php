<?php
// Feature test perilaku OtpService: kedaluwarsa, jumlah percobaan, konsumsi sekali pakai.
// (Ditempatkan di Feature agar pakai RefreshDatabase; menguji service + model langsung.)

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $otp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otp = app(OtpService::class);
    }

    public function test_verify_succeeds_with_correct_code_and_consumes_it(): void
    {
        $code = $this->otp->issue('081234567890', 'register');

        $this->assertTrue($this->otp->verify('081234567890', $code, 'register'));
        // Tidak bisa dipakai dua kali.
        $this->assertFalse($this->otp->verify('081234567890', $code, 'register'));
    }

    public function test_verify_fails_after_expiry(): void
    {
        $code = $this->otp->issue('081234567890', 'register');
        OtpCode::where('phone', '081234567890')->update(['expires_at' => now()->subMinute()]);

        $this->assertFalse($this->otp->verify('081234567890', $code, 'register'));
    }

    public function test_verify_blocked_after_max_attempts(): void
    {
        $code = $this->otp->issue('081234567890', 'register');

        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            $this->otp->verify('081234567890', '000000', 'register');
        }

        // Kode benar pun ditolak karena percobaan habis.
        $this->assertFalse($this->otp->verify('081234567890', $code, 'register'));
    }

    public function test_new_issue_invalidates_previous_active_code(): void
    {
        $first = $this->otp->issue('081234567890', 'register');
        $second = $this->otp->issue('081234567890', 'register');

        // Hanya kode terbaru yang aktif; kode lama dianggap consumed.
        $this->assertFalse($this->otp->verify('081234567890', $first, 'register'));
        $this->assertTrue($this->otp->verify('081234567890', $second, 'register'));
    }
}
