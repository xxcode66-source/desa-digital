<?php
// Test API PBB Fase 4 (PEMBANGUNAN.md 4.3): cek NOP publik menyamar­kan nama WP + dibatasi
// rate limit, pengajuan klaim (butuh foto + akun terverifikasi), "NOP saya" hanya memuat
// objek berklaim approved, serta alur kolektor melihat tagihan & mengajukan setoran.

namespace Tests\Feature;

use App\Models\PbbBill;
use App\Models\PbbClaim;
use App\Models\PbbObject;
use App\Models\User;
use App\Models\Village;
use App\Support\PbbStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PbbApiTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->village = Village::factory()->create();
    }

    private function objectWithArrears(string $wpName = 'Budi Santoso'): PbbObject
    {
        $object = PbbObject::factory()->create([
            'village_id' => $this->village->id,
            'nop' => '083141111000000101',
            'wp_name' => $wpName,
        ]);
        $object->bills()->create([
            'village_id' => $this->village->id, 'year' => 2026, 'amount' => 150000, 'penalty' => 5000,
            'status' => PbbStatus::BILL_UNPAID,
        ]);

        return $object;
    }

    // ---- cek NOP (publik) ----

    public function test_cek_nop_masks_wp_name_and_flags_disclaimer(): void
    {
        $this->objectWithArrears('Budi Santoso');

        // NOP boleh dikirim berformat; server menormalkan ke digit.
        $response = $this->getJson('/api/pbb/cek-nop?nop=0831-4111-1000-0001-01&village_id=' . $this->village->id)
            ->assertOk();

        $response->assertJsonPath('data.nop', '083141111000000101');
        $response->assertJsonPath('data.wp_name_masked', 'B*** S******');
        $this->assertStringNotContainsString('Budi', $response->json('data.wp_name_masked'));
        $this->assertNotEmpty($response->json('data.disclaimer'));
        $this->assertSame(1, $response->json('data.arrears_count'));
    }

    public function test_cek_nop_unknown_returns_404(): void
    {
        $this->getJson('/api/pbb/cek-nop?nop=999999999999999999&village_id=' . $this->village->id)
            ->assertNotFound();
    }

    public function test_cek_nop_is_rate_limited(): void
    {
        $this->objectWithArrears();
        $limit = (int) config('desa.throttle.pbb_nop_check');
        $url = '/api/pbb/cek-nop?nop=083141111000000101&village_id=' . $this->village->id;

        for ($i = 0; $i < $limit; $i++) {
            $this->getJson($url)->assertOk();
        }
        $this->getJson($url)->assertStatus(429);
    }

    // ---- klaim kepemilikan ----

    public function test_verified_warga_can_submit_claim_with_evidence(): void
    {
        $object = $this->objectWithArrears();
        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        Sanctum::actingAs($warga);

        $this->post('/api/pbb/claims', [
            'nop' => $object->nop,
            'note' => 'Ini rumah saya.',
            'evidence' => UploadedFile::fake()->image('sppt.jpg'),
        ])->assertStatus(201)->assertJsonPath('data.status', PbbStatus::CLAIM_PENDING);

        $this->assertDatabaseHas('audit_logs', ['action' => 'pbb.claim.submitted']);
        $this->assertSame(1, PbbClaim::where('user_id', $warga->id)->count());
    }

    public function test_claim_requires_evidence_image(): void
    {
        $object = $this->objectWithArrears();
        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        Sanctum::actingAs($warga);

        $this->postJson('/api/pbb/claims', ['nop' => $object->nop])
            ->assertStatus(422)->assertJsonValidationErrors('evidence');

        $this->postJson('/api/pbb/claims', [
            'nop' => $object->nop,
            'evidence' => UploadedFile::fake()->create('dokumen.pdf', 20, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('evidence');
    }

    public function test_unverified_warga_cannot_claim(): void
    {
        $object = $this->objectWithArrears();
        $pending = User::factory()->create(['village_id' => $this->village->id]);
        Sanctum::actingAs($pending);

        $this->post('/api/pbb/claims', [
            'nop' => $object->nop,
            'evidence' => UploadedFile::fake()->image('sppt.jpg'),
        ])->assertForbidden();
    }

    public function test_duplicate_pending_claim_is_rejected(): void
    {
        $object = $this->objectWithArrears();
        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        Sanctum::actingAs($warga);

        $payload = fn () => ['nop' => $object->nop, 'evidence' => UploadedFile::fake()->image('sppt.jpg')];
        $this->post('/api/pbb/claims', $payload())->assertStatus(201);
        $this->post('/api/pbb/claims', $payload())->assertStatus(422);
    }

    public function test_my_objects_only_shows_approved_claims(): void
    {
        $object = $this->objectWithArrears();
        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);

        // Klaim pending dulu -> belum tampil.
        PbbClaim::factory()->create([
            'village_id' => $this->village->id, 'user_id' => $warga->id, 'pbb_object_id' => $object->id,
            'status' => PbbStatus::CLAIM_PENDING,
        ]);
        Sanctum::actingAs($warga);
        $this->getJson('/api/my-pbb-objects')->assertOk()->assertJsonCount(0, 'data');

        // Setelah disetujui -> muncul dengan ringkasan tunggakan.
        $object2 = PbbObject::factory()->create([
            'village_id' => $this->village->id, 'nop' => '083141111000000202',
        ]);
        $object2->bills()->create(['village_id' => $this->village->id, 'year' => 2026, 'amount' => 100000, 'penalty' => 0]);
        PbbClaim::factory()->approved()->create([
            'village_id' => $this->village->id, 'user_id' => $warga->id, 'pbb_object_id' => $object2->id,
        ]);

        $this->getJson('/api/my-pbb-objects')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nop', '083141111000000202')
            ->assertJsonPath('data.0.arrears_count', 1);
    }

    public function test_owner_can_download_claim_evidence(): void
    {
        $object = $this->objectWithArrears();
        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        Sanctum::actingAs($warga);

        $id = $this->post('/api/pbb/claims', [
            'nop' => $object->nop,
            'evidence' => UploadedFile::fake()->image('sppt.jpg', 600, 400),
        ])->assertStatus(201)->json('data.id');

        $this->getJson("/api/pbb/claims/{$id}/evidence")->assertOk();

        // Warga lain tak boleh mengunduh bukti orang lain.
        Sanctum::actingAs(User::factory()->verified()->create(['village_id' => $this->village->id]));
        $this->getJson("/api/pbb/claims/{$id}/evidence")->assertForbidden();
    }

    // ---- setoran (kolektor) ----

    public function test_collector_can_list_bills_and_submit_deposit(): void
    {
        $collector = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $collector->assignRole('kolektor', '03');
        $object = PbbObject::factory()->create(['village_id' => $this->village->id, 'rt' => '03']);
        $bill = $object->bills()->create([
            'village_id' => $this->village->id, 'year' => 2026, 'amount' => 120000, 'penalty' => 0,
            'status' => PbbStatus::BILL_UNPAID,
        ]);

        Sanctum::actingAs($collector);

        $this->getJson('/api/pbb/bills')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson('/api/pbb/deposits', [
            'title' => 'Setoran RT 03',
            'bills' => [['pbb_bill_id' => $bill->id, 'amount_claimed' => 120000]],
        ])->assertStatus(201)
            ->assertJsonPath('data.status', PbbStatus::DEPOSIT_PENDING)
            ->assertJsonPath('data.item_count', 1);

        $this->assertSame(PbbStatus::BILL_PENDING, $bill->fresh()->status);
        $this->getJson('/api/my-pbb-deposits')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_non_collector_cannot_submit_deposit(): void
    {
        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $warga->assignRole('warga');
        Sanctum::actingAs($warga);

        $this->postJson('/api/pbb/deposits', [
            'title' => 'x', 'bills' => [['pbb_bill_id' => 1]],
        ])->assertForbidden();
    }

    public function test_deposit_requires_at_least_one_bill(): void
    {
        $collector = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $collector->assignRole('kolektor', '03');
        Sanctum::actingAs($collector);

        $this->postJson('/api/pbb/deposits', ['title' => 'x', 'bills' => []])
            ->assertStatus(422)->assertJsonValidationErrors('bills');
    }
}
