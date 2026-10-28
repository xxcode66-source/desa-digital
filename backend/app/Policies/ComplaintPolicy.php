<?php
namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

/**
 * RBAC pengaduan (PEMBANGUNAN.md 3 & 4.5). Multi-desa: staff hanya menangani pengaduan
 * desanya sendiri; warga hanya melihat/membalas pengaduannya sendiri. Identitas pelapor
 * tetap terlihat oleh petugas desa walau pengaduan ditandai anonim.
 */
class ComplaintPolicy
{
    private const STAFF = ['operator', 'kades', 'admin'];

    public function viewAny(User $actor): bool
    {
        return true;
    }

    /** Pemilik atau petugas desa yang sama. Anonim TIDAK menyembunyikan dari petugas/admin. */
    public function view(User $actor, Complaint $complaint): bool
    {
        return $actor->id === $complaint->user_id
            || ($actor->isStaff() && $actor->village_id === $complaint->village_id);
    }

    /** Sama seperti surat: layanan butuh akun terverifikasi (cegh spam akun palsu). */
    public function create(User $actor): bool
    {
        return $actor->isVerified();
    }

    /** Warga bisa membalas selama pengaduan belum selesai/ditolak; petugas selalu bisa. */
    public function reply(User $actor, Complaint $complaint): bool
    {
        if ($actor->id === $complaint->user_id) {
            return !$complaint->isClosed();
        }

        return $actor->hasAnyRole(self::STAFF) && $actor->village_id === $complaint->village_id;
    }

    /** Ubah status (proses/selesai/tolak): operator/kades/admin satu desa. */
    public function process(User $actor, Complaint $complaint): bool
    {
        return $actor->hasAnyRole(self::STAFF) && $actor->village_id === $complaint->village_id;
    }

    public function delete(User $actor, Complaint $complaint): bool
    {
        return $actor->isAdmin() && $actor->village_id === $complaint->village_id;
    }
}
