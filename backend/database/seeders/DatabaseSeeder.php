<?php

namespace Database\Seeders;

use App\Models\Village;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Village::firstOrCreate(['name' => 'Desa Digital']);

        $this->call([
            RoleSeeder::class,
            LetterTypeSeeder::class,
        ]);
    }
}
