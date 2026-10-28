<?php
namespace App\Models;

use App\Support\UmkmStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Laporan warga atas produk/toko UMKM untuk moderasi operator (PEMBANGUNAN.md 4.4).
 * Salah satu dari product_id / shop_id terisi. Operator menandai `ditindak` (mis. sembunyikan
 * toko) atau `diabaikan`. Semua penanganan tercatat di audit log.
 */
class ProductReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'product_id', 'shop_id', 'reporter_id', 'reason',
        'status', 'handled_by', 'handle_note', 'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'village_id' => 'integer',
            'product_id' => 'integer',
            'shop_id' => 'integer',
            'reporter_id' => 'integer',
            'handled_by' => 'integer',
            'handled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductReport $report): void {
            $report->status ??= UmkmStatus::REPORT_NEW;
        });
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function statusLabel(): string
    {
        return UmkmStatus::label($this->status);
    }
}
