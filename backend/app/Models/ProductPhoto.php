<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu foto produk UMKM di disk privat (PEMBANGUNAN.md 4.4) — pola sama dgn complaint_photos.
 */
class ProductPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'disk', 'path', 'original_name', 'mime', 'size',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'size' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
