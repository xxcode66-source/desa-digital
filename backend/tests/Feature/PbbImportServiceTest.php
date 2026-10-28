<?php
// Test import database PBB dari CSV (PEMBANGUNAN.md 4.3): upsert idempoten berdasar
// (desa, NOP) & (objek, tahun), baris rusak dilewati (status partial), dan re-import
// TIDAK menurunkan status tagihan yang sudah diproses — hanya nominal yang diperbarui.

namespace Tests\Feature;

use App\Models\PbbBill;
use App\Models\PbbObject;
use App\Models\User;
use App\Models\Village;
use App\Services\PbbImportService;
use App\Support\PbbStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PbbImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private PbbImportService $service;
    private Village $village;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PbbImportService::class);
        $this->village = Village::factory()->create();
        $this->operator = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $this->operator->assignRole('operator');
    }

    private function csv(string $body): string
    {
        return "nop,tahun,jumlah,denda,nama_wp,alamat,rt,rw\n" . $body;
    }

    public function test_valid_rows_create_objects_and_bills(): void
    {
        $csv = $this->csv(
            "08.314.111.100-1000.1,2026,150000,0,Budi Santoso,Jl Melati 1,03,01\n"
            . "08.314.111.100-1000.1,2025,140000,0,Budi Santoso,Jl Melati 1,03,01\n"
        );

        $log = $this->service->importFromString($this->village->id, $this->operator, $csv, 'pbb.csv');

        // NOP disimpan sebagai 18 digit murni (non-digit dibuang).
        $object = PbbObject::first();
        $this->assertSame('0831411110010001', $object->nop);
        $this->assertSame('Budi Santoso', $object->wp_name);
        $this->assertSame(2, $log->rows_created);
        $this->assertSame(0, $log->rows_skipped);
        $this->assertSame('success', $log->status);
        $this->assertSame(2, PbbBill::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'pbb.import', 'subject_id' => $log->id]);
    }

    public function test_invalid_rows_are_skipped_and_marked_partial(): void
    {
        $csv = $this->csv(
            "08.314.111.100-1000.2,2026,150000,0,Andi,Jl Mawar,01,01\n"
            . ",2026,150000,0,Tanpa Nop,,,,\n"                       // NOP kosong -> skip
            . "08.314.111.100-1000.3,1999,150000,0,Tahun Salah,,,,\n" // tahun < 2000 -> skip
            . "08.314.111.100-1000.4,2026,0,0,Nihil Nominal,,,,\n"    // nominal <= 0 -> skip
        );

        $log = $this->service->importFromString($this->village->id, $this->operator, $csv, 'campur.csv');

        $this->assertSame(4, $log->rows_total);
        $this->assertSame(1, $log->rows_created);
        $this->assertSame(3, $log->rows_skipped);
        $this->assertSame('partial', $log->status);
        $this->assertNotEmpty($log->message);
    }

    public function test_reimport_updates_amount_but_preserves_processed_status(): void
    {
        $nop = '08.314.111.100-2000.1';
        $csv = $this->csv("$nop,2026,150000,0,Budi,Jl Melati,03,01\n");

        $this->service->importFromString($this->village->id, $this->operator, $csv, 'v1.csv');
        $bill = PbbBill::first();
        $this->assertSame(PbbStatus::BILL_UNPAID, $bill->status);

        // Petugas menandai lunas, lalu admin re-import dengan nominal dikoreksi.
        $bill->forceFill(['status' => PbbStatus::BILL_PAID])->save();
        $csvV2 = $this->csv("$nop,2026,175000,5000,Budi,Jl Melati,03,01\n");
        $log = $this->service->importFromString($this->village->id, $this->operator, $csvV2, 'v2.csv');

        $bill->refresh();
        $this->assertSame(1, $log->rows_updated);
        $this->assertSame(0, $log->rows_created);
        $this->assertSame(1, PbbObject::count());                 // objek di-upsert, tidak duplikat
        $this->assertSame(1, PbbBill::count());
        $this->assertSame(175000.0, (float) $bill->amount);       // nominal diperbarui
        $this->assertSame(PbbStatus::BILL_PAID, $bill->status);   // status dipertahankan
    }

    public function test_completely_empty_file_marks_failed(): void
    {
        $log = $this->service->importFromString($this->village->id, $this->operator, "nop,tahun,jumlah\n", 'kosong.csv');

        $this->assertSame(0, $log->rows_total);
        $this->assertSame('failed', $log->status);
        $this->assertSame(0, PbbObject::count());
    }
}
