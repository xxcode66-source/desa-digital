<?php
namespace Database\Factories;

use App\Models\Complaint;
use App\Models\User;
use App\Models\Village;
use App\Support\ComplaintStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Complaint> */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'village_id' => Village::factory(),
            'user_id' => User::factory(),
            'category' => 'jalan_rusak',
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'location' => fake()->streetAddress(),
            'status' => ComplaintStatus::ACCEPTED,
            'is_anonymous' => false,
            'submitted_at' => now(),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn () => ['is_anonymous' => true]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => ComplaintStatus::IN_PROGRESS]);
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => ComplaintStatus::DONE,
            'resolved_at' => now(),
        ]);
    }
}
