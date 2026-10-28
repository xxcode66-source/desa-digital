<?php
namespace App\Policies;

use App\Models\Letter;
use App\Models\User;

/**
 * RBAC surat (PEMBANGUNAN.md 3 & 4.2). Prinsip multi-desa: staff hanya bisa memproses
 * surat desanya sendiri; warga hanya melihat/mengubah surat miliknya. Pengajuan surat
 * mensyaratkan akun sudah diverifikasi operator (bagian 4.1).
 */
class LetterPolicy
{
    private const STAFF = ['operator', 'kades', 'admin'];

    public function viewAny(User $actor): bool
    {
        return true; // warga lihat miliknya sendiri; staff lihat via panel (ter-scope).
    }

    public function view(User $actor, Letter $letter): bool
    {
        return $actor->id === $letter->user_id
            || ($actor->isStaff() && $actor->village_id === $letter->village_id);
    }

    /** Warga boleh mengajukan bila akunnya sudah diverifikasi operator. */
    public function create(User $actor): bool
    {
        return $actor->isVerified();
    }

    /** Warga bisa memperbaiki/mengirim ulang hanya pada draf / perlu_perbaikan. */
    public function update(User $actor, Letter $letter): bool
    {
        return $actor->id === $letter->user_id && $letter->isEditableByCitizen();
    }

    /** Pengantar RT/RW: role rt pada desa & wilayah yang sama. */
    public function approveRt(User $actor, Letter $letter): bool
    {
        return $actor->village_id === $letter->village_id
            && $actor->hasAnyRole(['rt', ...self::STAFF]);
    }

    /** Verifikasi operator (verifikasi_operator): operator/kades/admin satu desa. */
    public function process(User $actor, Letter $letter): bool
    {
        return $actor->hasAnyRole(self::STAFF) && $actor->village_id === $letter->village_id;
    }

    /** Tanda tangan & finalize (menunggu_ttd -> selesai): kades/admin satu desa. */
    public function finalize(User $actor, Letter $letter): bool
    {
        return $actor->hasAnyRole(['kades', 'admin']) && $actor->village_id === $letter->village_id;
    }

    public function delete(User $actor, Letter $letter): bool
    {
        return $actor->isAdmin() && $actor->village_id === $letter->village_id;
    }
}
