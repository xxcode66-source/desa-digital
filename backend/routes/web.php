<?php

use App\Models\Letter;
use App\Services\LetterPdfService;
use App\Support\LetterStatus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Panel (staff, sesi login Filament): unduh/cetak PDF surat siap-cetak. Policy Letter@view
// memastikan hanya petugas desa surat itu (atau pemilik) yang bisa menarik berkasnya.
// Route web terpisah karena panel memakai guard sesi (bukan token Sanctum warga).
Route::get('/cetak/surat/{letter}', function (Letter $letter, LetterPdfService $pdf) {
    abort_unless($letter->status === LetterStatus::DONE, 404, 'Surat belum selesai.');
    Gate::authorize('view', $letter);

    $full = $pdf->absolutePath($letter->loadMissing('type'));
    abort_unless($full !== null, 404, 'PDF surat belum tersedia.');

    return response()->download($full, $pdf->downloadName($letter), ['Content-Type' => 'application/pdf']);
})->middleware('auth')->name('panel.letter.pdf');
