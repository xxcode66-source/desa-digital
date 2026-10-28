<?php
// Feature test RBAC Fase 1 via Gate (memakai UserPolicy & VillagePolicy auto-discovery).
// Fokus: verifikasi warga oleh operator, pemisahan desa (multi-tenant), dan manageRoles.

namespace Tests\Feature;

use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Village $v, string $role): User
    {
        $u = User::factory()->create(['village_id' => $v->id]);
        $u->assignRole($role);

        return $u->fresh();
    }

    public function test_warga_cannot_list_users(): void
    {
        $v = Village::factory()->create();
        $warga = $this->staff($v, 'warga');

        $this->assertFalse(Gate::forUser($warga)->allows('viewAny', User::class));
    }

    public function test_operator_can_verify_pending_warga_in_same_village(): void
    {
        $v = Village::factory()->create();
        $operator = $this->staff($v, 'operator');
        $warga = User::factory()->create(['village_id' => $v->id, 'status' => User::STATUS_PENDING]);

        $this->assertTrue(Gate::forUser($operator)->allows('verify', $warga));
    }

    public function test_operator_cannot_verify_across_village(): void
    {
        $vA = Village::factory()->create();
        $vB = Village::factory()->create();
        $operator = $this->staff($vA, 'operator');
        $wargaLainDesa = User::factory()->create(['village_id' => $vB->id]);

        $this->assertFalse(Gate::forUser($operator)->allows('verify', $wargaLainDesa));
    }

    public function test_operator_cannot_verify_self(): void
    {
        $v = Village::factory()->create();
        $operator = $this->staff($v, 'operator');

        $this->assertFalse(Gate::forUser($operator)->allows('verify', $operator));
    }

    public function test_only_kades_or_admin_can_manage_roles(): void
    {
        $v = Village::factory()->create();
        $operator = $this->staff($v, 'operator');
        $kades = $this->staff($v, 'kades');
        $warga = User::factory()->create(['village_id' => $v->id]);

        $this->assertFalse(Gate::forUser($operator)->allows('manageRoles', $warga));
        $this->assertTrue(Gate::forUser($kades)->allows('manageRoles', $warga));
    }

    public function test_village_update_requires_kades_or_admin_in_that_village(): void
    {
        $v = Village::factory()->create();
        $other = Village::factory()->create();
        $warga = $this->staff($v, 'warga');
        $kades = $this->staff($v, 'kades');

        $this->assertFalse(Gate::forUser($warga)->allows('update', $v));
        $this->assertTrue(Gate::forUser($kades)->allows('update', $v));
        $this->assertFalse(Gate::forUser($kades)->allows('update', $other)); // lintas desa
    }

    public function test_only_admin_can_create_village(): void
    {
        $v = Village::factory()->create();
        $kades = $this->staff($v, 'kades');
        $admin = $this->staff($v, 'admin');

        $this->assertFalse(Gate::forUser($kades)->allows('create', Village::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Village::class));
    }
}
