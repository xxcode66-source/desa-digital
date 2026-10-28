<?php
namespace Database\Factories;

use App\Models\User;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'village_id' => Village::factory(),
            'nik' => fake()->unique()->numerify('32##############'),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('08##########'),
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'pending',
            'rt' => fake()->numerify('##'),
            'rw' => fake()->numerify('#'),
            // Perekam data mewakili warga yang sudah memberi consent (Fase 1).
            'consent_given_at' => now(),
            'terms_version' => config('desa.terms_version'),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['status' => 'verified']);
    }
}
