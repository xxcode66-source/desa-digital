<?php
namespace App\Models;

use App\Support\PbbStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Objek pajak PBB (PEMBANGUNAN.md 4.3). NOP unik per desa. Warga bisa mengklaim
 * kepemilikan (pbb_claims) setelah diverifikasi; satu objek bisa punya banyak tagihan
 * per tahun (pbb_bills).
 */
class PbbObject extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'nop', 'wp_name', 'address', 'rt', 'rw', 'land_area', 'building_area',
    ];

    protected function casts(): array
    {
        return [
            'village_id' => 'integer',
            'land_area' => 'decimal:2',
            'building_area' => 'decimal:2',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(PbbBill::class)->orderBy('year');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(PbbClaim::class);
    }

    /** Klaim yang sudah disetujui — dipakai utk daftar "NOP saya" seorang warga. */
    public function approvedClaims(): HasMany
    {
        return $this->claims()->where('status', PbbStatus::CLAIM_APPROVED);
    }

    /** Daftar tahun yang masih jadi tunggakan (belum lunas). */
    public function arrearsYears(): array
    {
        return $this->bills
            ->filter(fn (PbbBill $b) => PbbStatus::isBillArrears($b->status))
            ->pluck('year')
            ->all();
    }
}
