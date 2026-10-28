<?php
namespace App\Models;

use App\Support\UmkmStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Toko UMKM milik seorang pelaku (PEMBANGUNAN.md 4.4). Punya pemilik (user), kategori, lokasi,
 * nomor WA untuk tombol chat, dan status verifikasi operator. Produk hanya tampil di marketplace
 * bila toko `aktif` dan tidak `is_hidden` (moderasi). Tidak ada pembayaran di aplikasi.
 */
class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'user_id', 'name', 'description', 'category', 'address',
        'rt', 'rw', 'opening_hours', 'wa_number',
        'disk', 'photo_path', 'photo_name',
        'status', 'is_hidden', 'reviewed_by', 'review_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'village_id' => 'integer',
            'user_id' => 'integer',
            'is_hidden' => 'boolean',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Shop $shop): void {
            $shop->status ??= UmkmStatus::SHOP_PENDING;
            $shop->is_hidden ??= false;
        });
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(ShopStats::class);
    }

    public function statusLabel(): string
    {
        return UmkmStatus::label($this->status);
    }

    public function categoryLabel(): string
    {
        return config('desa.umkm.categories')[$this->category] ?? $this->category;
    }

    /** Tampil ke publik hanya jika aktif & tidak disembunyikan. */
    public function isPubliclyVisible(): bool
    {
        return UmkmStatus::isPubliclyVisible($this->status, (bool) $this->is_hidden);
    }
}
