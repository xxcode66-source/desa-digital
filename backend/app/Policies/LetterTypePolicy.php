<?php
namespace App\Policies;

use App\Models\LetterType;
use App\Models\User;

/**
 * Kelola jenis surat dinamis (PEMBANGUNAN.md 4.2). Jenis bawaan (village_id NULL)
 * hanya bisa diubah admin pusat. Jenis milik satu desa bisa dikelola kades/admin desa itu.
 */
class LetterTypePolicy
{
    public function viewAny(User $actor): bool
    {
        return true; // warga butuh daftar jenis aktif (lewat endpoint terpisah).
    }

    public function create(User $actor): bool
    {
        return $actor->hasAnyRole(['kades', 'admin']);
    }

    public function update(User $actor, LetterType $type): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        return $actor->hasAnyRole(['kades', 'admin'])
            && $type->village_id !== null
            && $type->village_id === $actor->village_id;
    }

    public function delete(User $actor, LetterType $type): bool
    {
        return $this->update($actor, $type);
    }
}
