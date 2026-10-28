<?php
namespace Database\Factories;

use App\Models\PbbObject;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PbbObject> */
class PbbObjectFactory extends Factory
{
    protected $model = PbbObject::class;

    public function definition(): array
    {
        return [
            'village_id' => Village::factory(),
            // NOP 18 digit (kolom string(18)): pakai numerify polos agar muat & realistis.
            'nop' => fake()->numerify(str_repeat('#', 18)),
            'wp_name' => fake()->name(),
            'address' => fake()->streetAddress(),
            'rt' => fake()->numerify('##'),
            'rw' => fake()->numerify('##'),
            'land_area' => fake()->randomFloat(2, 60, 500),
            'building_area' => fake()->randomFloat(2, 30, 300),
        ];
    }
}
