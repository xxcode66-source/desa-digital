<?php
namespace App\Models;

use App\Support\ComplaintStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengaduan warga (PEMBANGUNAN.md 4.5). Laporan punya kategori, lokasi opsional, foto bukti,
 * dan alur status (diterima -> diproses -> selesai/ditolak). `is_anonymous` hanya menyembunyikan
 * identitas pelapor dari papan publik — pelapor TETAP tercatat (user_id) untuk admin.
 */
class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'user_id', 'category', 'title', 'description',
        'location', 'latitude', 'longitude', 'status', 'is_anonymous',
        'submitted_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_anonymous' => 'boolean',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Complaint $complaint): void {
            $complaint->status ??= ComplaintStatus::ACCEPTED;
            $complaint->submitted_at ??= now();
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

    public function photos(): HasMany
    {
        return $this->hasMany(ComplaintPhoto::class);
    }

    /** Kronologis (terlama dulu) — dibaca sebagai percakapan + riwayat. */
    public function replies(): HasMany
    {
        return $this->hasMany(ComplaintReply::class)->oldest('id');
    }

    public function isClosed(): bool
    {
        return ComplaintStatus::isClosed($this->status);
    }

    public function statusLabel(): string
    {
        return ComplaintStatus::label($this->status);
    }

    /** Nama pelapor untuk papan publik: "Warga anonim" bila ditandai anonim. */
    public function publicReporterName(): string
    {
        return $this->is_anonymous ? 'Warga anonim' : ($this->user?->name ?? 'Warga');
    }
}
