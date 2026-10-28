<?php
// Test RBAC pengaduan (PEMBANGUNAN.md 3 & 4.5) lewat Gate: pemisahan antar desa,
// layanan butuh akun terverifikasi, pemilik hanya membalas saat laporan masih terbuka,
// dan identitas pelapor anonim tetap terlihat oleh petugas desa yang sama.

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use App\Models\Village;
use App\Support\ComplaintStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ComplaintPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    private function staff(string $role, ?Village $v = null): User
    {
        $v ??= $this->village;
        $u = User::factory()->create(['village_id' => $v->id]);
        $u->assignRole($role);

        return $u->fresh();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->village = Village::factory()->create();
    }

    public function test_only_verified_warga_can_submit(): void
    {
        $pending = User::factory()->create(['village_id' => $this->village->id]);
        $verified = User::factory()->verified()->create(['village_id' => $this->village->id]);

        $this->assertFalse(Gate::forUser($pending)->allows('create', Complaint::class));
        $this->assertTrue(Gate::forUser($verified)->allows('create', Complaint::class));
    }

    public function test_view_is_owner_or_same_village_staff_only_even_when_anonymous(): void
    {
        $owner = User::factory()->create(['village_id' => $this->village->id]);
        $complaint = Complaint::factory()->anonymous()->create([
            'village_id' => $this->village->id, 'user_id' => $owner->id,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $complaint));
        $this->assertFalse(Gate::forUser(User::factory()->create(['village_id' => $this->village->id]))->allows('view', $complaint));
        // Anonim tidak menyembunyikan pelapor dari petugas desa yang sama.
        $this->assertTrue(Gate::forUser($this->staff('operator'))->allows('view', $complaint));
        $this->assertFalse(Gate::forUser($this->staff('operator', Village::factory()->create()))->allows('view', $complaint), 'Lintas desa harus ditolak.');
    }

    public function test_owner_reply_only_while_open(): void
    {
        $owner = User::factory()->create(['village_id' => $this->village->id]);
        $open = Complaint::factory()->create(['village_id' => $this->village->id, 'user_id' => $owner->id]);
        $done = Complaint::factory()->done()->create(['village_id' => $this->village->id, 'user_id' => $owner->id]);

        $this->assertTrue(Gate::forUser($owner)->allows('reply', $open));
        $this->assertFalse(Gate::forUser($owner)->allows('reply', $done), 'Laporan tertutup tidak bisa dibalas warga.');
    }

    public function test_process_is_staff_same_village_only(): void
    {
        $complaint = Complaint::factory()->create(['village_id' => $this->village->id]);
        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);

        $this->assertTrue(Gate::forUser($this->staff('operator'))->allows('process', $complaint));
        $this->assertTrue(Gate::forUser($this->staff('kades'))->allows('process', $complaint));
        $this->assertFalse(Gate::forUser($warga)->allows('process', $complaint));
        $this->assertFalse(Gate::forUser($this->staff('operator', Village::factory()->create()))->allows('process', $complaint));
    }

    public function test_status_transitions_follow_the_machine(): void
    {
        // Sanity mesin status dipakai workflow + panel.
        $this->assertTrue(ComplaintStatus::canTransition(ComplaintStatus::ACCEPTED, ComplaintStatus::IN_PROGRESS));
        $this->assertFalse(ComplaintStatus::canTransition(ComplaintStatus::DONE, ComplaintStatus::IN_PROGRESS));
    }
}
