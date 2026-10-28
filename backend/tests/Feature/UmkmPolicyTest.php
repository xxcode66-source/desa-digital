<?php
// Test RBAC UMKM (PEMBANGUNAN.md 3 & 4.4): pendaftaran butuh akun terverifikasi, toko/produk
// hanya tampil publik saat aktif & tak disembunyikan (pemilik & petugas desa tetap bisa), produk
// mengikuti kepemilikan toko, dan moderasi laporan dikhususkan bagi petugas desa yang sama.

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Shop;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UmkmPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();
        $this->village = Village::factory()->create();
    }

    private function staff(string $role, ?Village $v = null): User
    {
        $u = User::factory()->create(['village_id' => ($v ?? $this->village)->id]);
        $u->assignRole($role);

        return $u->fresh();
    }

    public function test_shop_registration_requires_verified_account(): void
    {
        $pending = User::factory()->create(['village_id' => $this->village->id]);
        $verified = User::factory()->verified()->create(['village_id' => $this->village->id]);

        $this->assertFalse(Gate::forUser($pending)->allows('create', Shop::class));
        $this->assertTrue(Gate::forUser($verified)->allows('create', Shop::class));
    }

    public function test_public_view_only_when_active_and_not_hidden(): void
    {
        $other = User::factory()->verified()->create(['village_id' => $this->village->id]);

        $active = Shop::factory()->active()->create(['village_id' => $this->village->id]);
        $hidden = Shop::factory()->active()->hidden()->create(['village_id' => $this->village->id]);
        $pending = Shop::factory()->create(['village_id' => $this->village->id]);

        $this->assertTrue(Gate::forUser($other)->allows('view', $active));
        $this->assertFalse(Gate::forUser($other)->allows('view', $hidden));
        $this->assertFalse(Gate::forUser($other)->allows('view', $pending));

        // Pemilik selalu bisa melihat tokonya sendiri, apapun statusnya.
        $this->assertTrue(Gate::forUser($hidden->owner)->allows('view', $hidden));
        // Petugas desa bisa meninjau walau disembunyikan.
        $this->assertTrue(Gate::forUser($this->staff('operator'))->allows('view', $hidden));
        // Petugas desa lain tidak.
        $this->assertFalse(Gate::forUser($this->staff('operator', Village::factory()->create()))->allows('view', $hidden));
    }

    public function test_shop_update_ownership_and_review_roles(): void
    {
        $shop = Shop::factory()->active()->create(['village_id' => $this->village->id]);

        $this->assertTrue(Gate::forUser($shop->owner)->allows('update', $shop));
        $this->assertFalse(Gate::forUser(User::factory()->verified()->create(['village_id' => $this->village->id]))->allows('update', $shop));

        $this->assertTrue(Gate::forUser($this->staff('operator'))->allows('review', $shop));
        $this->assertTrue(Gate::forUser($this->staff('kades'))->allows('review', $shop));
        $this->assertFalse(Gate::forUser($shop->owner)->allows('review', $shop), 'Pemilik tidak meninjau tokonya sendiri.');
        $this->assertFalse(Gate::forUser($this->staff('operator', Village::factory()->create()))->allows('review', $shop));
    }

    public function test_product_view_follows_shop_and_create_needs_verified(): void
    {
        $shop = Shop::factory()->active()->create(['village_id' => $this->village->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'village_id' => $shop->village_id]);

        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $this->assertTrue(Gate::forUser($warga)->allows('view', $product));

        $shop->update(['is_hidden' => true]);
        $product->unsetRelation('shop');
        $this->assertFalse(Gate::forUser($warga->fresh())->allows('view', $product->fresh()));
        $this->assertTrue(Gate::forUser($shop->owner)->allows('view', $product->fresh()));

        // Update produk: hanya pemilik toko.
        $this->assertTrue(Gate::forUser($shop->owner)->allows('update', $product));
        $this->assertFalse(Gate::forUser($warga)->allows('update', $product));
    }

    public function test_report_create_verified_and_handle_by_reviewer(): void
    {
        $shop = Shop::factory()->active()->create(['village_id' => $this->village->id]);
        $report = ProductReport::create([
            'village_id' => $this->village->id, 'shop_id' => $shop->id, 'reason' => 'Mencurigakan.',
        ]);

        $this->assertTrue(Gate::forUser(User::factory()->verified()->create(['village_id' => $this->village->id]))->allows('create', ProductReport::class));
        $this->assertFalse(Gate::forUser(User::factory()->create(['village_id' => $this->village->id]))->allows('create', ProductReport::class));

        $this->assertTrue(Gate::forUser($this->staff('operator'))->allows('handle', $report));
        $this->assertFalse(Gate::forUser($this->staff('operator', Village::factory()->create()))->allows('handle', $report));
    }

    public function test_product_policy_survives_partially_loaded_shop(): void
    {
        // Regresi crash: kolom relasi Filament 'shop.name' meng-eager-load shop hanya
        // (id,name) -> Shop->status null. ProductPolicy::view harus tetap jalan (tanpa
        // TypeError) dan petugas desa tetap boleh melihat produknya.
        $operator = $this->staff('operator');
        $shop = Shop::factory()->active()->create(['village_id' => $this->village->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'village_id' => $this->village->id]);

        $product->setRelation('shop', Shop::select('id', 'name')->findOrFail($shop->id));

        $this->assertTrue(Gate::forUser($operator)->allows('view', $product));
    }
}
