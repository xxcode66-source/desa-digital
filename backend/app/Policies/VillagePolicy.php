<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Village;

/**
 * Kebijakan akses data desa (multi-tenant). Setiap warga/staff terikat satu village_id;
 * hanya boleh melihat desanya sendiri. Mengubah profil desa = urusan kades/admin desa itu.
 */
class VillagePolicy
{
    public function view(User $actor, Village $village): bool
    {
        return $actor->village_id === $village->id;
    }

    public function update(User $actor, Village $village): bool
    {
        return $actor->village_id === $village->id
            && $actor->hasAnyRole(['kades', 'admin']);
    }

    /** Pembuatan/penghapusan desa adalah aksi platform-level (admin). */
    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, Village $village): bool
    {
        return $actor->isAdmin();
    }
}
