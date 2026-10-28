<?php
namespace App\Models;

use App\Support\ComplaintStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris kronologi pengaduan (PEMBANGUNAN.md 4.5). Melayani dua hal sekaligus:
 * - balasan/percakapan (body diisi; is_staff menandai penulisnya petugas);
 * - riwayat status (from_status -> to_status terisi saat transisi).
 * Baris transisi yang tidak punya body tetap valid (hanya mencatat perubahan status).
 */
class ComplaintReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id', 'user_id', 'body', 'from_status', 'to_status', 'is_staff',
    ];

    protected function casts(): array
    {
        return ['is_staff' => 'boolean'];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isStatusChange(): bool
    {
        return $this->to_status !== null;
    }

    public function statusLabel(): ?string
    {
        return $this->to_status ? ComplaintStatus::label($this->to_status) : null;
    }
}
