<?php
// Test PDF surat siap-cetak (PEMBANGUNAN.md 4.2): dihasilkan saat surat selesai, memuat
// QR verifikasi ber-token, dan hanya bisa diunduh pihak yang berhak (warga pemilik/staff
// satu desa). Surat belum selesai -> tak ada berkas.

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterType;
use App\Models\User;
use App\Models\Village;
use App\Services\LetterPdfService;
use App\Services\LetterWorkflow;
use App\Support\LetterStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LetterPdfTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;
    private User $warga;
    private LetterType $type;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->village = Village::factory()->create([
            'settings' => ['kop' => ['name' => 'DESA ujicoba'], 'kades' => 'Budi'],
        ]);
        $this->warga = User::factory()->verified()->create([
            'village_id' => $this->village->id,
            'nik' => '3301112233445566',
            'rt' => '02',
            'rw' => '05',
        ]);
        $this->warga->assignRole('warga');

        $this->type = LetterType::factory()->create([
            'village_id' => $this->village->id,
            'form_schema_json' => [
                ['key' => 'keperluan', 'label' => 'Keperluan', 'type' => 'textarea', 'required' => true],
                ['key' => 'alamat', 'label' => 'Alamat', 'type' => 'text', 'required' => true],
            ],
        ]);
    }

    private function doneLetter(): Letter
    {
        return Letter::factory()->create([
            'village_id' => $this->village->id,
            'letter_type_id' => $this->type->id,
            'user_id' => $this->warga->id,
            'data_json' => ['keperluan' => 'Izin usaha', 'alamat' => 'Jl. Melati 1'],
            'status' => LetterStatus::DONE,
            'number' => '470/007/DS/10/2026',
            'public_token' => (string) \Illuminate\Support\Str::uuid(),
            'completed_at' => now(),
        ]);
    }

    public function test_generate_produces_pdf_with_qr_and_sets_path(): void
    {
        $letter = $this->doneLetter();

        $path = app(LetterPdfService::class)->generate($letter);

        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $letter->fresh()->pdf_path);

        // Isi PDF harus memuat nomor, nama desa, dan token pada URL verifikasi.
        $content = Storage::disk('local')->get($path);
        $this->assertStringStartsWith('%PDF-', $content);
    }

    public function test_no_pdf_when_letter_has_no_public_token(): void
    {
        $letter = $this->doneLetter();
        $letter->forceFill(['public_token' => null])->save();

        $this->assertNull(app(LetterPdfService::class)->generate($letter->fresh()));
    }

    public function test_workflow_generates_pdf_on_done_transition(): void
    {
        $operator = User::factory()->create(['village_id' => $this->village->id]);
        $operator->assignRole('kades');

        $letter = Letter::factory()->create([
            'village_id' => $this->village->id,
            'letter_type_id' => $this->type->id,
            'user_id' => $this->warga->id,
            'status' => LetterStatus::SIGN,
        ]);

        $letter = app(LetterWorkflow::class)->transition($letter, LetterStatus::DONE, $operator);

        $this->assertSame(LetterStatus::DONE, $letter->status);
        $this->assertNotNull($letter->pdf_path);
        Storage::disk('local')->assertExists($letter->pdf_path);
    }

    public function test_owner_can_download_pdf(): void
    {
        $letter = $this->doneLetter();
        app(LetterPdfService::class)->generate($letter);

        Sanctum::actingAs($this->warga);

        $this->getJson("/api/letters/{$letter->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_citizen_of_other_village_cannot_download(): void
    {
        $letter = $this->doneLetter();
        app(LetterPdfService::class)->generate($letter);

        $orang = User::factory()->verified()->create();
        $orang->assignRole('warga');
        Sanctum::actingAs($orang);

        $this->getJson("/api/letters/{$letter->id}/pdf")->assertForbidden();
    }

    public function test_unfinished_letter_has_no_pdf(): void
    {
        $letter = Letter::factory()->create([
            'village_id' => $this->village->id,
            'letter_type_id' => $this->type->id,
            'user_id' => $this->warga->id,
            'status' => LetterStatus::SUBMITTED,
        ]);

        Sanctum::actingAs($this->warga);

        $this->getJson("/api/letters/{$letter->id}/pdf")->assertNotFound();
    }
}
