<?php
namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Catatan audit log (PEMBANGUNAN.md bagian 1 & 5):
 * semua tindakan penting tercatat — siapa, kapan, apa. Log tidak untuk dihapus.
 */
class AuditLogger
{
    public static function record(string $action, ?Model $subject = null, ?string $description = null, array $properties = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }
}
