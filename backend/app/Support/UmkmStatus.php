<?php
// Fase 5 — Mesin status UMKM (PEMBANGUNAN.md 4.4). Memusatkan transisi sah toko & laporan
// moderasi agar tidak bisa diubah sembarangan dari banyak tempat (API vs panel Filament).
namespace App\Support;

final class UmkmStatus
{
    // ---- Status toko (shops) ----
    public const SHOP_PENDING = 'pending';   // diajukan pelaku, menunggu verifikasi operator
    public const SHOP_ACTIVE = 'aktif';      // lolos verifikasi, tampil di marketplace
    public const SHOP_REJECTED = 'ditolak';  // ditolak operator (wajib alasan) — bisa diajukan ulang
    public const SHOP_SUSPENDED = 'nonaktif'; // dibekukan/disembunyikan operator (mis. pasca laporan)

    public const SHOP_ALL = [self::SHOP_PENDING, self::SHOP_ACTIVE, self::SHOP_REJECTED, self::SHOP_SUSPENDED];

    /** pending -> aktif/ditolak; aktif <-> nonaktif; ditolak -> pending (ajukan ulang). */
    private const SHOP_TRANSITIONS = [
        self::SHOP_PENDING => [self::SHOP_ACTIVE, self::SHOP_REJECTED],
        self::SHOP_ACTIVE => [self::SHOP_SUSPENDED],
        self::SHOP_SUSPENDED => [self::SHOP_ACTIVE],
        self::SHOP_REJECTED => [self::SHOP_PENDING],
    ];

    // ---- Status laporan moderasi (product_reports) ----
    public const REPORT_NEW = 'baru';
    public const REPORT_RESOLVED = 'ditindak';   // ditindaklanjuti (mis. toko/produk disembunyikan)
    public const REPORT_DISMISSED = 'diabaikan';  // dinilai tidak melanggar

    public const REPORT_ALL = [self::REPORT_NEW, self::REPORT_RESOLVED, self::REPORT_DISMISSED];

    private const LABELS = [
        self::SHOP_PENDING => 'Menunggu verifikasi',
        self::SHOP_ACTIVE => 'Aktif',
        self::SHOP_REJECTED => 'Ditolak',
        self::SHOP_SUSPENDED => 'Dinonaktifkan',
        self::REPORT_NEW => 'Laporan baru',
        self::REPORT_RESOLVED => 'Ditindaklanjuti',
        self::REPORT_DISMISSED => 'Diabaikan',
    ];

    public static function canShopTransition(string $from, string $to): bool
    {
        return in_array($to, self::SHOP_TRANSITIONS[$from] ?? [], true);
    }

    /** Hanya toko aktif & tidak disembunyikan yang boleh muncul ke publik. */
    public static function isPubliclyVisible(string $status, bool $isHidden): bool
    {
        return $status === self::SHOP_ACTIVE && !$isHidden;
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }
}
