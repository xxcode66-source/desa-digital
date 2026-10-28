<?php
// Unit test mesin status & penyamaran nama PBB (PEMBANGUNAN.md 4.3 & 5): transisi tagihan
// hanya lewat jalur sah (belum_bayar -> disetor_pending -> lunas), lunas bersifat final,
// penyamaran nama melindungi data pribadi utk cek NOP, dan tiap status berlabel Indonesia.

namespace Tests\Unit;

use App\Support\PbbStatus;
use PHPUnit\Framework\TestCase;

class PbbStatusTest extends TestCase
{
    public static function billTransitionProvider(): array
    {
        return [
            'belum -> disetor' => [PbbStatus::BILL_UNPAID, PbbStatus::BILL_PENDING, true],
            'disetor -> lunas' => [PbbStatus::BILL_PENDING, PbbStatus::BILL_PAID, true],
            'disetor -> belum (ditolak)' => [PbbStatus::BILL_PENDING, PbbStatus::BILL_UNPAID, true],
            'belum -> lunas (dilompat, ilegal)' => [PbbStatus::BILL_UNPAID, PbbStatus::BILL_PAID, false],
            'lunas -> apa pun (final)' => [PbbStatus::BILL_PAID, PbbStatus::BILL_UNPAID, false],
            'lunas -> disetor (mundur, ilegal)' => [PbbStatus::BILL_PAID, PbbStatus::BILL_PENDING, false],
            'belum -> belum (identik, tak diizinkan gerbang)' => [PbbStatus::BILL_UNPAID, PbbStatus::BILL_UNPAID, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('billTransitionProvider')]
    public function test_bill_transitions(string $from, string $to, bool $expected): void
    {
        $this->assertSame($expected, PbbStatus::canBillTransition($from, $to));
    }

    public function test_arrears_is_everything_not_lunas(): void
    {
        $this->assertTrue(PbbStatus::isBillArrears(PbbStatus::BILL_UNPAID));
        $this->assertTrue(PbbStatus::isBillArrears(PbbStatus::BILL_PENDING));
        $this->assertFalse(PbbStatus::isBillArrears(PbbStatus::BILL_PAID));
        $this->assertTrue(PbbStatus::isBillPaid(PbbStatus::BILL_PAID));
    }

    public function test_mask_name_keeps_first_letter_per_word(): void
    {
        $this->assertSame('B*** S******', PbbStatus::maskName('Budi Santoso'));
        // Satu huruf tetap utuh; spasi berlebih diringkas.
        $this->assertSame('A B**', PbbStatus::maskName('  A  Boy '));
        $this->assertSame('-', PbbStatus::maskName(null));
        $this->assertSame('-', PbbStatus::maskName('   '));
    }

    public function test_mask_name_never_leaks_full_original(): void
    {
        $masked = PbbStatus::maskName('Sri Wahyuni Putri');
        $this->assertStringNotContainsString('Sri', $masked);
        $this->assertStringNotContainsString('Wahyuni', $masked);
    }

    public function test_every_status_has_indonesian_label(): void
    {
        foreach (PbbStatus::BILL_ALL as $s) {
            $this->assertNotSame($s, PbbStatus::label($s), "Label tagihan $s belum dibuat.");
        }
        foreach (PbbStatus::CLAIM_ALL as $s) {
            $this->assertNotSame($s, PbbStatus::label($s), "Label klaim $s belum dibuat.");
        }
        foreach (PbbStatus::DEPOSIT_ALL as $s) {
            $this->assertNotSame($s, PbbStatus::label($s), "Label setoran $s belum dibuat.");
        }
    }
}
