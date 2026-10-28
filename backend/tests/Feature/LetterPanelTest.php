<?php
// Smoke test panel Filament Fase 2: halaman antrean surat & jenis surat bisa dibuka
// staff, dan resource terdaftar dengan benar. Menguat bahwa wiring Filament v5
// (Schemas API, tabel, infolist) tidak error saat dirender.

namespace Tests\Feature;

use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterPanelTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): User
    {
        $v = Village::factory()->create();
        $u = User::factory()->create(['village_id' => $v->id]);
        $u->assignRole($role);

        return $u;
    }

    public function test_operator_can_open_letter_queue(): void
    {
        $this->actingAs($this->staffWithRole('operator'))
            ->get('/admin/letters')
            ->assertOk();
    }

    public function test_kades_can_open_letter_types(): void
    {
        $this->actingAs($this->staffWithRole('kades'))
            ->get('/admin/letter-types')
            ->assertOk();
    }

    public function test_warga_without_staff_role_cannot_open_panel(): void
    {
        $warga = User::factory()->create();
        $warga->assignRole('warga');

        $this->actingAs($warga)->get('/admin/letters')->assertForbidden();
    }
}
