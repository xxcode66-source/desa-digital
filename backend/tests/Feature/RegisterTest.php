<?php
// Feature test alur registrasi + OTP Fase 1 (PEMBANGUNAN.md 4.1).
// OTP di-*issue* lewat service (gateway WA/SMS di-stub), lalu register memverifikasinya.

namespace Tests\Feature;

use App\Models\User;
use App\Models\Village;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function village(): Village
    {
        return Village::factory()->create();
    }

    private function payload(Village $v, array $overrides = []): array
    {
        return array_merge([
            'village_id' => $v->id,
            'nik' => '3201001003900001',
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'rt' => '03',
            'rw' => '01',
            'consent' => true,
        ], $overrides);
    }

    public function test_send_otp_returns_debug_code_in_testing(): void
    {
        $response = $this->postJson('/api/register/otp', ['phone' => '081234567890']);

        $response->assertOk()->assertJsonStructure(['message', 'debug_otp']);
    }

    public function test_send_otp_rejects_already_registered_phone(): void
    {
        User::factory()->create(['phone' => '081234567890']);

        $this->postJson('/api/register/otp', ['phone' => '081234567890'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_register_creates_pending_warga_and_verifies_phone(): void
    {
        $v = $this->village();
        $code = app(OtpService::class)->issue('081234567890', 'register');

        $response = $this->postJson('/api/register', $this->payload($v, ['otp_code' => $code]));

        $response->assertStatus(201)->assertJsonStructure(['token', 'user']);

        $user = User::where('phone', '081234567890')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertNotNull($user->consent_given_at);
        $this->assertTrue($user->hasRole('warga'));
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_register_rejects_wrong_otp(): void
    {
        $v = $this->village();
        app(OtpService::class)->issue('081234567890', 'register');

        $this->postJson('/api/register', $this->payload($v, ['otp_code' => '000000']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('otp_code');

        $this->assertDatabaseMissing('users', ['phone' => '081234567890']);
    }

    public function test_register_requires_consent(): void
    {
        $v = $this->village();
        $code = app(OtpService::class)->issue('081234567890', 'register');

        $this->postJson('/api/register', $this->payload($v, [
            'otp_code' => $code,
            'consent' => false,
        ]))->assertStatus(422)->assertJsonValidationErrors('consent');
    }

    public function test_register_validates_nik_structure(): void
    {
        $v = $this->village();
        $code = app(OtpService::class)->issue('081234567890', 'register');

        $this->postJson('/api/register', $this->payload($v, [
            'otp_code' => $code,
            'nik' => '9901001013900001', // provinsi & bulan tidak valid
        ]))->assertStatus(422)->assertJsonValidationErrors('nik');
    }

    public function test_login_blocked_when_phone_not_verified(): void
    {
        User::factory()->unverifiedPhone()->create([
            'phone' => '081234567890',
            'password' => 'rahasia123',
        ]);

        $this->postJson('/api/login', ['phone' => '081234567890', 'password' => 'rahasia123'])
            ->assertStatus(403)
            ->assertJson(['code' => 'phone_unverified']);
    }
}
