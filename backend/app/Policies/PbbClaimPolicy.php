<?php
namespace App\Policies;

use App\Models\PbbClaim;
use App\Models\User;

/**
 * RBAC klaim kepemilikan NOP (PEMBANGUNAN.md 4.3 & 3). Warga terverifikasi bisa mengajukan
 * klaim atas objek di desanya; hanya pemilik & petugas desa terkait yang melihat klaim.
 * Verifikasi/ACC klaim dilakukan operator/kades/admin satu desa (bukan warga).
 */
class PbbClaimPolicy
{
    private const REVIEWERS = ['operator', 'kades', 'admin'];

    public function viewAny(User $actor): bool
    {
        return $actor->isVerified();
    }

    /** Pemilik klaim, atau petugas desa yang sama (untuk meninjau). */
    public function view(User $actor, PbbClaim $claim): bool
    {
        return $actor->id === $claim->user_id
            || ($actor->hasAnyRole(self::REVIEWERS) && $actor->village_id === $claim->village_id);
    }

    public function create(User $actor): bool
    {
        return $actor->isVerified();
    }

    /** Setujui/tolak klaim: operator/kades/admin satu desa. */
    public function review(User $actor, PbbClaim $claim): bool
    {
        return $actor->hasAnyRole(self::REVIEWERS) && $actor->village_id === $claim->village_id;
    }

    /** Unduh bukti klaim (disk privat): pemilik atau peninjau desa yang sama. */
    public function downloadEvidence(User $actor, PbbClaim $claim): bool
    {
        return $this->view($actor, $claim);
    }
}
