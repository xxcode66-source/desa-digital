<?php
// Smoke test panel Filament Fase 4 (PBB): membuktikan resource PBB terdaftar & halaman
// (daftar objek/klaim/setoran/item + detail) bisa dirender staff tanpa error runtime —
// termasuk navigasi Filament v5 (tipe $navigationIcon) & komponen infolist. Sekaligus
// menguji RBAC panel: warga non-staff ditolak.

namespace Tests\Feature;

use App\Models\PbbBill;
use App\Models\PbbClaim;
use App\Models\PbbDepositBatch;
use App\Models\PbbObject;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PbbPanelTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();
        $this->village = Village::factory()->create();
    }

    private function staffWithRole(string $role): User
    {
        $u = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $u->assignRole($role);

        return $u;
    }

    public function test_operator_can_open_pbb_pages(): void
    {
        $actor = $this->staffWithRole('operator');

        // Operator menangani objek & klaim (bukan setoran — itu wilayah bendahara).
        foreach (['pbb-objects', 'pbb-claims'] as $slug) {
            $this->actingAs($actor)->get('/admin/pbb/' . $slug)->assertOk();
        }
    }

    public function test_bendahara_can_open_deposit_pages(): void
    {
        $actor = $this->staffWithRole('bendahara_pbb');

        foreach (['pbb-deposit-batches', 'pbb-deposit-items'] as $slug) {
            $this->actingAs($actor)->get('/admin/pbb/' . $slug)->assertOk();
        }
    }

    public function test_object_detail_page_renders(): void
    {
        $object = PbbObject::factory()->create(['village_id' => $this->village->id]);
        $object->bills()->create([
            'village_id' => $this->village->id, 'year' => 2026, 'amount' => 150000, 'penalty' => 5000,
        ]);

        $this->actingAs($this->staffWithRole('operator'))
            ->get('/admin/pbb/pbb-objects/' . $object->id)
            ->assertOk()
            ->assertSee($object->nop);
    }

    public function test_claim_detail_page_renders(): void
    {
        $claim = PbbClaim::factory()->create(['village_id' => $this->village->id]);

        $this->actingAs($this->staffWithRole('operator'))
            ->get('/admin/pbb/pbb-claims/' . $claim->id)
            ->assertOk();
    }

    public function test_deposit_item_detail_page_renders(): void
    {
        $batch = PbbDepositBatch::factory()->create(['village_id' => $this->village->id]);
        $bill = PbbObject::factory()->create(['village_id' => $this->village->id])
            ->bills()->create(['village_id' => $this->village->id, 'year' => 2026, 'amount' => 100000]);
        $item = $batch->items()->create([
            'pbb_bill_id' => $bill->id, 'amount_claimed' => 100000, 'status' => 'pending',
        ]);

        $this->actingAs($this->staffWithRole('bendahara_pbb'))
            ->get('/admin/pbb/pbb-deposit-items/' . $item->id)
            ->assertOk();
    }

    public function test_dashboard_with_pbb_widget_renders(): void
    {
        // Widget statistik PBB terdaftar & dashboard di-bootstrap tanpa error (data stat
        // dirender via Livewire/Schemas, jadi cukup assert halaman beranda OK).
        PbbBill::factory()->create([
            'village_id' => $this->village->id, 'year' => (int) date('Y'), 'amount' => 200000, 'status' => 'lunas',
        ]);

        $this->actingAs($this->staffWithRole('bendahara_pbb'))
            ->get('/admin')
            ->assertOk();
    }

    public function test_warga_without_staff_role_cannot_open_panel(): void
    {
        $warga = User::factory()->create(['village_id' => $this->village->id]);
        $warga->assignRole('warga');

        $this->actingAs($warga)->get('/admin/pbb/pbb-objects')->assertForbidden();
    }

    public function test_kolektor_is_not_panel_staff(): void
    {
        // RT/kolektor bekerja lewat aplikasi, bukan panel — tak boleh masuk admin.
        $kolektor = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $kolektor->assignRole('kolektor');

        $this->actingAs($kolektor)->get('/admin/pbb/pbb-deposit-batches')->assertForbidden();
    }
}
