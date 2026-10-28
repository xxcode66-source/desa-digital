<?php
namespace App\Services;

use App\Models\PbbClaim;
use App\Models\User;
use App\Support\PbbStatus;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi klaim kepemilikan NOP oleh petugas (PEMBANGUNAN.md 4.3 & 5).
 * Hanya operator/kades/admin satu desa yang menilai (dijaga policy saat dipanggil). Saat disetujui,
 * klaim menimpa review_* dan tercatat di audit; warga baru melihat NOP di "NOP saya" setelah approved.
 */
class PbbClaimService
{
    public function approve(PbbClaim $claim, User $reviewer, ?string $note = null): PbbClaim
    {
        return DB::transaction(function () use ($claim, $reviewer, $note): PbbClaim {
            $claim->forceFill([
                'status' => PbbStatus::CLAIM_APPROVED,
                'reviewed_by' => $reviewer->id,
                'review_note' => $note,
                'reviewed_at' => now(),
            ])->save();

            AuditLogger::record('pbb.claim.approved', $claim, 'Klaim kepemilikan NOP disetujui.', [
                'reviewer_id' => $reviewer->id, 'nop' => $claim->object?->nop,
            ]);

            return $claim->fresh();
        });
    }

    public function reject(PbbClaim $claim, User $reviewer, string $note): PbbClaim
    {
        if (blank($note)) {
            throw new \InvalidArgumentException('Penolakan klaim wajib menyertakan alasan.');
        }

        return DB::transaction(function () use ($claim, $reviewer, $note): PbbClaim {
            $claim->forceFill([
                'status' => PbbStatus::CLAIM_REJECTED,
                'reviewed_by' => $reviewer->id,
                'review_note' => $note,
                'reviewed_at' => now(),
            ])->save();

            AuditLogger::record('pbb.claim.rejected', $claim, 'Klaim kepemilikan NOP ditolak: '.$note, [
                'reviewer_id' => $reviewer->id, 'nop' => $claim->object?->nop,
            ]);

            return $claim->fresh();
        });
    }
}
