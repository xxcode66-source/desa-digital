<?php
// Test LetterWorkflow sebagai satu-satunya pintu transisi status (PEMBANGUNAN.md 4.2 & 5):
// transisi ilegal ditolak, penolakan wajib alasan, selesai memicu nomor + token,
// dan setiap transisi menulis log status + audit log.

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\User;
use App\Models\Village;
use App\Services\LetterWorkflow;
use App\Support\LetterStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LetterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private LetterWorkflow $wf;
    private Village $village;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local'); // DONE memicu pembuatan PDF; jangan tulis disk asli.

        $this->wf = app(LetterWorkflow::class);
        $this->village = Village::factory()->create();
        $this->operator = User::factory()->create(['village_id' => $this->village->id]);
        $this->operator->assignRole('operator');
    }

    private function letter(string $status = LetterStatus::SUBMITTED): Letter
    {
        return Letter::factory()->create([
            'village_id' => $this->village->id,
            'user_id' => $this->operator->id,
            'status' => $status,
        ]);
    }

    public function test_illegal_transition_is_rejected_and_status_unchanged(): void
    {
        $letter = $this->letter(LetterStatus::SUBMITTED);

        $this->expectException(\InvalidArgumentException::class);
        $this->wf->transition($letter, LetterStatus::DONE, $this->operator);

        $this->assertSame(LetterStatus::SUBMITTED, $letter->fresh()->status);
    }

    public function test_reject_requires_reason(): void
    {
        $letter = $this->letter();

        $this->expectException(\InvalidArgumentException::class);
        $this->wf->transition($letter, LetterStatus::REJECTED, $this->operator, null);
    }

    public function test_happy_path_writes_logs_and_produces_number_on_done(): void
    {
        $letter = $this->letter();

        $letter = $this->wf->transition($letter, LetterStatus::RT, $this->operator);
        $letter = $this->wf->transition($letter, LetterStatus::OPERATOR, $this->operator);
        $letter = $this->wf->transition($letter, LetterStatus::SIGN, $this->operator, 'Data sesuai.');
        $letter = $this->wf->transition($letter, LetterStatus::DONE, $this->operator);

        $this->assertSame(LetterStatus::DONE, $letter->status);
        $this->assertMatchesRegularExpression('#^470/\d{3}/DS/\d{1,2}/\d{4}$#', $letter->number);
        $this->assertNotNull($letter->public_token);
        $this->assertNotNull($letter->completed_at);

        // 4 transisi => minimal 4 log status (belum termasuk log awal pabrik belum ada).
        $this->assertSame(4, $letter->statusLogs()->count());
        $this->assertSame(4, $letter->statusLogs()->where('actor_id', $this->operator->id)->count());

        // Audit log tercatat untuk setiap perubahan status.
        $this->assertSame(4, \App\Models\AuditLog::where('action', 'like', 'letter.status.%')->count());
    }

    public function test_repair_records_flagged_fields(): void
    {
        $letter = $this->letter();

        $letter = $this->wf->transition(
            $letter, LetterStatus::REPAIR, $this->operator,
            'Alamat tidak lengkap.', ['alamat', 'keperluan'],
        );

        $this->assertSame(LetterStatus::REPAIR, $letter->status);
        $this->assertSame(['alamat', 'keperluan'], $letter->flaggedFields());
        $this->assertTrue($letter->isEditableByCitizen());
    }

    public function test_second_done_does_not_renumber(): void
    {
        $letter = $this->letter(LetterStatus::SIGN);
        $letter = $this->wf->transition($letter, LetterStatus::DONE, $this->operator);
        $firstNumber = $letter->number;

        // Tidak ada transisi keluar dari DONE, jadi nomor tidak mungkin berubah;
        // jaga terhadap regression bila seseorang menambah jalur DONE -> DONE.
        $this->expectException(\InvalidArgumentException::class);
        $this->wf->transition($letter, LetterStatus::DONE, $this->operator);
    }
}
