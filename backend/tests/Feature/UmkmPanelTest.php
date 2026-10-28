<?php
// Smoke test panel Filament Fase 5: antrean UMKM (toko, produk, laporan moderasi) bisa dibuka
// petugas desa & resource terdaftar benar, membuktikan wiring Filament v5 (tabel + infolist +
// aksi review) tidak error saat dirender. Warga non-staff ditolak. Beranda (widget UMKM) ikut.

namespace Tests\Feature;

use App\Models\ProductReport;
use App\Models\Shop;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UmkmPanelTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $v = Village::factory()->create();
        $u = User::factory()->create(['village_id' => $v->id]);
        $u->assignRole($role);

        return $u;
    }

    public function test_staff_can_open_umkm_pages(): void
    {
        $operator = $this->staff('operator');
        $shop = Shop::factory()->active()->create(['village_id' => $operator->village_id]);

        $this->actingAs($operator)->get('/admin/umkm/shops')->assertOk();
        $this->actingAs($operator)->get('/admin/umkm/products')->assertOk();
        $this->actingAs($operator)->get('/admin/umkm/product-reports')->assertOk();
        $this->actingAs($operator)->get("/admin/umkm/shops/{$shop->id}")->assertOk();
    }

    public function test_kades_and_admin_can_open_umkm_queue(): void
    {
        foreach (['kades', 'admin'] as $role) {
            $this->actingAs($this->staff($role))->get('/admin/umkm/shops')->assertOk();
        }
    }

    public function test_dashboard_with_umkm_widget_renders(): void
    {
        $operator = $this->staff('operator');
        ProductReport::create(['village_id' => $operator->village_id, 'reason' => 'x']);

        $this->actingAs($operator)->get('/admin')->assertOk();
    }

    public function test_warga_without_staff_role_cannot_open_panel(): void
    {
        $warga = User::factory()->create();
        $warga->assignRole('warga');

        $this->actingAs($warga)->get('/admin/umkm/shops')->assertForbidden();
    }
}
