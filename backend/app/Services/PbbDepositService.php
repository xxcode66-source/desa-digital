<?php
namespace App\Services;

use App\Models\PbbBill;
use App\Models\PbbDepositBatch;
use App\Models\PbbDepositItem;
use App\Models\User;
use App\Support\PbbStatus;
use Illuminate\Support\Facades\DB;

/**
 * Alur setoran PBB dari RT/Kolektor & ACC bendahara (PEMBANGUNAN.md 4.3 & 5).
 *
 * Prinsip: TIDAK ada pembayaran di aplikasi. Setoran hanya mencatat uang FISIK yang
 * disetor kolektor ke bendahara. ACC bendahara bersifat sebagian (per item), dan
 * status 'lunas' adalah verifikasi INTERNAL desa — bukan bukti pelunasan resmi.
 *
 * Transisi tagihan dijaga lewat PbbStatus: belum_bayar -> disetor_pending (saat disetor),
 * disetor_pending -> lunas (item di-ACC) atau -> belum_bayar (item ditolak/ditangguhkan).
 */
class PbbDepositService
{
    /**
     * RT/kolektor membuat slip setoran berisi beberapa tagihan (NOP+tahun).
     *
     * @param  array<int, array{pbb_bill_id:int, amount_claimed:float|string}>  $items
     */
    public function submitBatch(User $collector, string $title, array $items, ?string $note = null): PbbDepositBatch
    {
        if (!$collector->isCollector()) {
            abort(403, 'Hanya RT/kolektor yang bisa menyetor PBB.');
        }

        return DB::transaction(function () use ($collector, $title, $items, $note): PbbDepositBatch {
            $batch = PbbDepositBatch::create([
                'village_id' => $collector->village_id,
                'collector_id' => $collector->id,
                'title' => $title,
                'note' => $note,
                'status' => PbbStatus::DEPOSIT_PENDING,
                'total_amount' => 0,
                'item_count' => 0,
            ]);

            $total = 0.0;
            $count = 0;

            foreach ($items as $line) {
                $bill = PbbBill::where('village_id', $collector->village_id)
                    ->where('id', $line['pbb_bill_id'])
                    ->first();

                if (!$bill) {
                    throw new \InvalidArgumentException("Tagihan #{$line['pbb_bill_id']} tidak ditemukan untuk desa ini.");
                }

                // Hanya tagihan belum bayar yang boleh disetor (cegah setor duplikat lunas/pending).
                if ($bill->status !== PbbStatus::BILL_UNPAID) {
                    throw new \InvalidArgumentException(
                        'Tagihan NOP '.$bill->object->nop." ({$bill->year}) statusnya ".$bill->statusLabel().', tidak bisa disetor lagi.'
                    );
                }

                $amountClaimed = (float) ($line['amount_claimed'] ?? $bill->total());

                $batch->items()->create([
                    'pbb_bill_id' => $bill->id,
                    'amount_claimed' => $amountClaimed,
                    'status' => PbbStatus::ITEM_PENDING,
                ]);

                // Tandai tagihan sedang dalam proses setoran.
                $this->moveBill($bill, PbbStatus::BILL_PENDING, $collector);

                $total += $amountClaimed;
                $count++;
            }

            if ($count === 0) {
                throw new \InvalidArgumentException('Setoran harus memuat minimal satu tagihan.');
            }

            $batch->forceFill(['total_amount' => $total, 'item_count' => $count])->save();

            AuditLogger::record(
                'pbb.deposit.submitted', $batch,
                "Kolektor menyetor {$count} tagihan (total Rp ".number_format($total, 0, ',', '.').' ).',
                ['collector_id' => $collector->id],
            );

            return $batch->fresh('items.bill.object');
        });
    }

