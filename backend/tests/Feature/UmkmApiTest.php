<?php
// Test API UMKM Fase 5 (PEMBANGUNAN.md 4.4): jelajah marketplace (hanya toko aktif desa sendiri),
// detail toko + statistik "dilihat", tombol Chat WA (catat klik + tautan), kelola toko & produk
// (daftar/perbarui/ajukan ulang, CRUD produk + foto), lapor moderasi, dan unduh foto privat.

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\Shop;
use App\Models\User;
use App\Models\Village;
use App\Support\UmkmStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UmkmApiTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;
    private User $warga;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->village = Village::factory()->create();
        $this->warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $this->warga->assignRole('warga');
        $this->seller = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $this->seller->assignRole('umkm');
    }

    public function test_categories_come_from_config(): void
    {
        Sanctum::actingAs($this->warga);
        $this->getJson('/api/umkm/categories')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'makanan')
            ->assertJsonStructure(['data' => [['code', 'label']]]);
    }

    public function test_browse_lists_only_active_visible_shops_in_own_village(): void
    {
        $aktif = Shop::factory()->active()->create(['village_id' => $this->village->id, 'name' => 'Warung Aktif']);
        Shop::factory()->create(['village_id' => $this->village->id]);                       // pending
        Shop::factory()->active()->hidden()->create(['village_id' => $this->village->id]);   // disembunyikan
        Shop::factory()->active()->create(['village_id' => Village::factory()->create()->id]); // desa lain

        Sanctum::actingAs($this->warga);
        $response = $this->getJson('/api/umkm/shops')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($aktif->id));
        $this->assertSame(1, $ids->count(), 'Hanya 1 toko tampil (aktif, desa sendiri, tak tersembunyi).');
    }

    public function test_shop_detail_records_view_and_exposes_wa_link(): void
    {
        $shop = Shop::factory()->active()->create(['village_id' => $this->village->id, 'wa_number' => '081234567890']);
        Product::factory()->create(['shop_id' => $shop->id, 'village_id' => $shop->village_id]);

        Sanctum::actingAs($this->warga);
        $this->getJson("/api/umkm/shops/{$shop->id}")
            ->assertOk()
            ->assertJsonPath('data.products_count', 1)
            ->assertJsonStructure(['data' => ['wa_url', 'products']]);

        $this->assertDatabaseHas('shop_stats', ['shop_id' => $shop->id, 'views_count' => 1]);
    }

    public function test_pending_shop_hidden_from_public_browsing_but_detail_forbidden_for_others(): void
    {
        $pending = Shop::factory()->create(['village_id' => $this->village->id]);

        Sanctum::actingAs($this->warga);
        $this->getJson('/api/umkm/shops')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/umkm/shops/{$pending->id}")->assertForbidden();
    }

    public function test_wa_click_returns_link_and_records_stat(): void
    {
        $shop = Shop::factory()->active()->create(['village_id' => $this->village->id, 'wa_number' => '081234567890']);

        Sanctum::actingAs($this->warga);
        $response = $this->getJson("/api/umkm/shops/{$shop->id}/wa")->assertOk();

        $this->assertStringContainsString('wa.me/6281234567890', $response->json('data.wa_url'));
        $this->assertDatabaseHas('shop_stats', ['shop_id' => $shop->id, 'wa_clicks_count' => 1]);
    }

    public function test_seller_can_register_shop_then_it_is_pending(): void
    {
        Sanctum::actingAs($this->seller);

        $this->postJson('/api/umkm/my-shop', [
            'name' => 'Toko Maju',
            'category' => 'makanan',
            'wa_number' => '0812',
            'photo' => UploadedFile::fake()->image('toko.jpg'),
        ])->assertStatus(201)->assertJsonPath('data.status', UmkmStatus::SHOP_PENDING);

        $shop = Shop::firstWhere('user_id', $this->seller->id);
        $this->assertNotNull($shop);
        $this->assertSame($this->village->id, $shop->village_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'umkm.shop.created', 'subject_id' => $shop->id]);

        $this->getJson('/api/umkm/my-shop')->assertOk()->assertJsonPath('data.name', 'Toko Maju');
    }

    public function test_unverified_user_cannot_register_shop(): void
    {
        $pending = User::factory()->create(['village_id' => $this->village->id]);
        Sanctum::actingAs($pending);

        $this->postJson('/api/umkm/my-shop', ['name' => 'X', 'category' => 'makanan', 'wa_number' => '0812'])
            ->assertForbidden();
    }

    public function test_editing_rejected_shop_resubmits_it(): void
    {
        $shop = Shop::factory()->rejected()->create(['village_id' => $this->village->id, 'user_id' => $this->seller->id]);

        Sanctum::actingAs($this->seller);
        $this->putJson('/api/umkm/my-shop', [
            'name' => 'Toko Diperbaiki',
            'category' => 'makanan',
            'wa_number' => '0812',
        ])->assertOk()->assertJsonPath('data.status', UmkmStatus::SHOP_PENDING);
    }

    public function test_seller_can_crud_products(): void
    {
        $shop = Shop::factory()->active()->create(['village_id' => $this->village->id, 'user_id' => $this->seller->id]);
        Sanctum::actingAs($this->seller);

        $created = $this->postJson('/api/umkm/products', [
            'name' => 'Keripik',
            'price' => 5000,
            'photos' => [UploadedFile::fake()->image('p1.jpg')],
        ])->assertStatus(201)->assertJsonPath('data.price_label', 'Rp 5.000');

        $id = $created->json('data.id');
        $this->assertDatabaseHas('products', ['id' => $id, 'shop_id' => $shop->id, 'village_id' => $shop->village_id]);

        $this->putJson("/api/umkm/products/{$id}", [
            'name' => 'Keripik Upgrade',
            'price' => 6000,
            'is_available' => false,
            'photos' => [UploadedFile::fake()->image('p2.jpg')],
        ])->assertOk()->assertJsonPath('data.is_available', false);

        $this->deleteJson("/api/umkm/products/{$id}")->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $id]);
    }

    public function test_product_requires_existing_shop(): void
    {
        $lonely = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $lonely->assignRole('umkm');
        Sanctum::actingAs($lonely);

        $this->postJson('/api/umkm/products', ['name' => 'A', 'price' => 100])->assertStatus(422);
    }

    public function test_warga_can_report_shop_for_moderation(): void
    {
        $shop = Shop::factory()->active()->create(['village_id' => $this->village->id]);
        Sanctum::actingAs($this->warga);

        $this->postJson("/api/umkm/shops/{$shop->id}/reports", ['reason' => 'Curiga menipu.'])
            ->assertStatus(201);

        $this->assertDatabaseHas('product_reports', [
            'shop_id' => $shop->id, 'reporter_id' => $this->warga->id, 'status' => UmkmStatus::REPORT_NEW,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'umkm.report.submitted']);
    }

    public function test_report_requires_reason(): void
    {
        $shop = Shop::factory()->active()->create(['village_id' => $this->village->id]);
        Sanctum::actingAs($this->warga);

        $this->postJson("/api/umkm/shops/{$shop->id}/reports", [])->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    public function test_product_photo_download_requires_view(): void
    {
        $shop = Shop::factory()->active()->create(['village_id' => $this->village->id, 'user_id' => $this->seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'village_id' => $shop->village_id]);
        $photo = ProductPhoto::create(['product_id' => $product->id, 'disk' => 'local', 'path' => 'umkm/products/seed.jpg', 'original_name' => 'seed.jpg']);
        Storage::disk('local')->put('umkm/products/seed.jpg', 'x');

        Sanctum::actingAs($this->warga);
        $this->get("/api/umkm/products/{$product->id}/photos/{$photo->id}")->assertOk();

        // Setelah toko disembunyikan, warga tak boleh mengunduh.
        $shop->update(['status' => UmkmStatus::SHOP_SUSPENDED]);
        $this->get("/api/umkm/products/{$product->id}/photos/{$photo->id}")->assertForbidden();
    }
}
