<?php
// Fase 0 — jalur API dasar. Fitur bisnis (surat/PBB/UMKM/pengaduan) ditambahkan
// pada fasenya masing-masing sesuai PEMBANGUNAN.md bagian 8.

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['app' => config('app.name'), 'status' => 'ok']));

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
