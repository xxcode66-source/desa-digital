# Aplikasi Desa Digital

Monorepo untuk layanan desa: **Android** (warga / RT / kolektor / UMKM) + **backend
Laravel** dengan panel operator/admin. Prinsip inti & aturan fase (Fase 0–6,
*satu fase per sesi*, tanpa transaksi uang di aplikasi, `village_id` di setiap tabel,
RBAC via Policy, audit log immutabel) dijelaskan lengkap di
[`PEMBANGUNAN.md`](PEMBANGUNAN.md) — baca file itu terlebih dahulu.

> Status: **Fase 0 (Setup), Fase 1 (Auth & Verifikasi), Fase 2 (Surat / E-Layanan), &
> Fase 3 (Pengaduan) selesai.**
> Backend buildable + panel Filament (verifikasi warga + antrean surat dinamis + antrean
> pengaduan), Android menghasilkan APK (onboarding, registrasi NIK, PIN/biometrik, wizard
> surat dari skema, lacak status & perbaikan, formulir pengaduan + foto + lacak & balasan),
> CI, ERD, API spec, & panduan desain.
> Registrasi **tanpa OTP/SMS berbayar** — verifikasi identitas cukup oleh operator desa.

## Struktur

```
desa-digital/
├── .github/workflows/ci.yml     # CI: backend (phpunit) + android (assembleDebug)
├── android-app/                 # Proyek Gradle Kotlin/Compose/Hilt (Fase 0)
├── backend/                     # Laravel 12 + Sanctum + Filament (Fase 0)
├── docs/                        # openapi.yaml, ERD.md, UI-DESIGN.md
├── PEMBANGUNAN.md               # Sumber kebenaran rencana & aturan fase
├── setup_monorepo.sh            # Bootstrap backend (+ opsional build APK)
└── build_zip.py                 # Arsip sumber (exclude artefak build)
```

## Fase 0 yang sudah nyata

### Backend (`backend/`)
- **Laravel 12** + **Sanctum** (auth token). *Catatan:* dipakai Laravel 12, bukan 11,
  karena Composer memblokir 11.x lewat security advisory — perbedaan hanya pada
  bootstrap; struktur Fase 0 identik.
- Migrasi dasar: `villages`, `users`, `roles`, `user_roles`, `audit_logs`, `sessions`.
- Model `User/Village/Role/AuditLog`, service `AuditLogger`, `RoleSeeder`.
- Route API: `GET /api/health`, `POST /api/login`, `GET /api/me`, `POST /api/logout`.
- **9 test phpunit hijau** (Auth + AuditLogger) dengan SQLite in-memory.
- **Panel admin Filament v5** (base): provider `Admin` di `/admin` + Dashboard,
  warna brand emerald. Kolom `email` nullable di `users` utk login staff/operator.
  RBAC & resource fitur (verifikasi warga, antrean surat, dsb.) **sengaja belum** —
  menyusul Fase 1+ sesuai aturan satu fase per sesi.

### Android (`android-app/`)
- Toolchain: **AGP 9.1.0 + Kotlin 2.3.20 + Gradle 9.6 + Hilt 2.60.1** (built-in Kotlin,
  new DSL) — cocok dengan JDK 25 (JBR Android Studio).
- Stack: Jetpack Compose (Material 3 + dark theme), Hilt DI, Retrofit + OkHttp +
  kotlinx.serialization, Room, WorkManager, Coil.
- Package per fitur: `feature/auth`, `feature/home`, `core/{theme,network,data,di,database,work,ui}`.
- `:app:assembleDebug` menghasilkan `app-debug.apk`.

## Fase 1 yang sudah nyata (Auth & Verifikasi)

### Backend
- Aturan `NikValid` (struktur NIK 16 digit), migrasi `add_auth_fields_to_users`
  (`consent_given_at`, `terms_version`). **Tanpa OTP/SMS pihak ketiga.**
- API: `POST /api/register` (status `pending` + role `warga` + consent UU PDP),
  `POST /api/logout-all`; warga bisa langsung login setelah daftar.
- RBAC nyata: `UserPolicy` (verify/reject/manageRoles/update, semua scoped `village_id`)
  & `VillagePolicy`. Rate limiting tiap endpoint sensitif (config `desa.php`).
- **29 test phpunit hijau** (NIK unit, register feature, RBAC Gate, + Fase 0).
- Filament: `UserResource` "Verifikasi Warga" — list multi-tenant (khusus desa operator),
  badge status, aksi **Setujui/Tolak** (ter-audit), edit role khusus kades/admin.

### Android
- Alur lengkap: onboarding → registrasi (NIK + consent) → login → **buat PIN** →
  beranda (banner status verifikasi) → profil & logout (semua perangkat).
- Kunci lokal Fase 1: `PinManager` (PIN 6 digit, hash PBKDF2 di `EncryptedSharedPreferences`)
  + `BiometricHelper` (sidik jari/credentials via `androidx.biometric`).

## Fase 2 yang sudah nyata (Surat / E-Layanan)

### Backend
- Skema dinamis: tabel `letter_types` dengan `form_schema_json` (text/textarea/select) +
  `required_attachments_json` → formulir surat apa pun bisa dibuat petugas tanpa deploy kode
  baru. Seed awal: SK Usaha & SK Domisili.
