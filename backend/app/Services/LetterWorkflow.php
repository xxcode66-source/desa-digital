<?php
namespace App\Services;

use App\Models\Letter;
use App\Models\User;
use App\Support\LetterStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu-satunya tempat transisi status surat terjadi (PEMBANGUNAN.md 4.2 & 5).
 * Memastikan transisi legal, menulis letter_status_logs + audit log, menyalakan
 * penomoran + token verifikasi publik saat surat selesai.
 */
class LetterWorkflow
{
    public function __construct(
        private readonly LetterNumberGenerator $numbers,
        private readonly LetterPdfService $pdf,
    ) {
    }

    public function transition(
        Letter $letter,
        string $to,
        ?User $actor = null,
        ?string $note = null,
        array $flagged = [],
    ): Letter {
        $letter = DB::transaction(function () use ($letter, $to, $actor, $note, $flagged): Letter {
            $from = $letter->status;

            if (!LetterStatus::canTransition($from, $to)) {
                throw new \InvalidArgumentException("Transisi status tidak sah: $from -> $to.");
            }

            if ($to === LetterStatus::REJECTED && blank($note)) {
                throw new \InvalidArgumentException('Penolakan surat wajib menyertakan alasan.');
            }

            $attrs = ['status' => $to];

            if ($to === LetterStatus::SUBMITTED) {
                $attrs['submitted_at'] = now();
            }

            if ($to === LetterStatus::DONE) {
                $attrs['number'] ??= $letter->number ?: $this->numbers->next($letter->village);
                $attrs['public_token'] = $letter->public_token ?: (string) Str::uuid();
                $attrs['completed_at'] = now();
            }

            if ($note !== null) {
                $attrs['note'] = $note;
            }

            $letter->fill($attrs)->save();

            $letter->statusLogs()->create([
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'flagged_fields_json' => $flagged ?: null,
                'actor_id' => $actor?->id,
            ]);

            AuditLogger::record(
                "letter.status.{$to}",
                $letter,
                "Status surat diubah: " . LetterStatus::label($from) . ' -> ' . LetterStatus::label($to),
                array_filter([
                    'from' => $from,
                    'to' => $to,
                    'actor_id' => $actor?->id,
                    'number' => $attrs['number'] ?? null,
                ]),
            );

            return $letter->refresh();
        });

        // PDF dibuat DI LUAR transaksi: status `selesai` wajib tetap tersimpan walaupun
        // render/tulis file gagal (LetterPdfService menelan error & mencatat log).
        if ($to === LetterStatus::DONE) {
            $this->pdf->generate($letter);
        }

        return $letter;
    }
}
