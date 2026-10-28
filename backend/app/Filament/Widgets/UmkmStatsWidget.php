<?php

namespace App\Filament\Widgets;

use App\Models\ProductReport;
use App\Models\Shop;
use App\Support\UmkmStatus;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ringkasan marketplace UMKM untuk petugas desa di beranda panel (PEMBANGUNAN.md 4.4 & 4.7).
 * Scoping multi-tenant: admin melihat total seluruh desa, lainnya dibatasi desa aktif.
 * Fokus: antrean verifikasi toko & laporan moderasi yang menunggu ditindak.
 */
class UmkmStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $pending = $this->shopQuery()->where('status', UmkmStatus::SHOP_PENDING)->count();
        $active = $this->shopQuery()->where('status', UmkmStatus::SHOP_ACTIVE)->count();

        $reportQuery = ProductReport::query()->where('status', UmkmStatus::REPORT_NEW);
        $actor = Filament::auth()->user();
        if ($actor && !$actor->isAdmin() && $actor->village_id) {
            $reportQuery->where('village_id', $actor->village_id);
        }
        $newReports = $reportQuery->count();

        return [
            Stat::make('Toko menunggu verifikasi', (string) $pending)
                ->description($pending > 0 ? 'Perlu ditinjau operator' : 'Antrean bersih')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pending > 0 ? 'warning' : 'success'),
            Stat::make('Toko aktif', (string) $active)
                ->description('Tampil di marketplace desa')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('gray'),
            Stat::make('Laporan moderasi baru', (string) $newReports)
                ->description($newReports > 0 ? 'Menunggu tindak lanjut' : 'Tidak ada laporan baru')
                ->descriptionIcon('heroicon-m-flag')
                ->color($newReports > 0 ? 'danger' : 'success'),
        ];
    }

    private function shopQuery(): Builder
    {
        $query = Shop::query();
        $actor = Filament::auth()->user();

        if ($actor && !$actor->isAdmin() && $actor->village_id) {
            $query->where('village_id', $actor->village_id);
        }

        return $query;
    }
}
