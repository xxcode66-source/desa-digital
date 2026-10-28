<?php
namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterType;
use App\Models\User;
use App\Models\Village;
use App\Support\LetterStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Letter> */
class LetterFactory extends Factory
{
    protected $model = Letter::class;

    public function definition(): array
    {
        return [
            'village_id' => Village::factory(),
            'letter_type_id' => LetterType::factory(),
            'user_id' => User::factory(),
            'data_json' => ['keperluan' => 'Usaha dagangan', 'alamat' => 'Jl. Melati 1'],
            'status' => LetterStatus::SUBMITTED,
            'submitted_at' => now(),
        ];
    }

    /** Surat yang sudah selesai + bernomor + punya token verifikasi publik. */
    public function done(): static
    {
        return $this->state(fn () => [
            'status' => LetterStatus::DONE,
            'number' => '470/001/DS/10/2026',
            'public_token' => fake()->uuid(),
            'completed_at' => now(),
        ]);
    }
}
