<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedToVillage;
use App\Models\Letter;
use App\Support\LetterStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Antrean layanan surat per tahap verifikasi — memudahkan operator melihat macetnya di mana.
 */
class SuratStatsWidget extends StatsOverviewWidget
{
    use ScopedToVillage;

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $perluPerbaikan = $this->countOf(Letter::class, fn ($q) => $q->where('status', LetterStatus::REPAIR));
        $menungguTtd = $this->countOf(Letter::class, fn ($q) => $q->where('status', LetterStatus::SIGN));
        $diproses = $this->countOf(Letter::class, fn ($q) => $q->whereIn('status', [
            LetterStatus::SUBMITTED, LetterStatus::RT, LetterStatus::OPERATOR,
        ]));
        $selesai = $this->countOf(Letter::class, fn ($q) => $q->where('status', LetterStatus::DONE));

        return [
            Stat::make('Perlu Perbaikan', number_format($perluPerbaikan, 0, ',', '.'))
                ->description($perluPerbaikan > 0 ? 'Menunggu pemohon merevisi' : 'Tidak ada revisi')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($perluPerbaikan > 0 ? 'warning' : 'gray'),
            Stat::make('Menunggu TTD Kades', number_format($menungguTtd, 0, ',', '.'))
                ->description('Sudah dinomori, siap tanda tangan')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($menungguTtd > 0 ? 'info' : 'gray'),
            Stat::make('Sedang Diproses', number_format($diproses, 0, ',', '.'))
                ->description('Diverifikasi RT/operator')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('gray'),
            Stat::make('Selesai', number_format($selesai, 0, ',', '.'))
                ->description('PDF siap unduh warga')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
