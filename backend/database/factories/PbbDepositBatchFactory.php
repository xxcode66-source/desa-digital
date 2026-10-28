<?php
namespace Database\Factories;

use App\Models\PbbDepositBatch;
use App\Models\User;
use App\Models\Village;
use App\Support\PbbStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PbbDepositBatch> */
class PbbDepositBatchFactory extends Factory
{
    protected $model = PbbDepositBatch::class;

    public function definition(): array
    {
        return [
            'village_id' => Village::factory(),
            'collector_id' => User::factory(),
            'title' => 'Setoran RT ' . fake()->numerify('##') . ', ' . fake()->date(),
            'total_amount' => fake()->randomFloat(2, 100000, 2000000),
            'item_count' => fake()->numberBetween(1, 5),
            'status' => PbbStatus::DEPOSIT_PENDING,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => PbbStatus::DEPOSIT_APPROVED,
            'reviewed_at' => now(),
        ]);
    }
}
