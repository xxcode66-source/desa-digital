<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produk/jasa milik satu toko UMKM (PEMBANGUNAN.md 4.4). Foto disimpan di tabel product_photos
 * (maks 5, disk privat). Tidak ada stok/keranjang/pembayaran — hanya flag ketersediaan
 * (tersedia/habis) untuk memberi tanda ke pembeli.
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'shop_id', 'name', 'description', 'price', 'is_available',
    ];

    protected function casts(): array
    {
        return [
            'village_id' => 'integer',
            'shop_id' => 'integer',
            'price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $product->is_available ??= true;
        });
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProductReport::class);
    }
}
