<?php
namespace App\Support;

/**
 * Mesin status pengaduan Fase 3 (PEMBANGUNAN.md 4.5). Sumber kebenaran transisi sah:
 *   diterima -> diproses -> selesai
 *      \        \            -> ditolak (wajib alasan)
 *      -> ditolak (langsung, wajib alasan)
 * Transisi ilegal ditolak di service, bukan hanya di UI.
 */
class ComplaintStatus
{
    public const ACCEPTED = 'diterima';
    public const IN_PROGRESS = 'diproses';
    public const DONE = 'selesai';
    public const REJECTED = 'ditolak';

    public const ALL = [
        self::ACCEPTED, self::IN_PROGRESS, self::DONE, self::REJECTED,
    ];

    /** Label bahasa Indonesia sederhana (PEMBANGUNAN.md 6). */
    public const LABELS = [
        self::ACCEPTED => 'Laporan diterima',
        self::IN_PROGRESS => 'Sedang diproses',
        self::DONE => 'Selesai',
        self::REJECTED => 'Ditolak',
    ];

    /** Peta transisi legal from => [to, ...]. */
    private const TRANSITIONS = [
        self::ACCEPTED => [self::IN_PROGRESS, self::DONE, self::REJECTED],
        self::IN_PROGRESS => [self::DONE, self::REJECTED],
        self::DONE => [],
        self::REJECTED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Status akhir: warga tidak bisa lagi membalas/mengubah. */
    public static function isClosed(string $status): bool
    {
        return in_array($status, [self::DONE, self::REJECTED], true);
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }
}
