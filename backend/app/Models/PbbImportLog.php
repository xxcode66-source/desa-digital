<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat import CSV database PBB (PEMBANGUNAN.md 4.3). Mencatat versi data per file:
 * baris dibaca/dibuat/diupdate/dilewati + status. Untuk audit & troubleshooting operator.
 */
class PbbImportLog extends Model
{
    protected $fillable = [
        'village_id', 'user_id', 'filename',
        'rows_total', 'rows_created', 'rows_updated', 'rows_skipped',
        'status', 'message',
    ];

    protected function casts(): array
    {
        return [
            'village_id' => 'integer',
            'user_id' => 'integer',
            'rows_total' => 'integer',
            'rows_created' => 'integer',
            'rows_updated' => 'integer',
            'rows_skipped' => 'integer',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
