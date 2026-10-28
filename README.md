# Aplikasi Desa Digital

Monorepo untuk layanan desa: **Android** (warga / RT / kolektor / UMKM) + **backend
Laravel** dengan panel operator/admin. Prinsip inti & aturan fase (Fase 0–6,
*satu fase per sesi*, tanpa transaksi uang di aplikasi, `village_id` di setiap tabel,
RBAC via Policy, audit log immutabel) dijelaskan lengkap di
[`PEMBANGUNAN.md`](PEMBANGUNAN.md) — baca file itu terlebih dahulu.

> Status: **Fase 0 (Setup) TUNTAS.** Fondasi nyata: backend buildable + panel admin
> Filament terpasang, Android menghasilkan APK, ada CI, ERD, API spec, & panduan desain.

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

## Reference fase berikutnya (JANGAN dibangun sekarang)
Skeleton fitur lama dipindah ke folder *reference* agar aturan satu-fase-per-sesi
terjaga, masing-masing punya README pemetaan fase:
- `backend/reference-future-phases/` → Letter (Fase 2), Complaint (Fase 3), PBB (Fase 4), UMKM (Fase 5).
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
