<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perubahan status surat (PEMBANGUNAN.md 4.2 & 5). Immutabel — dibuat tiap
 * transisi, dicatat siapa (actor) dan dari/ke status, plus catatan & field ditandai.
 */
class LetterStatusLog extends Model
{
    protected $fillable = [
        'letter_id', 'from_status', 'to_status', 'note', 'flagged_fields_json', 'actor_id',
    ];

    protected function casts(): array
    {
        return ['flagged_fields_json' => 'array'];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
