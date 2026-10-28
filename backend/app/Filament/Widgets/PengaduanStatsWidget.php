<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedToVillage;
use App\Models\Complaint;
use App\Support\ComplaintStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Status pengaduan warga — menyoroti mana yang belum direspons.
 */
class PengaduanStatsWidget extends StatsOverviewWidget
{
    use ScopedToVillage;

    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $baru = $this->countOf(Complaint::class, fn ($q) => $q->where('status', ComplaintStatus::ACCEPTED));
        $diproses = $this->countOf(Complaint::class, fn ($q) => $q->where('status', ComplaintStatus::IN_PROGRESS));
        $selesai = $this->countOf(Complaint::class, fn ($q) => $q->where('status', ComplaintStatus::DONE));
        $ditolak = $this->countOf(Complaint::class, fn ($q) => $q->where('status', ComplaintStatus::REJECTED));

        return [
            Stat::make('Baru Diterima', number_format($baru, 0, ',', '.'))
                ->description($baru > 0 ? 'Belum direspons petugas' : 'Tidak ada antrean baru')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($baru > 0 ? 'warning' : 'success'),
            Stat::make('Sedang Diproses', number_format($diproses, 0, ',', '.'))
                ->description('Sedang ditangani')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('info'),
            Stat::make('Selesai', number_format($selesai, 0, ',', '.'))
                ->description('Sudah ditindaklanjuti')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Ditolak', number_format($ditolak, 0, ',', '.'))
                ->description('Tidak memenuhi kriteria')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('gray'),
        ];
    }
}
