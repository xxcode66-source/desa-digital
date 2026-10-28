<?php
// Test RBAC klaim & setoran PBB lewat Gate (PEMBANGUNAN.md 3 & 4.3): pemisahan antar desa,
// pemisahan peran (operator meninjau klaim, bendahara menilai setoran — tidak tertukar),
// pengajuan butuh akun terverifikasi, dan hanya kolektor pemilik/bendahara desa melihat slip.

namespace Tests\Feature;

use App\Models\PbbClaim;
use App\Models\PbbDepositBatch;
use App\Models\PbbObject;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PbbPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();
        $this->village = Village::factory()->create();
    }

    private function user(string $role, ?Village $v = null, bool $verified = true): User
    {
        $v ??= $this->village;
        $u = $verified
            ? User::factory()->verified()->create(['village_id' => $v->id])
            : User::factory()->create(['village_id' => $v->id]);
        $u->assignRole($role, $role === 'kolektor' ? '03' : null);

        return $u->fresh();
    }

    // ---- klaim kepemilikan ----

    public function test_only_verified_user_can_create_claim(): void
    {
        $this->assertTrue(Gate::forUser($this->user('warga'))->allows('create', PbbClaim::class));
        $this->assertFalse(Gate::forUser($this->user('warga', verified: false))->allows('create', PbbClaim::class));
    }

    public function test_claim_view_is_owner_or_same_village_reviewer(): void
    {
        $owner = $this->user('warga');
        $claim = PbbClaim::factory()->create([
            'village_id' => $this->village->id,
            'user_id' => $owner->id,
            'pbb_object_id' => PbbObject::factory()->create(['village_id' => $this->village->id])->id,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $claim));
        $this->assertTrue(Gate::forUser($this->user('operator'))->allows('view', $claim));
        $this->assertTrue(Gate::forUser($this->user('warga'))->allows('view', $claim) === false, 'Warga lain tak boleh melihat.');
        // Lintas desa ditolak walau ia reviewer di desanya sendiri.
        $this->assertFalse(Gate::forUser($this->user('operator', Village::factory()->create()))->allows('view', $claim));
    }

    public function test_claim_review_is_operator_kades_admin_only(): void
    {
        $claim = PbbClaim::factory()->create([
            'village_id' => $this->village->id,
            'pbb_object_id' => PbbObject::factory()->create(['village_id' => $this->village->id])->id,
        ]);

        foreach (['operator', 'kades', 'admin'] as $role) {
            $this->assertTrue(Gate::forUser($this->user($role))->allows('review', $claim), "$role harusnya boleh review.");
        }
        // bendahara PBB menilai SETORAN, bukan klaim — tidak boleh review klaim.
        $this->assertFalse(Gate::forUser($this->user('bendahara_pbb'))->allows('review', $claim));
        $this->assertFalse(Gate::forUser($this->user('warga'))->allows('review', $claim));
    }

    // ---- setoran ----

    public function test_only_verified_collector_can_create_deposit(): void
    {
        $this->assertTrue(Gate::forUser($this->user('kolektor'))->allows('create', PbbDepositBatch::class));
        $this->assertTrue(Gate::forUser($this->user('rt'))->allows('create', PbbDepositBatch::class));
        $this->assertFalse(Gate::forUser($this->user('kolektor', verified: false))->allows('create', PbbDepositBatch::class));
        $this->assertFalse(Gate::forUser($this->user('bendahara_pbb'))->allows('create', PbbDepositBatch::class), 'Bendahara bukan penyetor.');
    }

    public function test_deposit_view_any_is_collector_or_treasurer_only(): void
    {
        $this->assertTrue(Gate::forUser($this->user('kolektor'))->allows('viewAny', PbbDepositBatch::class));
        $this->assertTrue(Gate::forUser($this->user('bendahara_pbb'))->allows('viewAny', PbbDepositBatch::class));
        $this->assertTrue(Gate::forUser($this->user('admin'))->allows('viewAny', PbbDepositBatch::class));
        $this->assertFalse(Gate::forUser($this->user('operator'))->allows('viewAny', PbbDepositBatch::class));
    }

    public function test_deposit_review_is_treasurer_or_admin_of_same_village(): void
    {
        $batch = PbbDepositBatch::factory()->create([
            'village_id' => $this->village->id,
            'collector_id' => $this->user('kolektor')->id,
        ]);

        $this->assertTrue(Gate::forUser($this->user('bendahara_pbb'))->allows('review', $batch));
        $this->assertTrue(Gate::forUser($this->user('admin'))->allows('review', $batch));
        $this->assertTrue(Gate::forUser($this->user('admin', Village::factory()->create()))->allows('review', $batch), 'Admin lintas desa tetap boleh.');
        $this->assertFalse(Gate::forUser($this->user('bendahara_pbb', Village::factory()->create()))->allows('review', $batch));
        $this->assertFalse(Gate::forUser($this->user('operator'))->allows('review', $batch));
    }

    public function test_deposit_view_is_owner_collector_or_village_treasurer(): void
    {
        $collector = $this->user('kolektor');
        $batch = PbbDepositBatch::factory()->create([
            'village_id' => $this->village->id,
            'collector_id' => $collector->id,
        ]);

        $this->assertTrue(Gate::forUser($collector)->allows('view', $batch));
        $this->assertTrue(Gate::forUser($this->user('bendahara_pbb'))->allows('view', $batch));
        $this->assertFalse(Gate::forUser($this->user('kolektor'))->allows('view', $batch), 'Kolektor lain tak boleh melihat slip tetangga.');
    }
}
