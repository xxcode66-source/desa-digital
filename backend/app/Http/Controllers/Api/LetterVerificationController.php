<?php
// Fase 2 — Verifikasi keaslian surat untuk publik (PEMBANGUNAN.md 4.2). QR pada surat
// mengarah ke endpoint ini; hasil scan menampilkan "surat sah, dikeluarkan untuk X".
// Hanya surat berstatus selesai yang bisa diverifikasi. Tanpa login, di-rate-limit.
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Support\LetterStatus;

class LetterVerificationController extends Controller
{
    public function verify(string $token)
    {
        $letter = Letter::with('type:id,name,code', 'user:id,name', 'village:id,name')
            ->where('public_token', $token)
            ->where('status', LetterStatus::DONE)
            ->first();

        if (!$letter) {
            return response()->json([
                'valid' => false,
                'message' => 'Kode verifikasi tidak dikenal, atau surat belum selesai.',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'number' => $letter->number,
            'type' => $letter->type?->name,
            'issued_for' => $letter->user?->name,
            'issued_at' => $letter->completed_at?->toDateString(),
            'village' => $letter->village?->name,
        ]);
    }
}
