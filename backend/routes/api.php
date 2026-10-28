<?php
// Fase 0: health + login/me/logout. Fase 1 (PEMBANGUNAN.md 4.1): registrasi NIK +
// consent + logout-all. Tanpa OTP/SMS pihak ketiga; verifikasi identitas tetap oleh
// operator di panel. Rate limiting di sini membatasi brute-force login/daftar;
// fitur bisnis (surat/PBB/UMKM/pengaduan) menyusul pada fasenya.

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LetterController;
use App\Http\Controllers\Api\LetterVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['app' => config('app.name'), 'status' => 'ok']));

// Publik: login + registrasi. Dibatasi ketat.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:' . config('desa.throttle.register') . ',1');

// Publik: verifikasi keaslian surat (hasil scan QR). Dibatasi untuk cegah scraping.
Route::get('/verify-letter/{token}', [LetterVerificationController::class, 'verify'])
    ->middleware('throttle:' . config('desa.throttle.letter_verify') . ',1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);

    // Fase 2 — Surat (warga): jenis surat, ajukan, lacak, perbaiki, unduh lampiran.
    Route::get('/letters/types', [LetterController::class, 'types']);
    Route::post('/letters', [LetterController::class, 'store'])
        ->middleware('throttle:' . config('desa.throttle.letter_submit') . ',1');
    Route::get('/letters', [LetterController::class, 'index']);
    Route::get('/letters/{letter}', [LetterController::class, 'show']);
    Route::put('/letters/{letter}', [LetterController::class, 'update']);
    Route::get('/letters/{letter}/attachments/{attachment}', [LetterController::class, 'attachment']);
});
