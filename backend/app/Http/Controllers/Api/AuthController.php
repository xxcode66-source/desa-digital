<?php
// Fase 0 fondasi: login token-based via Sanctum + profil + logout.
// Fase 1 (PEMBANGUNAN.md 4.1 & 5): registrasi NIK + OTP verifikasi nomor HP,
// persetujuan data (consent), dan logout-all. Verifikasi identitas tetap oleh
// operator di panel Filament (status pending -> verified/rejected).
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\NikValid;
use App\Services\AuditLogger;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('phone', $credentials['phone'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Nomor HP atau password salah.'], 401);
        }

        // Nomor HP harus terverifikasi OTP sebelum token diberikan.
        if (!$user->isPhoneVerified()) {
            return response()->json([
                'message' => 'Nomor HP belum diverifikasi. Silakan lengkapi verifikasi.',
                'code' => 'phone_unverified',
            ], 403);
        }

        AuditLogger::record('auth.login', $user, 'Login berhasil ke aplikasi warga.');

        return response()->json([
            'token' => $user->createToken('android')->plainTextToken,
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Kirim OTP untuk registrasi. Nomor belum boleh terdaftar. Kode disimpan ter-hash;
     * only returned in local/testing untuk memudahkan uji end-to-end tanpa gateway WA/SMS.
     */
    public function sendRegisterOtp(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'phone' => [
                'required', 'string', 'regex:/^(\+?62|0)8[1-9][0-9]{6,11}$/',
                Rule::unique('users', 'phone'),
            ],
        ]);

        $phone = $this->normalizePhone($data['phone']);
        $code = $otp->issue($phone, 'register');

        $payload = ['message' => 'Kode verifikasi telah dikirim.'];
        if (app()->environment(['local', 'testing'])) {
            $payload['debug_otp'] = $code; // JANGAN di produksi.
        }

        return response()->json($payload);
    }

    /**
     * Registrasi warga: validasi NIK + OTP + consent, buat user status pending,
     * role 'warga', catat consent & verifikasi nomor. Token diberikan agar warga bisa
     * login & memantau status verifikasinya (fitur terbatas sampai operator menyetujui).
     */
    public function register(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'village_id' => ['required', 'integer', Rule::exists('villages', 'id')],
            'nik' => ['required', 'string', 'digits:16', 'unique:users,nik', new NikValid],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^(\+?62|0)8[1-9][0-9]{6,11}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'otp_code' => ['required', 'string', 'digits:6'],
            'consent' => ['accepted'],
        ], [
            'nik.digits' => 'NIK harus 16 digit angka.',
            'nik.unique' => 'NIK sudah terdaftar.',
            'phone.unique' => 'Nomor HP sudah terdaftar.',
            'phone.regex' => 'Format nomor HP tidak valid (contoh: 081234567890).',
            'otp_code.digits' => 'Kode verifikasi harus 6 digit.',
            'consent.accepted' => 'Anda harus menyetujui persetujuan penggunaan data.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $phone = $this->normalizePhone($data['phone']);

        if (!$otp->verify($phone, $data['otp_code'], 'register')) {
            return response()->json([
                'message' => 'Kode verifikasi salah atau sudah kedaluwarsa.',
                'errors' => ['otp_code' => ['Kode verifikasi salah atau sudah kedaluwarsa.']],
            ], 422);
        }

        $user = User::create([
            'village_id' => $data['village_id'],
            'nik' => $data['nik'],
            'name' => $data['name'],
            'phone' => $phone,
            'password' => $data['password'],
            'rt' => $data['rt'] ?? null,
            'rw' => $data['rw'] ?? null,
            'status' => User::STATUS_PENDING,
            'phone_verified_at' => now(),
            'consent_given_at' => now(),
            'terms_version' => config('desa.terms_version'),
        ]);

        $user->assignRole('warga', $data['rt'] ?? null, $data['rw'] ?? null);

        AuditLogger::record('auth.register', $user, 'Registrasi warga baru (menunggu verifikasi operator).', [
            'village_id' => $user->village_id,
            'phone' => $phone,
        ]);

        return response()->json([
            'message' => 'Pendaftaran berhasil. Akun Anda sedang menunggu verifikasi operator.',
            'token' => $user->createToken('android')->plainTextToken,
            'user' => $user->load('roles'),
        ], 201);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('roles', 'village'));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    /** Cabut semua token perangkat akun ini (logout-all). */
    public function logoutAll(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();

        AuditLogger::record('auth.logout_all', $user, 'Semua sesi diakhiri dari perangkat ini.');

        return response()->json(['message' => 'Semua perangkat telah keluar.']);
    }

    /** Normalisasi nomor HP ke format lokal 08xxx agar unik & konsisten. */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '08')) {
            // sudah normal
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }
}
