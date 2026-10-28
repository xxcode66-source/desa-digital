<?php
namespace Database\Factories;

use App\Models\LetterType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterType> */
class LetterTypeFactory extends Factory
{
    protected $model = LetterType::class;

    public function definition(): array
    {
        return [
            'village_id' => null, // jenis bawaan (tersedia utk semua desa)
            'code' => fake()->unique()->slug(2),
            'name' => 'SK ' . fake()->words(2, true),
            'needs_rt' => true,
            'form_schema_json' => [
                ['key' => 'keperluan', 'label' => 'Keperluan', 'type' => 'textarea', 'required' => true],
                ['key' => 'alamat', 'label' => 'Alamat', 'type' => 'text', 'required' => true],
            ],
            'required_attachments_json' => ['foto_ktp'],
            'is_active' => true,
        ];
    }
}
