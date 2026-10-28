<?php
namespace App\Models;

use App\Support\LetterStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Surat yang diajukan warga (PEMBANGUNAN.md 4.2). Data formulir disimpan di data_json
 * (divalidasi terhadap form_schema jenis surat). Alur status dicatat di letter_status_logs.
 */
class Letter extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'letter_type_id', 'user_id', 'data_json', 'status',
        'number', 'public_token', 'note', 'pdf_path', 'submitted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'data_json' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Letter $letter): void {
            $letter->status ??= LetterStatus::SUBMITTED;
        });
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LetterType::class, 'letter_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LetterAttachment::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(LetterStatusLog::class)->latest('id');
    }

    public function isEditableByCitizen(): bool
    {
        return LetterStatus::isCitizenEditable($this->status);
    }

    /** Field yang ditandai operator pada permintaan perbaikan terakhir (kosong bila tak ada). */
    public function flaggedFields(): array
    {
        $log = $this->statusLogs()
            ->where('to_status', LetterStatus::REPAIR)
            ->whereNotNull('flagged_fields_json')
            ->first();

        return $log?->flagged_fields_json ?? [];
    }

    public function statusLabel(): string
    {
        return LetterStatus::label($this->status);
    }
}
