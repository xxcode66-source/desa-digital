<?php
namespace Database\Factories;

use App\Models\PbbClaim;
use App\Models\PbbObject;
use App\Models\User;
use App\Models\Village;
use App\Support\PbbStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PbbClaim> */
class PbbClaimFactory extends Factory
{
    protected $model = PbbClaim::class;

    public function definition(): array
    {
        return [
            'village_id' => Village::factory(),
            'user_id' => User::factory(),
            'pbb_object_id' => PbbObject::factory(),
            'status' => PbbStatus::CLAIM_PENDING,
            'note' => fake()->sentence(8),
            'disk' => 'local',
            'evidence_path' => 'pbb/claims/seed.jpg',
            'evidence_name' => 'sppt.jpg',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => PbbStatus::CLAIM_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => PbbStatus::CLAIM_REJECTED,
            'review_note' => 'Bukti kurang jelas.',
            'reviewed_at' => now(),
        ]);
    }
}