- Mesin status `LetterStatus` (diajukan → verifikasi_RT → verifikasi_operator →
  menunggu_ttd_kades → selesai; cabang perlu_perbaikan ↔ diajukan; ditolak wajib alasan),
  dilindungi `LetterWorkflow` + log riwayat immutabel + audit.
- Penomoran otomatis **anti-duplikat**: `letter_sequences` + lock-for-update, format
  `470/{seq}/DS/{bulan}/{tahun}` per desa & per tahun; surat final dapat `public_token` untuk
  verifikasi QR publik (`GET /api/verify-letter/{token}`).
- API warga: jenis surat, ajukan (multipart, maks 5 lampiran, MIME/ukuran dibatasi config),
  lacak, unduh lampiran (disk privat), perbaiki surat bertanda — hanya warga `verified`
  (`LetterPolicy`), throttle per endpoint. Verifikasi & tanda tangan via panel, bukan API.
- **70 test phpunit hijau** (mesin status, penomoran anti-dup + custom format, workflow,
  API submit/lacak/perbaikan/verifikasi publik, policy, smoke panel Filament).
- Filament: `LetterTypeResource` (CRUD jenis + editor skema dinamis) dan `LetterResource`
  "Antrean Surat" — aksi maju RT/operator/ttd/selesai, **perbaikan per-field** (petugas
  centang field mana yang salah → merah di wizard warga), tolak wajib alasan.

### Android
- Wizard 3 langkah **dirender dinamis dari `form_schema_json`** (tanpa hardcode per jenis),
  mode perbaikan menampilkan field bertanda merah + catatan petugas.
- Unggah lampiran via photo picker (maks 5, preview Coil) → multipart `data[key]` +
  `attachments[]`; lacak status (daftar + detail + riwayat lengkap), antrean masuk dari Beranda
  ("Ajukan Surat"). Pesan error teknis diterjemahkan ke bahasa warga.

## Fase 3 yang sudah nyata (Pengaduan)

### Backend
- Tabel `complaints` + `complaint_photos` + `complaint_replies` (semua ber-`village_id`).
  `complaint_replies` ganda fungsi: percakapan (balasan) **dan** kronologi (perubahan status).
- Mesin status `ComplaintStatus` (diterima → diproses → selesai/ditolak; transisi mundur
  ditolak; **penolakan wajib alasan**), dilindungi `ComplaintWorkflow` + catatan kronologi
  immutabel + audit. Kategori baku dari config `desa.complaints.categories` (jalan rusak,
  sampah, keamanan, penerangan, pelayanan, saluran air, lainnya) — mudah ditambah tanpa kode.
- API warga: kategori, ajukan (multipart `category/title/description` + `photos[]`, maks 3,
  MIME/ukuran dibatasi config, lokasi & lat/lng opsional, **opsi anonim**), lacak milik sendiri
  (`my-complaints`), detail + kronologi, balasan, unduh foto (disk privat) — hanya warga
  `verified` (`ComplaintPolicy`), throttle `complaint_submit`/`complaint_browse`.
- **Papan publik** `GET /api/complaints` (tanpa login): menyamarkan nama pelapor jadi "Warga
  anonim" untuk laporan anonim — namun identitas **tetap tercatat** & terlihat petugas.
- **102 test phpunit hijau** (mesin status, workflow transisi/alasan/audit, API submit/lacak/
  balasan/unduh/penyamaran anonim, policy, smoke panel Filament).
- Filament: `ComplaintResource` "Pengaduan Warga" — list multi-tenant, badge status, aksi
  **Proses/Selesai/Tolak** (tolak wajib alasan) + **Balasan petugas**, infolist foto & kronologi.

### Android
- Modul `feature/complaint`: formulir (pilih kategori, judul, penjelasan, lokasi opsional,
  foto bukti maks 3 via photo picker + preview Coil, **switch kirim anonim**) → multipart.
- Daftar laporan milik warga (lacak badge status), detail + kronologi (perubahan status &
  balasan petugas), kolom balasan selama laporan terbuka. Entri dari Beranda ("Pengaduan").

## Reference fase berikutnya (JANGAN dibangun sekarang)
Stub lama fitur yang belum dibangun dipindah ke folder *reference* agar aturan
satu-fase-per-sesi terjaga; stub yang sudah diimplementasikan dihapus saat fase-nya selesai
(Surat/Fase 2 & Pengaduan/Fase 3 sudah dihapus):
- `backend/reference-future-phases/` → PBB (Fase 4), UMKM (Fase 5).
- `android-app/reference-future-phases/` → stub layar fitur yang sama.

## Cara build

**Backend**
```bash
bash setup_monorepo.sh          # composer install + .env(sqlite) + key + migrate:fresh --seed + test
# atau manual:
cd backend
composer install
cp .env.example .env            # lalu set DB_CONNECTION=sqlite
php artisan key:generate
php artisan migrate:fresh --seed
php artisan test
```

**Android**
```bash
cd android-app
# set JAVA_HOME ke JDK 17+ (mis. JBR Android Studio), lalu:
./gradlew :app:assembleDebug    # Windows: gradlew.bat
```

**Arsip sumber**
```bash
python build_zip.py             # aplikasi-desa-digital.zip (sumber saja, tanpa vendor/build/.env)
```
