<?php
namespace App\Models;

use App\Support\PbbStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Batch setoran PBB dari RT/Kolektor (PEMBANGUNAN.md 4.3). Satu slip bisa memuat banyak
 * NOP+tahun (pbb_deposit_items). Bendahara boleh ACC sebagian per item; status batch
 * diturunkan dari item: pending -> partially_approved/approved/rejected.
 * Total = uang FISIK yang disetor, bukan transaksi di aplikasi (tanpa pembayaran in-app).
 */
class PbbDepositBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'collector_id', 'title', 'total_amount', 'item_count',
        'status', 'note', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'village_id' => 'integer',
            'collector_id' => 'integer',
            'total_amount' => 'decimal:2',
            'item_count' => 'integer',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PbbDepositBatch $batch): void {
            $batch->status ??= PbbStatus::DEPOSIT_PENDING;
        });
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /** RT/kolektor yang menyetor. */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PbbDepositItem::class);
    }

    public function statusLabel(): string
    {
        return PbbStatus::label($this->status);
    }
}
