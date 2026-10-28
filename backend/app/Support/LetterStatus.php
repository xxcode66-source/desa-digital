<?php
namespace App\Support;

/**
 * Mesin status surat Fase 2 (PEMBANGUNAN.md 4.2). Sumber kebenaran transisi yang sah:
 *   draft -> diajukan -> (verifikasi_rt) -> verifikasi_operator -> menunggu_ttd_kades -> selesai
 *   (perlu_perbaikan <-> diajukan)   |   -> ditolak (wajib alasan)
 * Transisi ilegal ditolak di service/controller, bukan hanya di UI.
 */
class LetterStatus
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'diajukan';
    public const RT = 'verifikasi_rt';
    public const OPERATOR = 'verifikasi_operator';
    public const REPAIR = 'perlu_perbaikan';
    public const SIGN = 'menunggu_ttd_kades';
    public const DONE = 'selesai';
    public const REJECTED = 'ditolak';

    public const ALL = [
        self::DRAFT, self::SUBMITTED, self::RT, self::OPERATOR,
        self::REPAIR, self::SIGN, self::DONE, self::REJECTED,
    ];

    /** Label bahasa Indonesia sederhana (PEMBANGUNAN.md 6). */
    public const LABELS = [
        self::DRAFT => 'Draf',
        self::SUBMITTED => 'Diajukan',
        self::RT => 'Menunggu pengantar RT/RW',
        self::OPERATOR => 'Sedang diverifikasi operator',
        self::REPAIR => 'Perlu perbaikan',
        self::SIGN => 'Menunggu tanda tangan Kades',
        self::DONE => 'Selesai (siap cetak)',
        self::REJECTED => 'Ditolak',
    ];

    /** Peta transisi legal from => [to, ...]. */
    private const TRANSITIONS = [
        self::DRAFT => [self::SUBMITTED],
        self::SUBMITTED => [self::RT, self::OPERATOR, self::REPAIR, self::REJECTED],
        self::RT => [self::OPERATOR, self::REPAIR, self::REJECTED],
        self::OPERATOR => [self::SIGN, self::REPAIR, self::REJECTED],
        self::REPAIR => [self::SUBMITTED],
        self::SIGN => [self::DONE],
        self::DONE => [],
        self::REJECTED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Status tempat warga boleh mengubah & mengirim ulang datanya. */
    public static function isCitizenEditable(string $status): bool
    {
        return in_array($status, [self::DRAFT, self::REPAIR], true);
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }
}
