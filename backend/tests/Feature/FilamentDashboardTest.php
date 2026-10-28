<?php
// Smoke test beranda Filament pasca-restyling.
// Widget dashboard dirender sebagai island Livewire (deferred via x-intersect), jadi GET /admin
// hanya memuat placeholder; isi widget diuji dengan me-mount komponen Livewire-nya langsung
// (menjamin getStats()/table() benar-benar dieksekusi tanpa error).

namespace Tests\Feature;

use App\Filament\Widgets\DesaHeaderWidget;
use App\Filament\Widgets\DesaOverviewWidget;
use App\Filament\Widgets\PengaduanStatsWidget;
use App\Filament\Widgets\PengaduanTerbaruWidget;
use App\Filament\Widgets\SuratStatsWidget;
use App\Models\User;
use App\Models\Village;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $v = Village::factory()->create();
        $u = User::factory()->create(['village_id' => $v->id]);
        $u->assignRole($role);

        return $u;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_dashboard_page_loads_and_registers_widgets(): void
    {
        $this->actingAs($this->staff('admin'))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Desa Digital')            // brandName panel
            ->assertSee('Dasbor')                  // judul beranda kustom
            ->assertSee('DesaHeaderWidget', false)  // widget terdaftar & mounted
            ->assertSee('PengaduanTerbaruWidget', false);
    }

    public function test_header_widget_shows_village(): void
    {
        Livewire::actingAs($this->staff('admin'))
            ->test(DesaHeaderWidget::class)
            ->assertSee('Panel Layanan Desa');
    }

    public function test_stats_widgets_execute_without_error(): void
    {
        $admin = $this->staff('admin');

        Livewire::actingAs($admin)->test(DesaOverviewWidget::class)->assertSee('Menunggu Verifikasi');
        Livewire::actingAs($admin)->test(SuratStatsWidget::class)->assertSee('Menunggu TTD Kades');
        Livewire::actingAs($admin)->test(PengaduanStatsWidget::class)->assertSee('Baru Diterima');
    }

    public function test_pengaduan_table_widget_executes(): void
    {
        Livewire::actingAs($this->staff('operator'))
            ->test(PengaduanTerbaruWidget::class)
            ->assertOk();
    }
}
