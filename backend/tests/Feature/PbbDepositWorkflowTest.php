<?php
// Test alur setoran PBB (PEMBANGUNAN.md 4.3 & 5): kolektor mengajukan slip -> tagihan
// jadi disetor_pending; bendahara ACC sebagian (per item) -> item approved jadi lunas,
// item ditolak kembali belum_bayar; status batch diturunkan otomatis (pending/partial/
// approved/rejected). Penilai non-bendahara & penyetor non-kolektor ditolak.

namespace Tests\Feature;

use App\Models\PbbBill;
use App\Models\PbbObject;
use App\Models\User;
use App\Models\Village;
use App\Services\PbbDepositService;
use App\Support\PbbStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PbbDepositWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private PbbDepositService $service;
    private Village $village;
    private User $collector;
    private User $treasurer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PbbDepositService::class);
        $this->village = Village::factory()->create();

        $this->collector = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $this->collector->assignRole('kolektor', '03');

        $this->treasurer = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $this->treasurer->assignRole('bendahara_pbb');
    }

    private function unpaidBill(float $amount = 150000, float $penalty = 0): PbbBill
    {
        $object = PbbObject::factory()->create(['village_id' => $this->village->id, 'rt' => '03']);

        return PbbBill::factory()->create([
            'village_id' => $this->village->id,
            'pbb_object_id' => $object->id,
            'amount' => $amount,
            'penalty' => $penalty,
            'status' => PbbStatus::BILL_UNPAID,
        ]);
    }

    private function submit(PbbBill ...$bills)
    {
        $items = array_map(fn (PbbBill $b) => ['pbb_bill_id' => $b->id], $bills);

        return $this->service->submitBatch($this->collector, 'Setoran RT 03', $items);
    }

    public function test_submit_moves_bills_to_pending_and_records_batch(): void
    {
        $b1 = $this->unpaidBill(150000);
        $b2 = $this->unpaidBill(200000, 5000);

        $batch = $this->submit($b1, $b2);

        $this->assertSame(PbbStatus::DEPOSIT_PENDING, $batch->status);
        $this->assertSame(2, $batch->item_count);
        $this->assertSame(355000.0, (float) $batch->total_amount); // (150k) + (200k+5k)
        $b1->refresh();
        $b2->refresh();
        $this->assertSame(PbbStatus::BILL_PENDING, $b1->status);
        $this->assertSame(PbbStatus::BILL_PENDING, $b2->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pbb.deposit.submitted', 'subject_id' => $batch->id]);
    }

    public function test_approve_item_makes_bill_lunas_and_finalizes_batch(): void
    {
        $b1 = $this->unpaidBill();
        $batch = $this->submit($b1);
        $item = $batch->items()->first();

        $this->service->approveItem($item, $this->treasurer, 'Uang cocok.');

        $item->refresh();
        $b1->refresh();
        $batch->refresh();
        $this->assertSame(PbbStatus::ITEM_APPROVED, $item->status);
        $this->assertSame(PbbStatus::BILL_PAID, $b1->status);
        $this->assertNotNull($b1->paid_at);
        $this->assertSame(PbbStatus::DEPOSIT_APPROVED, $batch->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pbb.deposit.item.approved', 'subject_id' => $item->id]);
    }

    public function test_reject_returns_bill_to_unpaid(): void
    {
        $b1 = $this->unpaidBill();
        $batch = $this->submit($b1);

        $this->service->rejectItem($batch->items()->first(), $this->treasurer, 'Jumlah tidak cocok.');

        $b1->refresh();
        $this->assertSame(PbbStatus::BILL_UNPAID, $b1->status);
        $this->assertSame(PbbStatus::DEPOSIT_REJECTED, $batch->fresh()->status);
    }

    public function test_mixed_review_yields_partially_approved_batch(): void
    {
        $b1 = $this->unpaidBill();
        $b2 = $this->unpaidBill();
        $batch = $this->submit($b1, $b2);

        [$i1, $i2] = $batch->items()->orderBy('id')->get()->all();
        $this->service->approveItem($i1, $this->treasurer);
        $this->service->rejectItem($i2, $this->treasurer, 'Bukti kurang.');

        $this->assertSame(PbbStatus::DEPOSIT_PARTIAL, $batch->fresh()->status);
        $b1->refresh();
        $b2->refresh();
        $this->assertSame(PbbStatus::BILL_PAID, $b1->status);
        $this->assertSame(PbbStatus::BILL_UNPAID, $b2->status);
    }

    public function test_batch_stays_pending_while_items_remain_unreviewed(): void
    {
        $b1 = $this->unpaidBill();
        $b2 = $this->unpaidBill();
        $batch = $this->submit($b1, $b2);

        $this->service->approveItem($batch->items()->orderBy('id')->first(), $this->treasurer);

        $this->assertSame(PbbStatus::DEPOSIT_PENDING, $batch->fresh()->status); // masih ada 1 pending
    }

    public function test_non_collector_cannot_submit(): void
    {
        $warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $warga->assignRole('warga');
        $bill = $this->unpaidBill();

        try {
            $this->service->submitBatch($warga, 'x', [['pbb_bill_id' => $bill->id]]);
            $this->fail('Non-kolektor seharusnya ditolak.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_only_treasurer_of_same_village_can_review(): void
    {
        $batch = $this->submit($this->unpaidBill());
        $item = $batch->items()->first();

        $operator = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $operator->assignRole('operator'); // bukan bendahara

        $this->expectException(AuthorizationException::class);
        $this->service->approveItem($item, $operator);
    }

    public function test_reject_requires_a_reason(): void
    {
        $batch = $this->submit($this->unpaidBill());
        $item = $batch->items()->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->rejectItem($item, $this->treasurer, '');
    }

    public function test_already_paid_bill_cannot_be_deposited_again(): void
    {
        $bill = $this->unpaidBill();
        $bill->forceFill(['status' => PbbStatus::BILL_PAID, 'paid_at' => now()])->save();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitBatch($this->collector, 'x', [['pbb_bill_id' => $bill->id]]);
    }

    public function test_empty_batch_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitBatch($this->collector, 'x', []);
    }

    public function test_cross_village_bill_is_rejected(): void
    {
        $otherVillage = Village::factory()->create();
        $foreignBill = PbbBill::factory()->create([
            'village_id' => $otherVillage->id,
            'pbb_object_id' => PbbObject::factory()->create(['village_id' => $otherVillage->id])->id,
            'status' => PbbStatus::BILL_UNPAID,
        ]);

        // Kolektor desa A tak bisa menyetor tagihan milik desa B.
        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitBatch($this->collector, 'x', [['pbb_bill_id' => $foreignBill->id]]);
    }
}
