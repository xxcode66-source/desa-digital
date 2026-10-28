<?php
// Unit test mesin status UMKM (PEMBANGUNAN.md 4.4): hanya transisi toko legal yang sah,
// visibilitas publik menuntut status aktif & tidak disembunyikan, laporan wajib berlabel ID.

namespace Tests\Unit;

use App\Support\UmkmStatus;
use PHPUnit\Framework\TestCase;

class UmkmStatusTest extends TestCase
{
    public static function shopTransitionProvider(): array
    {
        return [
            'pending -> aktif' => [UmkmStatus::SHOP_PENDING, UmkmStatus::SHOP_ACTIVE, true],
            'pending -> ditolak' => [UmkmStatus::SHOP_PENDING, UmkmStatus::SHOP_REJECTED, true],
            'aktif -> nonaktif' => [UmkmStatus::SHOP_ACTIVE, UmkmStatus::SHOP_SUSPENDED, true],
            'nonaktif -> aktif' => [UmkmStatus::SHOP_SUSPENDED, UmkmStatus::SHOP_ACTIVE, true],
            'ditolak -> pending (ajukan ulang)' => [UmkmStatus::SHOP_REJECTED, UmkmStatus::SHOP_PENDING, true],
            'aktif -> pending (ilegal)' => [UmkmStatus::SHOP_ACTIVE, UmkmStatus::SHOP_PENDING, false],
            'aktif -> ditolak (ilegal)' => [UmkmStatus::SHOP_ACTIVE, UmkmStatus::SHOP_REJECTED, false],
            'ditolak -> aktif (harus pending dulu)' => [UmkmStatus::SHOP_REJECTED, UmkmStatus::SHOP_ACTIVE, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shopTransitionProvider')]
    public function test_shop_transitions(string $from, string $to, bool $expected): void
    {
        $this->assertSame($expected, UmkmStatus::canShopTransition($from, $to));
    }

    public function test_public_visibility_requires_active_and_not_hidden(): void
    {
        $this->assertTrue(UmkmStatus::isPubliclyVisible(UmkmStatus::SHOP_ACTIVE, false));
        $this->assertFalse(UmkmStatus::isPubliclyVisible(UmkmStatus::SHOP_ACTIVE, true), 'Toko disembunyikan tidak tampil.');
        $this->assertFalse(UmkmStatus::isPubliclyVisible(UmkmStatus::SHOP_PENDING, false));
        $this->assertFalse(UmkmStatus::isPubliclyVisible(UmkmStatus::SHOP_SUSPENDED, false));
        $this->assertFalse(UmkmStatus::isPubliclyVisible(UmkmStatus::SHOP_REJECTED, false));
    }

    public function test_every_status_has_indonesian_label(): void
    {
        foreach ([...UmkmStatus::SHOP_ALL, ...UmkmStatus::REPORT_ALL] as $status) {
            $this->assertNotSame($status, UmkmStatus::label($status), "Label $status belum dibuat.");
        }
    }
}
