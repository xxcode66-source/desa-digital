# Aplikasi Desa Digital

Monorepo untuk layanan desa: **Android** (warga / RT / kolektor / UMKM) + **backend
Laravel** dengan panel operator/admin. Prinsip inti & aturan fase (Fase 0–6,
*satu fase per sesi*, tanpa transaksi uang di aplikasi, `village_id` di setiap tabel,
RBAC via Policy, audit log immutabel) dijelaskan lengkap di
[`PEMBANGUNAN.md`](PEMBANGUNAN.md) — baca file itu terlebih dahulu.

> Status: **Fase 0 (Setup), Fase 1 (Auth & Verifikasi), Fase 2 (Surat / E-Layanan),
> Fase 3 (Pengaduan), Fase 4 (PBB), & Fase 5 (Marketplace UMKM) selesai.**
> Backend buildable + panel Filament (verifikasi warga + antrean surat dinamis + antrean
> pengaduan + administrasi PBB: impor database, verifikasi klaim, ACC setoran sebagian +
> moderasi UMKM: verifikasi toko, produk, laporan), Android menghasilkan APK (onboarding,
> registrasi NIK, PIN/biometrik, wizard surat dari skema, lacak status & perbaikan, formulir
> pengaduan + foto + lacak & balasan, cek NOP, klaim kepemilikan + foto, "NOP saya" &
> tunggakan, slip setoran RT/kolektor, marketplace UMKM + chat WhatsApp + toko saya),
> cetak **PDF surat siap-cetak (berkop desa + QR anti-pemalsuan)** dari backend & unduh di
> aplikasi, CI, ERD, API spec, & panduan desain.
> Registrasi **tanpa OTP/SMS berbayar** — verifikasi identitas cukup oleh operator desa.
> **Tidak ada pembayaran di aplikasi** — status `lunas` PBB hanya verifikasi internal desa;
> transaksi UMKM tetap offline (WhatsApp hanyalah jembatan ke penjual).

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
- **PDF siap-cetak + QR**: saat surat mencapai `selesai`, `LetterWorkflow` memanggil
  `LetterPdfService` (DomPDF + `chillerlan/php-qrcode`) membangkitkan berkas **berkop desa**
  (dari `Village.settings`/logo) + isi formulir + blok tanda tangan + **kode QR** berisi tautan
  verifikasi publik `public_token`. Disimpan di disk privat; diunduh warga via
  `GET /api/letters/{letter}/pdf` dan petugas via tautan cetak di panel Filament.
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
- **Unduh / Lihat PDF Surat** pada halaman lacak (hanya tampil saat `selesai`): unduh berkas
  ke cache lewat jalur ter-autentikasi, lalu buka di aplikasi pembaca PDF via `FileProvider`.

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

## Fase 4 yang sudah nyata (PBB / NPD)

> **Prinsip: tanpa transaksi uang di aplikasi.** ACC/`lunas` adalah pencatatan verifikasi
> internal desa (bukti setoran dari RT/kolektor dinilai bendahara), **bukan** bukti pelunasan
> resmi — label ini tampil di UI & di `disclaimer` API cek NOP.

### Backend
- Tabel `pbb_objects`, `pbb_bills`, `pbb_claims`, `pbb_deposit_batches`, `pbb_deposit_items`,
  `pbb_import_logs` (semua ber-`village_id`; NOP unik per desa, disimpan 18 digit murni).
- Mesin status `PbbStatus`: tagihan `belum_bayar → disetor_pending → lunas` (transisi dijaga,
  `lunas` final), klaim & item setoran `pending/approved/rejected`, batch teragregasi
  (`pending/partially_approved/approved/rejected`). + penyamaran nama WP untuk cek NOP.
- **Impor CSV/Excel database PBB** (`PbbImportService`): idempoten (upsert objek per NOP,
  tagihan per tahun — tak menurunkan status), alias header longgar, log hasil
  (`pbb_import_logs`: created/updated/skipped), tersimpan di disk privat. **ACC sebagian per
  item** (`PbbDepositService`) — bendahara menyetujui/menolak tiap tagihan dalam slip; tolak
  wajib alasan; semua ter-audit.
- API warga: cek NOP publik (nama disamarkan + throttle `pbb_nop_check`), klaim kepemilikan
  (multipart + foto bukti SPPT/surat tanah), "NOP saya" + tunggakan, riwayat klaim, unduh bukti
  (disk privat). API kolektor: tagihan lingkup RT (`scope_rt` dari pivot role), ajukan slip
  setoran (throttle `pbb_deposit_submit`), riwayat setoran. RBAC via `PbbClaimPolicy` /
  `PbbDepositBatchPolicy`.
- **155 test phpunit hijau** (unit `PbbStatus`, service impor & alur setoran ACC-sebagian,
  policy, API cek-Nop/klaim/setoran + throttle + penyamaran, smoke panel Filament).
- Filament: `PbbObjectResource` (list objek + impor CSV + detail tagihan), `PbbClaimResource`
  (verifikasi klaim + lihat bukti), `PbbDepositBatchResource` & `PbbDepositItemResource`
  (ACC sebagian per item oleh bendahara), widget dashboard `PbbStatsWidget` (realisasi tahun
  berjalan vs target, uang "ditahan" belum di-ACC, setoran menunggu > N hari).

