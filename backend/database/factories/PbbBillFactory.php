<?php
namespace Database\Factories;

use App\Models\PbbBill;
use App\Models\PbbObject;
use App\Models\Village;
use App\Support\PbbStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PbbBill> */
class PbbBillFactory extends Factory
{
    protected $model = PbbBill::class;

    public function definition(): array
    {
        return [
            'village_id' => Village::factory(),
            'pbb_object_id' => PbbObject::factory(),
            'year' => (int) date('Y'),
            'amount' => fake()->randomFloat(2, 50000, 500000),
            'penalty' => 0,
            'status' => PbbStatus::BILL_UNPAID,
            'due_date' => now()->addMonths(6)->toDateString(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PbbStatus::BILL_PAID,
            'paid_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => PbbStatus::BILL_PENDING]);
    }
}
