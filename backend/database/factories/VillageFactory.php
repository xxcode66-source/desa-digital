<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class VillageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Desa ' . fake()->city(),
            'letter_number_format' => '470/{seq}/DS/{month}/{year}',
            'settings' => null,
        ];
    }
}
