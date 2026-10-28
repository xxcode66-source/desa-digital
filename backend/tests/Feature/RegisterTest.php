<?php
// Feature test alur registrasi Fase 1 (PEMBANGUNAN.md 4.1) — TANPA OTP/SMS pihak ketiga.
// Registrasi memvalidasi NIK + consent, membuat user status pending + role warga,
// lalu warga bisa langsung login (verifikasi identitas menyusul oleh operator di panel).

namespace Tests\Feature;

use App\Models\User;
use App\Models\Village;
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

    public function test_register_creates_pending_warga_with_consent(): void
    {
        $v = $this->village();

        $response = $this->postJson('/api/register', $this->payload($v));

        $response->assertStatus(201)->assertJsonStructure(['token', 'user']);

        $user = User::where('phone', '081234567890')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertNotNull($user->consent_given_at);
        $this->assertTrue($user->hasRole('warga'));
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_warga_can_login_right_after_register(): void
    {
        $v = $this->village();
        $this->postJson('/api/register', $this->payload($v))->assertStatus(201);

        $this->postJson('/api/login', ['phone' => '081234567890', 'password' => 'rahasia123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_register_requires_consent(): void
    {
        $v = $this->village();

        $this->postJson('/api/register', $this->payload($v, ['consent' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('consent');
    }

    public function test_register_validates_nik_structure(): void
    {
        $v = $this->village();

        // provinsi (99) & bulan (13) tidak valid secara struktur.
        $this->postJson('/api/register', $this->payload($v, ['nik' => '9901001303900001']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nik');
    }

    public function test_register_rejects_duplicate_nik(): void
    {
        $v = $this->village();
        User::factory()->create(['nik' => '3201001003900001', 'phone' => '081999999999']);

        $this->postJson('/api/register', $this->payload($v))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nik');
    }

    public function test_register_rejects_duplicate_phone(): void
    {
        $v = $this->village();
        User::factory()->create(['phone' => '081234567890']);

        $this->postJson('/api/register', $this->payload($v, ['nik' => '3201001003900002']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_register_validates_phone_format(): void
    {
        $v = $this->village();

        $this->postJson('/api/register', $this->payload($v, ['phone' => '12345']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }
}
