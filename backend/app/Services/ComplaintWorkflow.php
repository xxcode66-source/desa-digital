<?php
namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\User;
use App\Support\ComplaintStatus;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat perubahan status & balasan pengaduan terjadi (PEMBANGUNAN.md 4.5 & 5).
 * Memastikan transisi legal, menulis complaint_replies (riwayat + percakapan) + audit log,
 * dan menandai resolved_at saat selesai/ditolak.
 */
class ComplaintWorkflow
{
    /** Transisi status (dipakai petugas di panel). Penolakan wajib alasan. */
    public function transition(
        Complaint $complaint,
        string $to,
        ?User $actor = null,
        ?string $note = null,
    ): Complaint {
        return DB::transaction(function () use ($complaint, $to, $actor, $note): Complaint {
            $from = $complaint->status;

            if (!ComplaintStatus::canTransition($from, $to)) {
                throw new \InvalidArgumentException("Transisi status tidak sah: $from -> $to.");
            }

            if ($to === ComplaintStatus::REJECTED && blank($note)) {
                throw new \InvalidArgumentException('Penolakan pengaduan wajib menyertakan alasan.');
            }

            $attrs = ['status' => $to];
            if (ComplaintStatus::isClosed($to)) {
                $attrs['resolved_at'] = now();
            }

            $complaint->fill($attrs)->save();

            $complaint->replies()->create([
                'user_id' => $actor?->id,
                'body' => $note,
                'from_status' => $from,
                'to_status' => $to,
                'is_staff' => $actor?->isStaff() ?? false,
            ]);

            AuditLogger::record(
                "complaint.status.{$to}",
                $complaint,
                'Status pengaduan diubah: ' . ComplaintStatus::label($from) . ' -> ' . ComplaintStatus::label($to),
                array_filter(['from' => $from, 'to' => $to, 'actor_id' => $actor?->id]),
            );

            return $complaint->refresh();
        });
    }

    /** Balasan percakapan (petugas atau warga) tanpa mengubah status. */
    public function reply(Complaint $complaint, string $body, User $actor): ComplaintReply
    {
        $reply = $complaint->replies()->create([
            'user_id' => $actor->id,
            'body' => $body,
            'is_staff' => $actor->isStaff(),
        ]);

        AuditLogger::record(
            'complaint.replied',
            $complaint,
            'Balasan pada pengaduan.',
            ['actor_id' => $actor->id, 'is_staff' => $actor->isStaff()],
        );

        return $reply;
    }
}