    /** Bendahara ACC satu item -> tagihan jadi lunas (internal). */
    public function approveItem(PbbDepositItem $item, User $treasurer, ?string $note = null): PbbDepositItem
    {
        return DB::transaction(function () use ($item, $treasurer, $note): PbbDepositItem {
            $this->assertTreasurer($treasurer, $item);

            if ($item->status !== PbbStatus::ITEM_PENDING) {
                throw new \InvalidArgumentException('Item ini sudah selesai dinilai.');
            }

            $item->forceFill([
                'status' => PbbStatus::ITEM_APPROVED,
                'reviewed_by' => $treasurer->id,
                'review_note' => $note,
                'reviewed_at' => now(),
            ])->save();

            if ($item->bill) {
                $this->moveBill($item->bill, PbbStatus::BILL_PAID, $treasurer, markPaid: true);
            }

            $this->recalcBatch($item->pbb_deposit_batch_id);

            AuditLogger::record('pbb.deposit.item.approved', $item, 'Item setoran di-ACC, tagihan jadi lunas (internal).', [
                'treasurer_id' => $treasurer->id,
            ]);

            return $item->fresh();
        });
    }

    /** Bendahara tolak satu item -> tagihan kembali ke belum_bayar. */
    public function rejectItem(PbbDepositItem $item, User $treasurer, string $note): PbbDepositItem
    {
        return DB::transaction(function () use ($item, $treasurer, $note): PbbDepositItem {
            $this->assertTreasurer($treasurer, $item);

            if (blank($note)) {
                throw new \InvalidArgumentException('Penolakan item setoran wajib menyertakan alasan.');
            }

            if ($item->status !== PbbStatus::ITEM_PENDING) {
                throw new \InvalidArgumentException('Item ini sudah selesai dinilai.');
            }

            $item->forceFill([
                'status' => PbbStatus::ITEM_REJECTED,
                'reviewed_by' => $treasurer->id,
                'review_note' => $note,
                'reviewed_at' => now(),
            ])->save();

            if ($item->bill) {
                $this->moveBill($item->bill, PbbStatus::BILL_UNPAID, $treasurer);
            }

            $this->recalcBatch($item->pbb_deposit_batch_id);

            AuditLogger::record('pbb.deposit.item.rejected', $item, 'Item setoran ditolak: '.$note, [
                'treasurer_id' => $treasurer->id,
            ]);

            return $item->fresh();
        });
    }

    /**
     * Pindahkan status tagihan lewat gerbang legal PbbStatus (transisi tak sah -> error).
     */
    private function moveBill(PbbBill $bill, string $to, User $actor, bool $markPaid = false): void
    {
        $from = $bill->status;
        if ($from === $to) {
            return;
        }

        if (!PbbStatus::canBillTransition($from, $to)) {
            throw new \InvalidArgumentException("Transisi status tagihan tidak sah: $from -> $to.");
        }

        $attrs = ['status' => $to];
        if ($markPaid || $to === PbbStatus::BILL_PAID) {
            $attrs['paid_at'] = now();
        }

        $bill->forceFill($attrs)->save();
    }

    /** Turunkan status batch dari kondisi item-itemnya. */
    private function recalcBatch(int $batchId): void
    {
        $batch = PbbDepositBatch::with('items')->findOrFail($batchId);
        $statuses = $batch->items->pluck('status');
        $pending = $statuses->filter(fn ($s) => $s === PbbStatus::ITEM_PENDING)->count();
        $approved = $statuses->filter(fn ($s) => $s === PbbStatus::ITEM_APPROVED)->count();
        $rejected = $statuses->filter(fn ($s) => $s === PbbStatus::ITEM_REJECTED)->count();
        $total = $statuses->count();

        $status = match (true) {
            $pending > 0 => PbbStatus::DEPOSIT_PENDING,
            $approved === $total && $total > 0 => PbbStatus::DEPOSIT_APPROVED,
            $rejected === $total => PbbStatus::DEPOSIT_REJECTED,
            $approved > 0 => PbbStatus::DEPOSIT_PARTIAL, // campuran approved+rejected
            default => PbbStatus::DEPOSIT_PENDING,
        };

        $attrs = ['status' => $status];
        if ($pending === 0) {
            $attrs['reviewed_at'] = now();
        }
        $batch->forceFill($attrs)->save();
    }

    private function assertTreasurer(User $actor, PbbDepositItem $item): void
    {
        $batch = $item->batch;
        abort_unless($batch !== null, 404);

        $allowed = $actor->hasAnyRole(['bendahara_pbb', 'admin'])
            && ($actor->isAdmin() || $actor->village_id === $batch->village_id);

        if (!$allowed) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Hanya bendahara PBB desa ini yang bisa menilai setoran.');
        }
    }
}
