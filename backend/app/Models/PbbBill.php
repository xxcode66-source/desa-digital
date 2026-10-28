<?php
namespace App\Models;

use App\Support\PbbStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tagihan PBB per NOP per tahun pajak (PEMBANGUNAN.md 4.3). Nominal pokok + denda,
 * dengan status mesin (belum_bayar -> disetor_pending -> lunas). ACC bendahara membuat
 * tagihan 'lunas' — ini status INTERNAL desa, bukan bukti pelunasan resmi.
 */
class PbbBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'pbb_object_id', 'year', 'amount', 'penalty',
        'status', 'due_date', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'village_id' => 'integer',
            'pbb_object_id' => 'integer',
            'year' => 'integer',
            'amount' => 'decimal:2',
            'penalty' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PbbBill $bill): void {
            $bill->status ??= PbbStatus::BILL_UNPAID;
        });
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(PbbObject::class, 'pbb_object_id');
    }

    /** Total yang harus dibayar (pokok + denda). */
    public function total(): float
    {
        return (float) $this->amount + (float) $this->penalty;
    }

    public function isPaid(): bool
    {
        return PbbStatus::isBillPaid($this->status);
    }

    public function isArrears(): bool
    {
        return PbbStatus::isBillArrears($this->status);
    }

    public function statusLabel(): string
    {
        return PbbStatus::label($this->status);
    }
}
