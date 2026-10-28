<?php
namespace App\Services;

use App\Models\PbbBill;
use App\Models\PbbImportLog;
use App\Models\PbbObject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import database PBB dari CSV (PEMBANGUNAN.md 4.3). Baris = tagihan satu NOP per tahun,
 * jadi satu file bisa memuat beberapa baris untuk NOP yang sama (beda tahun).
 *
 * Idempoten: objek di-upsert berdasarkan (village_id, nop), tagihan berdasarkan
 * (pbb_object_id, year). Re-import TIDAK menimpa status tagihan yang sudah diproses
 * (mis. sudah lunas/disetor) — hanya nominal (pokok/denda). Setiap run dicatat ke
 * pbb_import_logs sebagai versi data untuk audit.
 *
 * CSV header wajib: nop, tahun, jumlah (pokok). Opsional: denda, nama_wp, alamat, rt, rw,
 * luas_tanah, luas_bangunan. Header fleksibel (huruf besar/spasi/diabaikan).
 */
class PbbImportService
{
    /** Peta berbagai penulisan header -> kolom kanonik. */
    private const HEADER_ALIASES = [
        'nop' => 'nop', 'nop_pbb' => 'nop', 'nomor_objek_pajak' => 'nop',
        'tahun' => 'tahun', 'tahun_pajak' => 'tahun', 'year' => 'tahun',
        'jumlah' => 'jumlah', 'pokok' => 'jumlah', 'pbb_terutang' => 'jumlah', 'amount' => 'jumlah',
        'denda' => 'denda', 'penalty' => 'denda', 'sanksi_administrasi' => 'denda',
        'nama_wp' => 'nama_wp', 'nama' => 'nama_wp', 'wp' => 'nama_wp', 'wajib_pajak' => 'nama_wp', 'nama_wp_tertanggal' => 'nama_wp',
        'alamat' => 'alamat', 'address' => 'alamat',
        'rt' => 'rt', 'rw' => 'rw',
        'luas_tanah' => 'luas_tanah', 'luas_bangunan' => 'luas_bangunan', 'luas_bngunan' => 'luas_bangunan',
    ];

    /**
     * @param  string  $csvContent  isi mentah file CSV
     * @return PbbImportLog  ringkasan hasil import (created/updated/skipped + status)
     */
    public function importFromString(int $villageId, ?User $actor, string $csvContent, string $filename): PbbImportLog
    {
        $rows = $this->parseCsv($csvContent);
        $log = PbbImportLog::create([
            'village_id' => $villageId,
            'user_id' => $actor?->id,
            'filename' => $filename,
            'status' => 'processing',
        ]);

        $counters = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        $errors = [];

        try {
            DB::transaction(function () use ($rows, $villageId, &$counters, &$errors): void {
                foreach ($rows as $index => $raw) {
                    $counters['total']++;
                    $lineNo = $index + 2; // +1 header, +1 basis-1

                    $row = $this->normalizeRow($raw);
                    // Simpan NOP sebagai 18 digit murni (sama seperti normalisasi cek NOP) agar cocok.
                    $nop = substr((string) preg_replace('/\D+/', '', (string) ($row['nop'] ?? '')), 0, (int) config('desa.pbb.nop_max_len', 18));
                    $year = (int) ($row['tahun'] ?? 0);
                    $amount = (float) str_replace([',', '.'], ['', ''], (string) ($row['jumlah'] ?? '0'));

                    if ($nop === '' || $year < 2000 || $year > 2100 || $amount <= 0) {
                        $counters['skipped']++;
                        $errors[] = "Baris $lineNo: NOP/tahun/nominal tidak valid.";
                        continue;
                    }

                    $object = $this->resolveObject($villageId, $nop, $row);
                    $penalty = (float) str_replace([',', '.'], ['', ''], (string) ($row['denda'] ?? '0'));

                    $bill = PbbBill::where('pbb_object_id', $object->id)->where('year', $year)->first();
                    if ($bill) {
                        // Jangan turunkan status tagihan yang sudah diproses; perbarui nominal saja.
                        $bill->forceFill(['amount' => $amount, 'penalty' => $penalty])->save();
                        $counters['updated']++;
                    } else {
                        PbbBill::create([
                            'village_id' => $villageId,
                            'pbb_object_id' => $object->id,
                            'year' => $year,
                            'amount' => $amount,
                            'penalty' => $penalty,
                        ]);
                        $counters['created']++;
                    }
                }
            });
        } catch (\Throwable $e) {
            $log->update([
                'rows_total' => $counters['total'],
                'status' => 'failed',
                'message' => Str::limit($e->getMessage(), 1000),
            ]);
            throw $e;
        }

        $status = match (true) {
            $counters['total'] === 0 => 'failed',
            $counters['skipped'] > 0 => 'partial',
            default => 'success',
        };

        $log->update([
            'rows_total' => $counters['total'],
            'rows_created' => $counters['created'],
            'rows_updated' => $counters['updated'],
            'rows_skipped' => $counters['skipped'],
            'status' => $status,
            'message' => $errors ? Str::limit(implode(' ', array_slice($errors, 0, 20)), 1000) : null,
        ]);

        AuditLogger::record(
            'pbb.import', $log,
            "Import CSV PBB: {$counters['created']} baru, {$counters['updated']} diperbarui, {$counters['skipped']} dilewati.",
            ['filename' => $filename, 'status' => $status],
        );

        return $log->refresh();
    }

    /** @return array<int, array<string, string>> */
    private function parseCsv(string $content): array
    {
        $handle = fopen('php://temp', 'r+'); // 'r+' agar bisa menulis lalu membaca ulang (mode 'r' membuat fwrite no-op).
        fwrite($handle, $content);
        rewind($handle);

        $all = [];
        while (($data = fgetcsv($handle)) !== false) {
            $all[] = $data;
        }
        fclose($handle);

        if (!$all) {
            return [];
        }

        $header = array_map(fn ($h) => $this->mapHeader((string) $h), array_shift($all));

        $rows = [];
        foreach ($all as $line) {
            // Lewati baris kosong total.
            if (implode('', array_map(fn ($v) => trim((string) $v), $line)) === '') {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                if ($key !== '') {
                    $row[$key] = $line[$i] ?? '';
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function mapHeader(string $raw): string
    {
        $key = (string) Str::of($raw)->trim()->lower()->replace([' ', '-', '.'], '_');
        $key = (string) preg_replace('/_+/', '_', $key);

        return self::HEADER_ALIASES[$key] ?? '';
    }

    /** @param  array<string, string>  $row  @return array<string, string> */
    private function normalizeRow(array $row): array
    {
        return array_map(fn ($v) => trim((string) $v), $row);
    }

    /**
     * Cari/buat objek pajak berdasar NOP, lalu segarkan detail WP bila ada kolomnya.
     * @param  array<string, string>  $row
     */
    private function resolveObject(int $villageId, string $nop, array $row): PbbObject
    {
        $object = PbbObject::firstOrNew(['village_id' => $villageId, 'nop' => $nop]);

        $detail = array_filter([
            'wp_name' => $row['nama_wp'] ?? null,
            'address' => $row['alamat'] ?? null,
            'rt' => $row['rt'] ?? null,
            'rw' => $row['rw'] ?? null,
            'land_area' => $row['luas_tanah'] ?? null,
            'building_area' => $row['luas_bangunan'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // wp_name wajib untuk objek baru; baris tanpa nama tak bisa membuat objek.
        if (!$object->exists && blank($detail['wp_name'] ?? null)) {
            $detail['wp_name'] = '(Tanpa nama WP)';
        }

        $object->fill($detail)->save();

        return $object;
    }
}
