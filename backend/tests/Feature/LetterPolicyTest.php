<?php
// Test RBAC surat (PEMBANGUNAN.md 3 & 4.2) lewat Gate: pemisahan antar desa,
// peran tiap tahap antrean, dan pengelolaan jenis surat dinamis.

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterType;
use App\Models\User;
use App\Models\Village;
use App\Support\LetterStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class LetterPolicyTest extends TestCase
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

        $this->assertFalse(Gate::forUser($pending)->allows('create', Letter::class));
        $this->assertTrue(Gate::forUser($verified)->allows('create', Letter::class));
    }

    public function test_view_is_owner_or_same_village_staff_only(): void
    {
        $owner = User::factory()->create(['village_id' => $this->village->id]);
        $letter = Letter::factory()->create(['village_id' => $this->village->id, 'user_id' => $owner->id]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $letter));
        $this->assertFalse(Gate::forUser(User::factory()->create(['village_id' => $this->village->id]))->allows('view', $letter));
        $this->assertTrue(Gate::forUser($this->staff('operator'))->allows('view', $letter));
        $this->assertFalse(Gate::forUser($this->staff('operator', Village::factory()->create()))->allows('view', $letter), 'Lintas desa harus ditolak.');
    }

    public function test_owner_update_only_on_repair_status(): void
    {
        $owner = User::factory()->create(['village_id' => $this->village->id]);
        $diproses = Letter::factory()->create([
            'village_id' => $this->village->id, 'user_id' => $owner->id, 'status' => LetterStatus::OPERATOR,
        ]);
        $diperbaiki = Letter::factory()->create([
            'village_id' => $this->village->id, 'user_id' => $owner->id, 'status' => LetterStatus::REPAIR,
        ]);

        $this->assertFalse(Gate::forUser($owner)->allows('update', $diproses));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $diperbaiki));
    }

    public function test_stage_authorities_are_progressive(): void
    {
        $letter = Letter::factory()->create(['village_id' => $this->village->id]);
        $rt = $this->staff('rt');
        $operator = $this->staff('operator');
        $kades = $this->staff('kades');
        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);

        $this->assertTrue(Gate::forUser($rt)->allows('approveRt', $letter));
        $this->assertTrue(Gate::forUser($operator)->allows('process', $letter));
        $this->assertFalse(Gate::forUser($operator)->allows('finalize', $letter), 'Hanya kades/admin yang menandatangani.');
        $this->assertTrue(Gate::forUser($kades)->allows('finalize', $letter));
        $this->assertFalse(Gate::forUser($warga)->allows('process', $letter));
    }

    public function test_letter_type_management(): void
    {
        $sistem = LetterType::factory()->create(['village_id' => null]);
        $milikDesa = LetterType::factory()->create(['village_id' => $this->village->id]);
        $kades = $this->staff('kades');
        $operator = $this->staff('operator');
        $admin = $this->staff('admin');

        $this->assertTrue(Gate::forUser($kades)->allows('create', LetterType::class));
        $this->assertFalse(Gate::forUser($operator)->allows('create', LetterType::class));

        // Jenis bawaan sistem hanya admin pusat yang bisa ubah.
        $this->assertFalse(Gate::forUser($kades)->allows('update', $sistem));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $sistem));

        // Jenis milik desa sendiri bisa diubah kades desa itu, tidak oleh kades desa lain.
        $this->assertTrue(Gate::forUser($kades)->allows('update', $milikDesa));
        $this->assertFalse(Gate::forUser($this->staff('kades', Village::factory()->create()))->allows('update', $milikDesa));
    }
}
