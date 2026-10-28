<?php
namespace App\Models;

use App\Support\PbbStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu item dalam batch setoran: pasangan NOP/tagihan + nominal yang disetor (PEMBANGUNAN.md 4.3).
 * Bisa di-ACC sebagian oleh bendahara (pending -> approved/rejected). Foto bukti kuitansi di
 * disk privat. Saat item di-ACC, tagihan terkait menjadi 'lunas'.
 */
class PbbDepositItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pbb_deposit_batch_id', 'pbb_bill_id', 'amount_claimed', 'status',
        'disk', 'evidence_path', 'evidence_name',
        'reviewed_by', 'review_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'pbb_deposit_batch_id' => 'integer',
            'pbb_bill_id' => 'integer',
            'amount_claimed' => 'decimal:2',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PbbDepositItem $item): void {
            $item->status ??= PbbStatus::ITEM_PENDING;
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PbbDepositBatch::class, 'pbb_deposit_batch_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PbbBill::class, 'pbb_bill_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return PbbStatus::label($this->status);
    }
}
