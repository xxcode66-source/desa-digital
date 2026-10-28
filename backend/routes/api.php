<?php
// Fase 0: health + login/me/logout. Fase 1 (PEMBANGUNAN.md 4.1): registrasi NIK +
// consent + logout-all. Tanpa OTP/SMS pihak ketiga; verifikasi identitas tetap oleh
// operator di panel. Rate limiting di sini membatasi brute-force login/daftar;
// fitur bisnis (surat/PBB/UMKM/pengaduan) menyusul pada fasenya.

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['app' => config('app.name'), 'status' => 'ok']));

// Publik: login + registrasi. Dibatasi ketat.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:' . config('desa.throttle.register') . ',1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
});
