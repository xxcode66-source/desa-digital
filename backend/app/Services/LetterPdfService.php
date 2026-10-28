<?php
namespace App\Services;

use App\Models\Letter;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Membangkitkan PDF surat resmi (PEMBANGUNAN.md 4.2): kop desa, isi formulir sesuai
 * skema jenis surat, blok tanda tangan kepala desa, dan QR verifikasi keaslian yang
 * menunjuk ke endpoint publik /api/verify-letter/{token}. File disimpan di disk privat
 * `local` (bukan publik) — hanya yang boleh melihat surat bisa mengunduhnya.
 */
class LetterPdfService
{
    /** Tautan verifikasi publik (hasil scan QR). Null bila token belum ada (belum selesai). */
    public function verifyUrl(Letter $letter): ?string
    {
        return $letter->public_token
            ? rtrim((string) config('app.url'), '/') . '/api/verify-letter/' . $letter->public_token
            : null;
    }

    /** QR (PNG) sebagai data-URI base64, siap disisipkan ke HTML PDF. */
    public function qrDataUri(string $url): string
    {
        $options = new QROptions([
            'outputType'  => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'    => QRCode::ECC_M,
            'imageBase64' => true,
            'scale'       => 4,
        ]);

        return (new QRCode($options))->render($url);
    }

    /**
     * Render + simpan PDF surat, lalu isi `pdf_path`. Best-effort: kegagalan render/
     * tulis file tidak boleh membatalkan status `selesai` (hanya dicatat). Return path
     * relatif atau null bila token belum ada / gagal.
     */
    public function generate(Letter $letter): ?string
    {
        $letter->loadMissing(['type', 'user', 'village']);

        $url = $this->verifyUrl($letter);
        if ($url === null) {
            return null;
        }

        try {
            $html = view('pdf.letter', [
                'letter'    => $letter,
                'qrUri'     => $this->qrDataUri($url),
                'verifyUrl' => $url,
            ])->render();

            $pdf  = Pdf::loadHTML($html)->setPaper('a4');
            $path = sprintf('letters/pdf/%d.pdf', $letter->id);
            Storage::disk('local')->put($path, $pdf->output());

            $letter->forceFill(['pdf_path' => $path])->save();

            return $path;
        } catch (\Throwable $e) {
            Log::warning("Gagal membuat PDF surat {$letter->id}: {$e->getMessage()}");

            return null;
        }
    }

    /** Path absolut file PDF di disk privat (untuk response()->download). */
    public function absolutePath(Letter $letter): ?string
    {
        if (! $letter->pdf_path) {
            return null;
        }

        $full = Storage::disk('local')->path($letter->pdf_path);

        return is_file($full) ? $full : null;
    }

    /** Nama file unduh yang rapi & aman (bebas karakter aneh). */
    public function downloadName(Letter $letter): string
    {
        $safe = preg_replace('/[^0-9A-Za-z._-]/', '_', (string) $letter->number) ?: 'surat';

        return "surat-{$safe}.pdf";
    }
}
