<?php
namespace Database\Factories;

use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ComplaintReply> */
class ComplaintReplyFactory extends Factory
{
    protected $model = ComplaintReply::class;

    public function definition(): array
    {
        return [
            'complaint_id' => Complaint::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'from_status' => null,
            'to_status' => null,
            'is_staff' => false,
        ];
    }

    public function staff(): static
    {
        return $this->state(fn () => ['is_staff' => true]);
    }
}
