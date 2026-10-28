<?php
namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        // village_id mengikuti desa toko-nya (kolom shop_id dievaluasi lebih dulu).
        return [
            'shop_id' => Shop::factory(),
            'village_id' => fn (array $attrs): int|null => isset($attrs['shop_id'])
                ? Shop::find($attrs['shop_id'])?->village_id
                : null,
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(8),
            'price' => fake()->randomNumber(6, true),
            'is_available' => true,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }
}
