<?php
namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

/**
 * RBAC toko UMKM (PEMBANGUNAN.md 4.4 & 3). Warga terverifikasi bisa mendaftar & mengelola
 * tokonya sendiri; publik (warga) boleh melihat toko aktif yang tidak disembunyikan;
 * verifikasi & moderasi hanya petugas desa yang sama.
 */
class ShopPolicy
{
    private const REVIEWERS = ['operator', 'kades', 'admin'];

    public function viewAny(User $actor): bool
    {
        return true;
    }

    /** Toko aktif & tak tersembunyi bisa dilihat siapa pun; pemilik & petugas desa terkait selalu bisa. */
    public function view(User $actor, Shop $shop): bool
    {
        return $shop->isPubliclyVisible()
            || $actor->id === $shop->user_id
            || ($actor->isStaff() && $actor->village_id === $shop->village_id);
    }

    /** Daftar toko butuh akun terverifikasi (cegah toko palsu / penipuan). */
    public function create(User $actor): bool
    {
        return $actor->isVerified();
    }

    /** Pemilik mengedit tokonya sendiri (petugas tidak mengedit konten penjual). */
    public function update(User $actor, Shop $shop): bool
    {
        return $actor->id === $shop->user_id;
    }

    public function delete(User $actor, Shop $shop): bool
    {
        return $actor->id === $shop->user_id;
    }

    /** Setujui/tolak/nonaktifkan: operator/kades/admin satu desa. */
    public function review(User $actor, Shop $shop): bool
    {
        return $actor->hasAnyRole(self::REVIEWERS) && $actor->village_id === $shop->village_id;
    }
}