### Android
- Modul `feature/pbb`: hub menu PBB → **Cek NOP** (nama disamarkan + disclaimer), **NOP Saya**
  (daftar + detail tunggakan), **Ajukan Klaim** (NOP + foto bukti via photo picker), dan
  **Setoran** untuk RT/kolektor (pilih tagihan lingkup RT, lampar foto slip, pantau status).
- Rupiah & status dilokalkan; pesan error teknis diterjemahkan ke bahasa warga. Entri Beranda
  ("PBB") — label netral, tanpa kata "bayar" karena tak ada pembayaran in-app.

## Fase 5 yang sudah nyata (Marketplace UMKM)

> **Prinsip: tidak ada keranjang & pembayaran di dalam aplikasi.** Transaksi terjadi
> offline antara warga dan penjual; tombol **Chat WhatsApp** hanya jembatan ke nomor
> penjual dengan pesan otomatis terisi.

### Backend
- Tabel `shops`, `products`, `product_photos`, `product_reports`, `shop_stats`
  (semua ber-`village_id`; satu toko per pemilik — unik `village_id`+`user_id`).
- Mesin status `UmkmStatus`: toko `pending → aktif/ditolak`, `aktif → nonaktif`,
  `nonaktif → aktif`, `ditolak → pending` (ajukan ulang); laporan `baru → ditindak/diabaikan`.
  Hanya toko `aktif` & tidak disembunyikan yang tampil publik (`isPubliclyVisible`).
- `UmkmService`: verifikasi (setujui/tolak/nonaktifkan), ajukan ulang oleh pemilik, penanganan
  laporan (moderasi — bisa sekalian membekukan toko), statistik penjual (dilihat & klik WA),
  dan pembangunan tautan `wa.me` bernomor internasional (dinormalisasi 0/8 → 62). Semua
  transisi status ter-audit.
- API: kategori, **browse** (cari `q`/filter kategori/"terdekat per RT"/sortir baru&populer —
  discoping ke toko aktif satu desa), detail toko & produk (+catat "dilihat"), **klik WA**
  (kembalikan `wa_url` +catat statistik), "Toko Saya" (toko + produk + statistik),
  daftar/edit toko (multipart, foto profil, ajukan ulang bila ditolak), CRUD produk
  (multipart `photos[]` maks 5, disk privat `local`), lapor produk/toko (throttle
  `umkm_report`). RBAC via `ShopPolicy`/`ProductPolicy`/`ProductReportPolicy`, throttle
  `umkm_browse`/`umkm_report`.
- **197 test phpunit hijau** (unit `UmkmStatus`, service verifikasi/moderasi/statistik/WA-link,
  policy, API browse/detail+WA/my-shop/CRUD produk/lapor + throttle, smoke panel Filament).
- Filament: `ShopResource` (verifikasi setujui/tolak/nonaktifkan + sembunyikan),
  `ProductResource` (moderasi baca-saja), `ProductReportResource` (tangani laporan —
  ditindak/abaikan, opsi bekukan toko), widget dashboard `UmkmStatsWidget`.

### Android
- Modul `feature/umkm`: **marketplace** (cari + filter kategori + sortir baru/populer/terdekat RT),
  detail toko & produk dengan **tombol Chat WhatsApp** (buka `wa.me` terisi), **Toko Saya**
  (daftar produk + statistik dilihat/klik WA), daftar/edit toko (foto profil), tambah/edit produk
  (foto maks 5 via photo picker), lapor produk/toko. Entri Beranda ("UMKM").

## Penguatan (hardening) pasca-Fase 5

Sebelum lanjut ke Fase 6, Fase 0–5 diaudit & dipertajam agar benar-benar siap lapangan:
- **Surat PDF + QR diaktifkan** — sebelumnya `pdf_path` tak pernah terisi & QR tak dibuat meski
  dijanjikan di UI/spesifikasi. Kini dibangkitkan otomatis saat `selesai` (DomPDF sudah
  terpasang, QR memakai `chillerlan/php-qrcode` bawaan Filament — tanpa paket baru), plus
  unduh ter-autentikasi di API & tautan cetak di panel + tombol **Unduh PDF** di Android.
- **Perbaikan logika UMKM** — folder foto toko saat daftar kini memakai id toko asli (bukan
  `new`), dan sortir "terdekat" pakai `CASE WHEN` portabel (sebelumnya `CAST` khusus SQLite yang
  bisa gagal di MySQL/PostgreSQL).
- `versionName` APK disinkronkan (0.6.0-fase5). **Total 203 test phpunit hijau** (547 assert),
  `assembleDebug` sukses.

## Reference fase berikutnya (JANGAN dibangun sekarang)
Stub lama fitur yang belum dibangun dulu disimpan di folder *reference* agar aturan
satu-fase-per-sesi terjaga. **Seluruh stub Fase 2–5 (Surat, Pengaduan, PBB, UMKM) sudah
diimplementasikan & dihapus** — tidak ada stub tersisa. Fase 6 & seterusnya akan dibangun
langsung di jalur aktif saat fasenya resmi dimulai:
- `backend/reference-future-phases/` → (kosong; hanya README catatan historis).
- `android-app/reference-future-phases/` → (kosong; hanya README catatan historis).

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
