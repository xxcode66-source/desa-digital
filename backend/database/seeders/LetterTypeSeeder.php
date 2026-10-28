<?php
namespace Database\Seeders;

use App\Models\LetterType;
use Illuminate\Database\Seeder;

/**
 * Jenis surat awal Fase 2 (PEMBANGUNAN.md 4.2 & Prompt Fase 2: "Mulai dengan SK Usaha
 * dan SK Domisili"). village_id = NULL sehingga tersedia untuk semua desa. Admin bisa
 * menambah/mengubah jenis lewat panel tanpa ubah kode (form_schema_json dinamis).
 */
class LetterTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'sk_usaha',
                'name' => 'Surat Keterangan Usaha',
                'needs_rt' => true,
                'required_attachments_json' => ['foto_ktp', 'foto_tempat_usaha'],
                'form_schema_json' => [
                    ['key' => 'nama_usaha', 'label' => 'Nama usaha', 'type' => 'text', 'required' => true],
                    ['key' => 'jenis_usaha', 'label' => 'Jenis usaha', 'type' => 'text', 'required' => true],
                    ['key' => 'alamat_usaha', 'label' => 'Alamat usaha', 'type' => 'textarea', 'required' => true],
                    ['key' => 'modal', 'label' => 'Modal usaha (Rp)', 'type' => 'text', 'required' => false],
                ],
            ],
            [
                'code' => 'sk_domisili',
                'name' => 'Surat Keterangan Domisili',
                'needs_rt' => true,
                'required_attachments_json' => ['foto_ktp'],
                'form_schema_json' => [
                    ['key' => 'alamat_domisili', 'label' => 'Alamat domisili', 'type' => 'textarea', 'required' => true],
                    ['key' => 'sejak', 'label' => 'Tinggal sejak (tahun)', 'type' => 'text', 'required' => true],
                    ['key' => 'keperluan', 'label' => 'Keperluan surat', 'type' => 'textarea', 'required' => true],
                ],
            ],
        ];

        foreach ($types as $t) {
            LetterType::firstOrCreate(
                ['village_id' => null, 'code' => $t['code']],
                $t + ['is_active' => true],
            );
        }
    }
}
