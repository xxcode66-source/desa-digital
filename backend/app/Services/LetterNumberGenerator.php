<?php
namespace App\Services;

use App\Models\Village;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran surat otomatis sesuai format desa (PEMBANGUNAN.md 4.2), anti-duplikat.
 * Counter per desa per tahun disimpan di `letter_sequences` dan diambil dalam
 * transaksi dengan row-lock sehingga dua pengajuan serentak tak dapat nomor sama.
 * Kolom letters.number juga UNIQUE sebagai penjaga terakhir.
 */
class LetterNumberGenerator
{
    public const DEFAULT_FORMAT = '470/{seq}/DS/{month}/{year}';

    public function next(Village $village, ?int $year = null, ?int $month = null): string
    {
        $year ??= (int) now()->format('Y');
        $month ??= (int) now()->format('n');

        return DB::transaction(function () use ($village, $year, $month): string {
            $row = DB::table('letter_sequences')
                ->where('village_id', $village->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $seq = (int) $row->last_number + 1;
                DB::table('letter_sequences')
                    ->where('id', $row->id)
                    ->update(['last_number' => $seq, 'updated_at' => now()]);
            } else {
                $seq = 1;
                DB::table('letter_sequences')->insert([
                    'village_id' => $village->id,
                    'year' => $year,
                    'last_number' => $seq,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $this->format($village->letter_number_format ?: self::DEFAULT_FORMAT, $seq, $month, $year);
        });
    }

    private function format(string $tpl, int $seq, int $month, int $year): string
    {
        $pad = (int) config('desa.letters.number_pad', 3);

        return str_replace(
            ['{seq}', '{month}', '{year}'],
            [str_pad((string) $seq, $pad, '0', STR_PAD_LEFT), (string) $month, (string) $year],
            $tpl,
        );
    }
}
