<?php
namespace App\Models;

use App\Support\PbbStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Klaim kepemilikan warga atas sebuah NOP (PEMBANGUNAN.md 4.3). Warga mengunggah bukti
 * (SPPT/surat tanah) via disk privat; klaim baru berarti setelah diverifikasi operator/kades.
 * Satu user hanya bisa punya satu klaim aktif per objek (unique user_id+pbb_object_id).
 */
class PbbClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'user_id', 'pbb_object_id', 'status', 'note',
        'disk', 'evidence_path', 'evidence_name',
        'reviewed_by', 'review_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'village_id' => 'integer',
            'user_id' => 'integer',
            'pbb_object_id' => 'integer',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PbbClaim $claim): void {
            $claim->status ??= PbbStatus::CLAIM_PENDING;
        });
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(PbbObject::class, 'pbb_object_id');
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
