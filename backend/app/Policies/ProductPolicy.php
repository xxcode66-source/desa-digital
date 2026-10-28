<?php
namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * RBAC produk UMKM (PEMBANGUNAN.md 4.4 & 3). Produk mengikuti kepemilikan tokonya: hanya
 * pemilik toko yang bisa membuat/mengubah/menghapus. Publik (warga) boleh melihat produk dari
 * toko yang aktif & tidak disembunyikan; petugas desa terkait selalu boleh.
 */
class ProductPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    /** Produk terlihat bila tokonya tampil ke publik, atau pemilik toko / petugas desa yang sama. */
    public function view(User $actor, Product $product): bool
    {
        $shop = $product->shop;

        return ($shop?->isPubliclyVisible() ?? false)
            || ($actor->id === $shop?->user_id)
            || ($actor->isStaff() && $actor->village_id === $product->village_id);
    }

    /** Hanya pemilik toko (yang terverifikasi) yang bisa menambah produk. */
    public function create(User $actor): bool
    {
        return $actor->isVerified();
    }

    public function update(User $actor, Product $product): bool
    {
        return $actor->id === $product->shop?->user_id;
    }

    public function delete(User $actor, Product $product): bool
    {
        return $actor->id === $product->shop?->user_id;
    }
}
