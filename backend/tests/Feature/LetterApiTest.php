<?php
// Test API surat warga Fase 2 (PEMBANGUNAN.md 4.2): jenis dinamis, pengajuan + lampiran,
// penolakan akun belum terverifikasi, perbaikan per-field, dan verifikasi publik.

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterType;
use App\Models\User;
use App\Models\Village;
use App\Support\LetterStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LetterApiTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;
    private User $warga;
    private LetterType $typeRt;
    private LetterType $typeLangsung;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->village = Village::factory()->create();
        $this->warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $this->warga->assignRole('warga');

        $this->typeRt = LetterType::factory()->create(['needs_rt' => true]);
        $this->typeLangsung = LetterType::factory()->create(['needs_rt' => false]);

        Sanctum::actingAs($this->warga);
    }

    /** Schema factory: keperluan (wajib) + alamat (wajib). */
    private function payload(LetterType $type, array $overrides = []): array
    {
        return [
            'letter_type_id' => $type->id,
            'data' => ['keperluan' => 'Pengajuan izin usaha', 'alamat' => 'Jl. Melati 1'],
            'attachments' => [UploadedFile::fake()->image('ktp.jpg')],
        ] + $overrides;
    }

    public function test_types_lists_active_builtin_and_village_types(): void
    {
        $milikDesa = LetterType::factory()->create(['village_id' => $this->village->id]);
        LetterType::factory()->create(['village_id' => $this->village->id, 'is_active' => false]);

        $response = $this->getJson('/api/letters/types')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->typeRt->id));
        $this->assertTrue($ids->contains($milikDesa->id));
    }

    public function test_warga_can_submit_letter_with_rt_route(): void
    {
        $response = $this->postJson('/api/letters', $this->payload($this->typeRt))
            ->assertStatus(201);

        $id = $response->json('data.id');
        $letter = Letter::find($id);

        $this->assertSame(LetterStatus::SUBMITTED, $letter->status);
        $this->assertSame($this->village->id, $letter->village_id);
        $this->assertCount(1, $letter->attachments);
        $this->assertCount(1, $letter->statusLogs()->where('to_status', LetterStatus::SUBMITTED)->get());
        $this->assertDatabaseHas('audit_logs', ['action' => 'letter.submitted', 'subject_id' => $id]);
        $response->assertJsonPath('data.status_label', LetterStatus::label(LetterStatus::SUBMITTED));
    }

    public function test_letter_without_rt_requirement_goes_straight_to_operator(): void
    {
        $this->postJson('/api/letters', $this->payload($this->typeLangsung))
            ->assertStatus(201)
            ->assertJsonPath('data.status', LetterStatus::OPERATOR);
    }

    public function test_submit_requires_all_mandatory_schema_fields(): void
    {
        $this->postJson('/api/letters', [
            'letter_type_id' => $this->typeRt->id,
            'data' => ['keperluan' => 'Tanpa alamat'],
            'attachments' => [UploadedFile::fake()->image('ktp.jpg')],
        ])->assertStatus(422)->assertJsonValidationErrors('data.alamat');
    }

    public function test_submit_requires_at_least_one_attachment(): void
    {
        $this->postJson('/api/letters', [
            'letter_type_id' => $this->typeRt->id,
            'data' => ['keperluan' => 'a', 'alamat' => 'b'],
        ])->assertStatus(422)->assertJsonValidationErrors('attachments');
    }

    public function test_unverified_warga_cannot_submit(): void
    {
        $pending = User::factory()->create(['village_id' => $this->village->id]);
        $pending->assignRole('warga');
        Sanctum::actingAs($pending);

        $this->postJson('/api/letters', $this->payload($this->typeRt))->assertStatus(403);
        $this->assertSame(0, Letter::count());
    }

    public function test_index_and_show_are_scoped_to_owner(): void
    {
        $milikku = Letter::factory()->create([
            'village_id' => $this->village->id, 'user_id' => $this->warga->id,
            'letter_type_id' => $this->typeRt->id,
        ]);
        $punTetangga = Letter::factory()->create([
            'village_id' => $this->village->id,
            'letter_type_id' => $this->typeRt->id,
        ]);

        $this->getJson('/api/letters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $milikku->id);

        $this->getJson("/api/letters/{$milikku->id}")->assertOk();
        $this->getJson("/api/letters/{$punTetangga->id}")->assertForbidden();
    }

    public function test_owner_can_repair_and_resubmit(): void
    {
        $letter = Letter::factory()->create([
            'village_id' => $this->village->id, 'user_id' => $this->warga->id,
            'letter_type_id' => $this->typeRt->id, 'status' => LetterStatus::REPAIR,
        ]);
        // Tandai field rusak lewat log perbaikan (hasil aksi staff di panel).
        $letter->statusLogs()->create([
            'from_status' => LetterStatus::OPERATOR,
            'to_status' => LetterStatus::REPAIR,
            'note' => 'Alamat kurang lengkap',
            'flagged_fields_json' => ['alamat'],
            'actor_id' => $this->warga->id,
        ]);

        $this->assertContains('alamat', $letter->fresh()->flaggedFields());

        $this->putJson("/api/letters/{$letter->id}", [
            'data' => ['keperluan' => 'Sudah diperbaiki', 'alamat' => 'Jl. Melati 1 RT 3'],
            'attachments' => [UploadedFile::fake()->image('ktp-baru.jpg')],
        ])->assertOk()->assertJsonPath('data.status', LetterStatus::SUBMITTED);

        $this->assertDatabaseHas('letter_status_logs', [
            'letter_id' => $letter->id, 'to_status' => LetterStatus::SUBMITTED,
        ]);
    }

    public function test_letter_not_in_repair_cannot_be_updated_by_owner(): void
    {
        $letter = Letter::factory()->create([
            'village_id' => $this->village->id, 'user_id' => $this->warga->id,
            'status' => LetterStatus::OPERATOR,
        ]);

        $this->putJson("/api/letters/{$letter->id}", [
            'data' => ['keperluan' => 'a', 'alamat' => 'b'],
        ])->assertForbidden();
    }

    public function test_public_verification_only_for_done_letters(): void
    {
        $selesai = Letter::factory()->done()->create([
            'village_id' => $this->village->id,
            'letter_type_id' => $this->typeRt->id,
        ]);

        $this->getJson("/api/verify-letter/{$selesai->public_token}")
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('number', $selesai->number);

        $belum = Letter::factory()->create(['village_id' => $this->village->id, 'public_token' => (string) str_repeat('a', 32)]);

        $this->getJson("/api/verify-letter/{$belum->public_token}")->assertNotFound()->assertJsonPath('valid', false);
        $this->getJson('/api/verify-letter/token-tidak-dikenal')->assertNotFound();
    }

    public function test_attachment_download_requires_view_permission(): void
    {
        $letter = Letter::factory()->create([
            'village_id' => $this->village->id, 'user_id' => $this->warga->id,
        ]);
        $attachment = $letter->attachments()->create([
            'user_id' => $this->warga->id, 'disk' => 'local',
            'path' => 'letters/seed.txt', 'original_name' => 'seed.txt',
        ]);
        Storage::disk('local')->put('letters/seed.txt', 'isi');

        $this->getJson("/api/letters/{$letter->id}/attachments/{$attachment->id}")
            ->assertOk();

        // User lain dari desa yang sama tapi bukan staff & bukan pemilik => 403.
        Sanctum::actingAs(User::factory()->verified()->create(['village_id' => $this->village->id]));
        $this->getJson("/api/letters/{$letter->id}/attachments/{$attachment->id}")->assertForbidden();
    }
}
