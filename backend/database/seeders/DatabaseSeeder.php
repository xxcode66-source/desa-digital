<?php

namespace Database\Seeders;

use App\Models\Village;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Kop & identitas desa untuk PDF surat (PEMBANGUNAN.md 4.2 & 4.7). Nilai contoh;
        // petugas menggantinya lewat panel (pengaturan desa). Ditulis hanya bila belum ada.
        $village = Village::firstOrCreate(['name' => 'Desa Digital']);
        if (blank($village->settings)) {
            $village->update([
                'letter_number_format' => $village->letter_number_format ?: '470/{seq}/DS/{month}/{year}',
                'settings' => [
                    'kop' => [
                        'top' => ['PEMERINTAH KABUPATEN CONTOH', 'KECAMATAN CONTOH'],
                        'name' => 'DESA DIGITAL',
                        'area' => 'Desa Digital',
                        'address' => 'Jalan Raya Desa Nomor 1',
                        'phone' => '(021) 5550-100',
                        'email' => 'halo@desadigital.desa.id',
                    ],
                    'kades' => 'Nama Kepala Desa',
                    'kades_nip' => null,
                ],
            ]);
        }

        $this->call([
            RoleSeeder::class,
            LetterTypeSeeder::class,
        ]);
    }
}
