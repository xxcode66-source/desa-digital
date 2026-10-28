<?php
// Fase 4 — Mesin status & util PBB (PEMBANGUNAN.md 4.3 & 5). Memusatkan aturan transisi
// tagihan/klaim/setoran agar tidak bisa diubah sembarangan dari banyak tempat, plus
// penyamaran nama wajib pajak untuk tampilan cek NOP publik (lindungi data pribadi).

namespace App\Support;

final class PbbStatus
{
    // ---- Status tagihan (pbb_bills) ----
    public const BILL_UNPAID = 'belum_bayar';
    public const BILL_PENDING = 'disetor_pending'; // sudah disetor RT/kolektor, menunggu ACC bendahara
    public const BILL_PAID = 'lunas'; // di-ACC desa (status internal, bukan bukti pelunasan resmi)

    public const BILL_ALL = [self::BILL_UNPAID, self::BILL_PENDING, self::BILL_PAID];

    /** Tagihan: belum_bayar <-> disetor_pending -> lunas (lunas final). */
    private const BILL_TRANSITIONS = [
        self::BILL_UNPAID => [self::BILL_PENDING],
        self::BILL_PENDING => [self::BILL_UNPAID, self::BILL_PAID],
        self::BILL_PAID => [],
    ];

    // ---- Status klaim kepemilikan (pbb_claims) ----
    public const CLAIM_PENDING = 'pending';
    public const CLAIM_APPROVED = 'approved';
    public const CLAIM_REJECTED = 'rejected';
    public const CLAIM_ALL = [self::CLAIM_PENDING, self::CLAIM_APPROVED, self::CLAIM_REJECTED];

    // ---- Status batch setoran (pbb_deposit_batches) ----
    public const DEPOSIT_PENDING = 'pending';
    public const DEPOSIT_PARTIAL = 'partially_approved';
    public const DEPOSIT_APPROVED = 'approved';
    public const DEPOSIT_REJECTED = 'rejected';
    public const DEPOSIT_ALL = [self::DEPOSIT_PENDING, self::DEPOSIT_PARTIAL, self::DEPOSIT_APPROVED, self::DEPOSIT_REJECTED];

    // ---- Status item setoran (pbb_deposit_items) — sama dgn klaim: pending/approved/rejected ----
    public const ITEM_PENDING = self::CLAIM_PENDING;
    public const ITEM_APPROVED = self::CLAIM_APPROVED;
    public const ITEM_REJECTED = self::CLAIM_REJECTED;

    private const LABELS = [
        self::BILL_UNPAID => 'Belum bayar',
        self::BILL_PENDING => 'Disetor, menunggu ACC',
        self::BILL_PAID => 'Lunas (verifikasi desa)',
        self::CLAIM_PENDING => 'Menunggu verifikasi',
        self::CLAIM_APPROVED => 'Disetujui',
        self::CLAIM_REJECTED => 'Ditolak',
        self::DEPOSIT_PENDING => 'Menunggu ACC bendahara',
        self::DEPOSIT_PARTIAL => 'Di-ACC sebagian',
        self::DEPOSIT_APPROVED => 'Disetujui',
        self::DEPOSIT_REJECTED => 'Ditolak',
    ];

    public static function canBillTransition(string $from, string $to): bool
    {
        return in_array($to, self::BILL_TRANSITIONS[$from] ?? [], true);
    }

    public static function isBillPaid(string $status): bool
    {
        return $status === self::BILL_PAID;
    }

    /** Apakah tagihan masih jadi tunggakan (belum lunas). */
    public static function isBillArrears(string $status): bool
    {
        return $status !== self::BILL_PAID;
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    /**
     * Samarkan nama WP utk tampilan cek NOP: tiap kata disisakan huruf pertamanya.
     * "Budi Santoso" -> "B*** S*****" (jumlah bintang mengikuti panjang kata).
     */
    public static function maskName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '-';
        }

        return implode(' ', array_map(
            fn (string $word) => mb_strlen($word) <= 1
                ? $word
                : mb_substr($word, 0, 1) . str_repeat('*', max(1, mb_strlen($word) - 1)),
            preg_split('/\s+/u', $name) ?: [],
        ));
    }
}
