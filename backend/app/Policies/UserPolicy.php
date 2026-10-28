<?php

namespace App\Policies;

use App\Models\User;

/**
 * RBAC Fase 1 (PEMBANGUNAN.md 4.1 & 5). Prinsip multi-desa: kecuali super-admin,
 * semua tindakan staff dibatasi ke akun pada SATU desa yang sama (village_id actor
 * == village_id target). Verifikasi identitas warga dilakukan operator, bukan otomatis.
 */
class UserPolicy
{
    /** Staff yang boleh memverifikasi/menolak akun warga. */
    private const VERIFIER_ROLES = ['operator', 'kades', 'admin'];

    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isStaff() && $this->sameVillage($actor, $target);
    }

    /** Operator bisa menyetujui/menolak warga pending (atau yang previously rejected). */
    public function verify(User $actor, User $target): bool
    {
        return $actor->hasAnyRole(self::VERIFIER_ROLES)
            && $this->sameVillage($actor, $target)
            && $actor->id !== $target->id; // tidak memverifikasi dirinya sendiri
    }

    public function reject(User $actor, User $target): bool
    {
        return $this->verify($actor, $target);
    }

    /** Perubahan keanggotaan role — hanya kades/admin, tidak lintas desa. */
    public function manageRoles(User $actor, User $target): bool
    {
        return $actor->hasAnyRole(['kades', 'admin'])
            && $this->sameVillage($actor, $target);
    }

    /** Edit data warga oleh staff (bukan self-service; profil sendiri lewat endpoint terpisah). */
    public function update(User $actor, User $target): bool
    {
        return $actor->isStaff() && $this->sameVillage($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->isAdmin() && $this->sameVillage($actor, $target);
    }

    private function sameVillage(User $actor, User $target): bool
    {
        return $actor->village_id === $target->village_id;
    }
}
