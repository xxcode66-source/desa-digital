<?php
// Unit test mesin status surat (PEMBANGUNAN.md 4.2): hanya transisi legal yang sah,
// penolakan wajib alasan, dan status tempat warga boleh memperbaiki datanya.

namespace Tests\Unit;

use App\Support\LetterStatus;
use PHPUnit\Framework\TestCase;

class LetterStatusTest extends TestCase
{
    public static function legalProvider(): array
    {
        return [
            'diajukan -> rt' => [LetterStatus::SUBMITTED, LetterStatus::RT, true],
            'diajukan -> operator' => [LetterStatus::SUBMITTED, LetterStatus::OPERATOR, true],
            'rt -> operator' => [LetterStatus::RT, LetterStatus::OPERATOR, true],
            'operator -> ttd' => [LetterStatus::OPERATOR, LetterStatus::SIGN, true],
            'ttd -> selesai' => [LetterStatus::SIGN, LetterStatus::DONE, true],
            'perbaikan -> diajukan' => [LetterStatus::REPAIR, LetterStatus::SUBMITTED, true],
            'selesai -> operator (ilegal)' => [LetterStatus::DONE, LetterStatus::OPERATOR, false],
            'ditolak -> apa pun (ilegal)' => [LetterStatus::REJECTED, LetterStatus::SUBMITTED, false],
            'lompat: diajukan -> ttd' => [LetterStatus::SUBMITTED, LetterStatus::SIGN, false],
            'lompat: operator -> selesai' => [LetterStatus::OPERATOR, LetterStatus::DONE, false],
            'rt -> ttd (ilegal)' => [LetterStatus::RT, LetterStatus::SIGN, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('legalProvider')]
    public function test_transitions(string $from, string $to, bool $expected): void
    {
        $this->assertSame($expected, LetterStatus::canTransition($from, $to));
    }

    public function test_citizen_can_only_edit_draft_and_repair(): void
    {
        $this->assertTrue(LetterStatus::isCitizenEditable(LetterStatus::DRAFT));
        $this->assertTrue(LetterStatus::isCitizenEditable(LetterStatus::REPAIR));

        foreach ([LetterStatus::SUBMITTED, LetterStatus::RT, LetterStatus::OPERATOR,
            LetterStatus::SIGN, LetterStatus::DONE, LetterStatus::REJECTED] as $locked) {
            $this->assertFalse(LetterStatus::isCitizenEditable($locked), "Status $locked tidak boleh diedit warga.");
        }
    }

    public function test_every_status_has_indonesian_label(): void
    {
        foreach (LetterStatus::ALL as $status) {
            $this->assertNotSame($status, LetterStatus::label($status), "Label $status belum dibuat.");
        }
    }
}
