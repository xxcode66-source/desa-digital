<?php
// Fase 0: health + login/me/logout. Fase 1 (PEMBANGUNAN.md 4.1): registrasi NIK +
// OTP verifikasi nomor HP + logout-all. Rate limiting di sini membatasi brute-force
// OTP/login; fitur bisnis (surat/PBB/UMKM/pengaduan) menyusul pada fasenya.

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['app' => config('app.name'), 'status' => 'ok']));

// Publik: login + alur registrasi (OTP lalu daftar). Dibatasi ketat.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register/otp', [AuthController::class, 'sendRegisterOtp'])
    ->middleware('throttle:' . config('desa.throttle.otp_send') . ',1');
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:' . config('desa.throttle.register') . ',1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
});
