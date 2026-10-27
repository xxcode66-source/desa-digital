<?php
namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            'warga' => 'Warga',
            'umkm' => 'Pelaku UMKM',
            'rt' => 'Pengantar RT/RW',
            'kolektor' => 'Kolektor PBB',
            'operator' => 'Operator Desa',
            'kades' => 'Kepala Desa',
            'bendahara_pbb' => 'Bendahara PBB',
            'admin' => 'Admin',
        ];

        foreach (Role::ALL as $name) {
            Role::firstOrCreate(['name' => $name], ['label' => $labels[$name]]);
        }
    }
}
