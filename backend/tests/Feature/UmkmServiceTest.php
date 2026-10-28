<?php
// Test logika bisnis UMKM (PEMBANGUNAN.md 4.4): verifikasi toko dijaga mesin status & audit,
// penolakan wajib alasan, ajukan ulang mereset catatan review, moderasi laporan bisa membekukan
// toko, statistik penjual bertambah atomik, dan tautan WA dinormalisasi ke format internasional.

namespace Tests\Feature;

use App\Models\ProductReport;
use App\Models\Shop;
use App\Models\User;
use App\Models\Village;
use App\Services\UmkmService;
use App\Support\UmkmStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UmkmServiceTest extends TestCase
{
    use RefreshDatabase;

    private UmkmService $service;
    private Village $village;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UmkmService::class);
        $this->village = Village::factory()->create();
        $this->operator = User::factory()->create(['village_id' => $this->village->id]);
        $this->operator->assignRole('operator');
        $this->actingAs($this->operator);
    }

    private function shop(?string $wa = '081234567890'): Shop
    {
        return Shop::factory()->create([
            'village_id' => $this->village->id,
            'wa_number' => $wa,
        ]);
    }

    public function test_approve_activates_pending_shop_with_audit(): void
    {
        $shop = $this->service->approve($this->shop(), $this->operator, 'Dokumen lengkap.');

        $this->assertSame(UmkmStatus::SHOP_ACTIVE, $shop->status);
        $this->assertSame($this->operator->id, $shop->reviewed_by);
        $this->assertNotNull($shop->reviewed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'umkm.shop.approved', 'subject_id' => $shop->id]);
    }

    public function test_reject_requires_reason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->reject($this->shop(), $this->operator, '   ');
    }

    public function test_reject_records_note_and_illegal_transition_guarded(): void
    {
        $shop = $this->service->reject($this->shop(), $this->operator, 'Foto usaha kurang jelas.');
        $this->assertSame(UmkmStatus::SHOP_REJECTED, $shop->status);
        $this->assertSame('Foto usaha kurang jelas.', $shop->review_note);

        // Toko ditolak tidak bisa langsung disetujui (harus diajukan ulang dulu).
        $this->expectException(\InvalidArgumentException::class);
        $this->service->approve($shop, $this->operator);
    }

    public function test_resubmit_moves_rejected_to_pending_and_clears_note(): void
    {
        $shop = $this->service->reject($this->shop(), $this->operator, 'Kurang jelas.');
        $shop = $this->service->resubmit($shop);

        $this->assertSame(UmkmStatus::SHOP_PENDING, $shop->status);
        $this->assertNull($shop->review_note);
        $this->assertDatabaseHas('audit_logs', ['action' => 'umkm.shop.resubmitted', 'subject_id' => $shop->id]);
    }

    public function test_suspend_then_reactivate(): void
    {
        $shop = $this->service->approve($this->shop(), $this->operator);
        $shop = $this->service->suspend($shop, $this->operator, 'Ada laporan.');
        $this->assertSame(UmkmStatus::SHOP_SUSPENDED, $shop->status);

        $shop = $this->service->approve($shop, $this->operator);
        $this->assertSame(UmkmStatus::SHOP_ACTIVE, $shop->status);
    }

    public function test_non_reviewer_cannot_approve(): void
    {
        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $warga->assignRole('warga');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->service->approve($this->shop(), $warga->fresh());
    }

    public function test_handle_report_can_freeze_active_shop(): void
    {
        $shop = $this->service->approve($this->shop(), $this->operator);
        $report = ProductReport::create([
            'village_id' => $this->village->id,
            'shop_id' => $shop->id,
            'reporter_id' => $this->operator->id,
            'reason' => 'Diduga penipuan.',
            'status' => UmkmStatus::REPORT_NEW,
        ]);

        $handled = $this->service->handleReport($report, $this->operator, UmkmStatus::REPORT_RESOLVED, 'Ditindak', hideShop: true);

        $this->assertSame(UmkmStatus::REPORT_RESOLVED, $handled->status);
        $this->assertSame(UmkmStatus::SHOP_SUSPENDED, $shop->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'umkm.report.handled', 'subject_id' => $report->id]);
    }

    public function test_report_cannot_be_handled_twice(): void
    {
        $shop = $this->shop();
        $report = ProductReport::create([
            'village_id' => $this->village->id, 'shop_id' => $shop->id,
            'reporter_id' => $this->operator->id, 'reason' => 'X', 'status' => UmkmStatus::REPORT_NEW,
        ]);
        $this->service->handleReport($report, $this->operator, UmkmStatus::REPORT_DISMISSED);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->handleReport($report->fresh(), $this->operator, UmkmStatus::REPORT_RESOLVED);
    }

    public function test_stats_increment(): void
    {
        $shop = $this->shop();
        $this->service->recordView($shop);
        $this->service->recordView($shop);
        $this->service->recordWaClick($shop);

        $stats = $shop->fresh()->stats;
        $this->assertSame(2, $stats->views_count);
        $this->assertSame(1, $stats->wa_clicks_count);
    }

    public function test_wa_link_normalizes_to_international_format(): void
    {
        $url = $this->service->waLink($this->shop('0812-3456-7890'), 'Keripik Singkong', 'Desa Mekar');

        $this->assertStringContainsString('wa.me/6281234567890', $url);
        $this->assertStringContainsString(rawurlencode('Keripik Singkong'), $url);
        $this->assertStringContainsString('Apakah%20masih%20tersedia', $url);
    }
}
