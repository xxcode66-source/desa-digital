<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto bukti pengaduan (PEMBANGUNAN.md 4.5) — disk privat, diakses lewat endpoint ber-otorisasi.
 */
class ComplaintPhoto extends Model
{
    protected $fillable = [
        'complaint_id', 'user_id', 'disk', 'path', 'original_name', 'mime', 'size',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}
