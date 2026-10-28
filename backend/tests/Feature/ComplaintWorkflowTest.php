<?php
// Test alur pengaduan (PEMBANGUNAN.md 4.5): transisi dijaga mesin status, penolakan wajib
// alasan, setiap perubahan tercatat di kronologi + audit, dan status akhir menutup laporan.

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use App\Services\ComplaintWorkflow;
use App\Support\ComplaintStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ComplaintWorkflow $workflow;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = app(ComplaintWorkflow::class);

        $reporter = User::factory()->create();
        $this->operator = User::factory()->create(['village_id' => $reporter->village_id]);
        $this->operator->assignRole('operator');

        $this->complaint = Complaint::factory()->create(['user_id' => $reporter->id, 'village_id' => $reporter->village_id]);
    }

    private Complaint $complaint;

    public function test_happy_path_accepted_to_done_records_history_and_audit(): void
    {
        $this->actingAs($this->operator);

        $c = $this->workflow->transition($this->complaint, ComplaintStatus::IN_PROGRESS, $this->operator);
        $c = $this->workflow->transition($c, ComplaintStatus::DONE, $this->operator, 'Sudah diperbaiki.');

        $this->assertSame(ComplaintStatus::DONE, $c->status);
        $this->assertNotNull($c->resolved_at);
        $this->assertCount(2, $c->replies()->whereNotNull('to_status')->get());
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.status.diproses', 'subject_id' => $c->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.status.selesai', 'subject_id' => $c->id]);
    }

    public function test_illegal_transition_is_rejected(): void
    {
        $this->complaint->update(['status' => ComplaintStatus::DONE]);

        $this->expectException(\InvalidArgumentException::class);
        $this->workflow->transition($this->complaint->fresh(), ComplaintStatus::IN_PROGRESS, $this->operator);
    }

    public function test_reject_requires_reason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->workflow->transition($this->complaint, ComplaintStatus::REJECTED, $this->operator, null);
    }

    public function test_reply_appends_conversation_without_changing_status(): void
    {
        $reporter = $this->complaint->user;
        $this->workflow->reply($this->complaint, 'Terima kasih, sudah ditindak?', $reporter);

        $this->complaint->refresh();
        $this->assertSame(ComplaintStatus::ACCEPTED, $this->complaint->status);
        $last = $this->complaint->replies()->latest('id')->first();
        $this->assertSame('Terima kasih, sudah ditindak?', $last->body);
        $this->assertNull($last->to_status);
    }
}
