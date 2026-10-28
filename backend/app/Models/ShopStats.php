<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Counter agregat per toko UMKM (PEMBANGUNAN.md 4.4): jumlah dilihat & klik WA — bahan
 * "statistik penjual". Ditambah atomik lewat UmkmService agar aman dari race.
 */
class ShopStats extends Model
{
    use HasFactory;

    protected $fillable = ['shop_id', 'views_count', 'wa_clicks_count'];

    protected function casts(): array
    {
        return [
            'shop_id' => 'integer',
            'views_count' => 'integer',
            'wa_clicks_count' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
