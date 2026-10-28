<?php
namespace Database\Factories;

use App\Models\Shop;
use App\Models\User;
use App\Models\Village;
use App\Support\UmkmStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shop> */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        return [
            'village_id' => Village::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(10),
            'category' => 'makanan',
            'address' => fake()->streetAddress(),
            'rt' => fake()->numerify('##'),
            'rw' => fake()->numerify('#'),
            'opening_hours' => '08:00 - 20:00',
            'wa_number' => '081234567890',
            'status' => UmkmStatus::SHOP_PENDING,
            'is_hidden' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => UmkmStatus::SHOP_ACTIVE, 'reviewed_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => UmkmStatus::SHOP_REJECTED,
            'review_note' => 'Foto usaha kurang jelas.',
            'reviewed_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UmkmStatus::SHOP_SUSPENDED]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_hidden' => true]);
    }
}
