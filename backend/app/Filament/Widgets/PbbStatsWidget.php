<?php

namespace App\Filament\Widgets;

use App\Models\PbbBill;
use App\Models\PbbDepositBatch;
use App\Support\PbbStatus;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ringkasan PBB untuk petugas desa di beranda panel (PEMBANGUNAN.md 4.3 & 4.7).
 * Scoping multi-tenant: admin melihat total seluruh desa, lainnya dibatasi desa aktif.
 * Fokus: realisasi tahun berjalan & uang yang masih "ditahan" (disetor belum di-ACC).
 */
class PbbStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $year = (int) date('Y');
        $due = $this->billQuery()->where('year', $year);

        $target = (float) (clone $due)->sum('amount');
        $collected = (float) (clone $due)->where('status', PbbStatus::BILL_PAID)->sum('amount');
        $realization = $target > 0 ? round($collected / $target * 100, 1) : 0.0;

        $held = (float) $this->billQuery()
            ->where('status', PbbStatus::BILL_PENDING)
            ->sum('amount');

        $pendingQuery = PbbDepositBatch::query()
            ->where('status', PbbStatus::DEPOSIT_PENDING);
        $actor = Filament::auth()->user();
        if ($actor && !$actor->isAdmin() && $actor->village_id) {
            $pendingQuery->where('village_id', $actor->village_id);
        }
        $stale = (clone $pendingQuery)
            ->where('created_at', '<', now()->subDays((int) config('desa.pbb.pending_reminder_days')))
            ->count();

        return [
            Stat::make("Realisasi PBB {$year}", $realization . '%')
                ->description('Terkumpul Rp ' . number_format($collected, 0, ',', '.') . ' / target Rp ' . number_format($target, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color($realization >= 70 ? 'success' : ($realization >= 40 ? 'warning' : 'danger')),
            Stat::make('Belum di-ACC (uang ditahan)', 'Rp ' . number_format($held, 0, ',', '.'))
                ->description('Tagihan berstatus disetor_pending')
                ->descriptionIcon('heroicon-m-clock')
                ->color($held > 0 ? 'warning' : 'gray'),
            Stat::make('Setoran menunggu > ' . config('desa.pbb.pending_reminder_days') . ' hari', (string) $stale)
                ->description($stale > 0 ? 'Perlu segera diperiksa bendahara' : 'Semua setoran sudah dinilai')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stale > 0 ? 'danger' : 'success'),
        ];
    }

    private function billQuery(): Builder
    {
        $query = PbbBill::query();
        $actor = Filament::auth()->user();

        if ($actor && !$actor->isAdmin() && $actor->village_id) {
            $query->where('village_id', $actor->village_id);
        }

        return $query;
    }
}
