<?php
// Test fondasi Fase 0: login Sanctum + proteksi auth + profil. Registrasi/OTP di Fase 1.

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_user_can_login_with_phone_and_password(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $response = $this->postJson('/api/login', [
            'phone' => $user->phone,
            'password' => 'rahasia123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->postJson('/api/login', [
            'phone' => $user->phone,
            'password' => 'salah',
        ])->assertStatus(401);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJson(['id' => $user->id]);
    }
}
