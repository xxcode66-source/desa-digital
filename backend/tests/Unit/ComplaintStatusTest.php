<?php
// Unit test mesin status pengaduan (PEMBANGUNAN.md 4.5): hanya transisi legal yang sah,
// status akhir menutup percakapan, dan setiap status punya label bahasa Indonesia.

namespace Tests\Unit;

use App\Support\ComplaintStatus;
use PHPUnit\Framework\TestCase;

class ComplaintStatusTest extends TestCase
{
    public static function legalProvider(): array
    {
        return [
            'diterima -> diproses' => [ComplaintStatus::ACCEPTED, ComplaintStatus::IN_PROGRESS, true],
            'diterima -> selesai' => [ComplaintStatus::ACCEPTED, ComplaintStatus::DONE, true],
            'diterima -> ditolak' => [ComplaintStatus::ACCEPTED, ComplaintStatus::REJECTED, true],
            'diproses -> selesai' => [ComplaintStatus::IN_PROGRESS, ComplaintStatus::DONE, true],
            'diproses -> ditolak' => [ComplaintStatus::IN_PROGRESS, ComplaintStatus::REJECTED, true],
            'selesai -> apa pun (ilegal)' => [ComplaintStatus::DONE, ComplaintStatus::IN_PROGRESS, false],
            'ditolak -> apa pun (ilegal)' => [ComplaintStatus::REJECTED, ComplaintStatus::ACCEPTED, false],
            'diproses -> diterima (mundur, ilegal)' => [ComplaintStatus::IN_PROGRESS, ComplaintStatus::ACCEPTED, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('legalProvider')]
    public function test_transitions(string $from, string $to, bool $expected): void
    {
        $this->assertSame($expected, ComplaintStatus::canTransition($from, $to));
    }

    public function test_closed_statuses(): void
    {
        $this->assertTrue(ComplaintStatus::isClosed(ComplaintStatus::DONE));
        $this->assertTrue(ComplaintStatus::isClosed(ComplaintStatus::REJECTED));
        $this->assertFalse(ComplaintStatus::isClosed(ComplaintStatus::ACCEPTED));
        $this->assertFalse(ComplaintStatus::isClosed(ComplaintStatus::IN_PROGRESS));
    }

    public function test_every_status_has_indonesian_label(): void
    {
        foreach (ComplaintStatus::ALL as $status) {
            $this->assertNotSame($status, ComplaintStatus::label($status), "Label $status belum dibuat.");
        }
    }
}
