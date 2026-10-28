<?php
namespace App\Policies;

use App\Models\ProductReport;
use App\Models\User;

/**
 * RBAC laporan moderasi UMKM (PEMBANGUNAN.md 4.4 & 3). Warga terverifikasi boleh melaporkan
 * produk/toko; hanya petugas desa (operator/kades/admin) satu desa yang bisa melihat & menangani.
 */
class ProductReportPolicy
{
    private const REVIEWERS = ['operator', 'kades', 'admin'];

    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(self::REVIEWERS);
    }

    /** Petugas desa yang sama bisa meninjau laporan; pelapor bisa melihat laporannya sendiri. */
    public function view(User $actor, ProductReport $report): bool
    {
        return ($actor->hasAnyRole(self::REVIEWERS) && $actor->village_id === $report->village_id)
            || $actor->id === $report->reporter_id;
    }

    /** Lapor butuh akun terverifikasi (cegah spam/penyalahgunaan). */
    public function create(User $actor): bool
    {
        return $actor->isVerified();
    }

    /** Tindak/abaikan laporan: operator/kades/admin satu desa. */
    public function handle(User $actor, ProductReport $report): bool
    {
        return $actor->hasAnyRole(self::REVIEWERS) && $actor->village_id === $report->village_id;
    }
}
