<?php
namespace App\Policies;

use App\Models\PbbDepositBatch;
use App\Models\User;

/**
 * RBAC setoran PBB (PEMBANGUNAN.md 4.3 & 3). RT/kolektor terverifikasi mengajukan slip
 * setoran atas tagihan di desanya; hanya kolektor pemilik & bendahara/admin desa yang
 * sama bisa melihat. Menilai (ACC sebagian/tolak) khusus bendahara_pbb/admin.
 */
class PbbDepositBatchPolicy
{
    private const REVIEWERS = ['bendahara_pbb', 'admin'];

    public function viewAny(User $actor): bool
    {
        return $actor->isCollector() || $actor->hasAnyRole(self::REVIEWERS);
    }

    public function create(User $actor): bool
    {
        return $actor->isCollector() && $actor->isVerified();
    }

    /** Kolektor pemilik, atau bendahara/admin desa yang sama. */
    public function view(User $actor, PbbDepositBatch $batch): bool
    {
        return $actor->id === $batch->collector_id
            || ($actor->hasAnyRole(self::REVIEWERS)
                && ($actor->isAdmin() || $actor->village_id === $batch->village_id));
    }

    /** Nilai setoran (ACC sebagian/tolak per item): bendahara_pbb/admin satu desa. */
    public function review(User $actor, PbbDepositBatch $batch): bool
    {
        return $actor->hasAnyRole(self::REVIEWERS)
            && ($actor->isAdmin() || $actor->village_id === $batch->village_id);
    }
}
