<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DesaHeaderWidget;
use App\Filament\Widgets\DesaOverviewWidget;
use App\Filament\Widgets\PbbStatsWidget;
use App\Filament\Widgets\PengaduanStatsWidget;
use App\Filament\Widgets\PengaduanTerbaruWidget;
use App\Filament\Widgets\SuratStatsWidget;
use App\Filament\Widgets\UmkmStatsWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Beranda panel yang dirakit ulang: banner desa + ringkasan tiap layanan + pengaduan terbaru.
 * Menggantikan Dashboard bawaan (yang cuma menampilkan AccountWidget generik).
 */
class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Dasbor';

    protected static ?string $title = 'Dasbor';

    public function getWidgets(): array
    {
        return [
            DesaHeaderWidget::class,
            DesaOverviewWidget::class,
            SuratStatsWidget::class,
            PengaduanStatsWidget::class,
            PbbStatsWidget::class,
            UmkmStatsWidget::class,
            PengaduanTerbaruWidget::class,
        ];
    }
}
