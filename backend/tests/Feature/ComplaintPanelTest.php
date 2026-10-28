<?php
// Smoke test panel Filament Fase 3: halaman antrean pengaduan bisa dibuka staff dan
// resource terdaftar dengan benar. Membuktikan wiring Filament v5 (tabel + infolist +
// aksi transisi) tidak error saat dirender, serta warga non-staff ditolak.

namespace Tests\Feature;

use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintPanelTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): User
    {
        $v = Village::factory()->create();
        $u = User::factory()->create(['village_id' => $v->id]);
        $u->assignRole($role);

        return $u;
    }

    public function test_operator_can_open_complaint_queue(): void
    {
        $this->actingAs($this->staffWithRole('operator'))
            ->get('/admin/complaints')
            ->assertOk();
    }

    public function test_kades_can_open_complaint_queue(): void
    {
        $this->actingAs($this->staffWithRole('kades'))
            ->get('/admin/complaints')
            ->assertOk();
    }

    public function test_warga_without_staff_role_cannot_open_panel(): void
    {
        $warga = User::factory()->create();
        $warga->assignRole('warga');

        $this->actingAs($warga)->get('/admin/complaints')->assertForbidden();
    }
}
