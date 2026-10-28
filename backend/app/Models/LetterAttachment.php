<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran surat (KTP/KK/foto usaha) — disimpan di disk privat, diakses via signed URL.
 * Setelah dikirim tidak bisa diganti, hanya ditambah (PEMBANGUNAN.md 4.3 utk PBB; sama prinsipnya).
 */
class LetterAttachment extends Model
{
    protected $fillable = [
        'letter_id', 'user_id', 'disk', 'path', 'original_name', 'mime', 'size', 'kind',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }
}
