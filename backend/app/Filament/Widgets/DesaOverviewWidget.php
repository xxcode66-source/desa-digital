<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedToVillage;
use App\Models\Complaint;
use App\Models\Letter;
use App\Models\Shop;
use App\Models\User;
use App\Support\LetterStatus;
use App\Support\UmkmStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan tinggi lintas layanan di beranda: kependudukan, surat, UMKM, pengaduan.
 */
class DesaOverviewWidget extends StatsOverviewWidget
{
    use ScopedToVillage;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $warga = $this->countOf(User::class, fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'warga')));
        $pending = $this->countOf(User::class, fn ($q) => $q->where('status', User::STATUS_PENDING));
        $suratSelesai = $this->countOf(Letter::class, fn ($q) => $q->where('status', LetterStatus::DONE)
            ->where('completed_at', '>=', now()->startOfMonth()));
        $tokoAktif = $this->countOf(Shop::class, fn ($q) => $q->where('status', UmkmStatus::SHOP_ACTIVE));
        $pengaduan = $this->countOf(Complaint::class, fn ($q) => $q->where('submitted_at', '>=', now()->startOfMonth()));

        return [
            Stat::make('Total Warga', number_format($warga, 0, ',', '.'))
                ->description('Terdaftar di kependudukan desa')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('gray'),
            Stat::make('Menunggu Verifikasi', number_format($pending, 0, ',', '.'))
                ->description($pending > 0 ? 'Perlu ditinjau operator' : 'Semua sudah diverifikasi')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pending > 0 ? 'warning' : 'success'),
            Stat::make('Surat Terbit (bln ini)', number_format($suratSelesai, 0, ',', '.'))
                ->description('Selesai & siap cetak')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('info'),
            Stat::make('Pengaduan (bln ini)', number_format($pengaduan, 0, ',', '.'))
                ->description("{$tokoAktif} toko UMKM aktif")
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('success'),
        ];
    }
}
