<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenis surat dinamis (PEMBANGUNAN.md 4.2). Admin bisa menambah jenis surat baru
 * tanpa mengubah kode: nama + form_schema_json (definisi field) + lampiran wajib.
 * village_id NULL = jenis bawaan yang tersedia untuk semua desa.
 */
class LetterType extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'code', 'name', 'needs_rt',
        'form_schema_json', 'required_attachments_json', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'form_schema_json' => 'array',
            'required_attachments_json' => 'array',
            'needs_rt' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    /** Jenis aktif yang berlaku untuk sebuah desa (bawaan + milik desa tsb). */
    public function scopeAvailableTo(Builder $query, int $villageId): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('village_id')->orWhere('village_id', $villageId));
    }

    /** Daftar key field yang diminta formulir, untuk validasi data_json. */
    public function fieldKeys(): array
    {
        return array_column($this->form_schema_json ?? [], 'key');
    }
}
