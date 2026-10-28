# Policies

Placeholder struktur (Fase 0). Isinya **belum** logika bisnis — baru dibuat saat
Fase 1 (Auth & RBAC) sesuai `PEMBANGUNAN.md` §4.1 dan aturan "satu fase per sesi".

Yang direncanakan masuk ke sini:
- `UserPolicy`   — verifikasi akun, update profil.
- `VillagePolicy` — kelola data desa (multi-desa, `village_id` wajib).
- per-modul (Fase berikutnya): `LetterPolicy`, `PbbPolicy`, `UmkmPolicy`, `ComplaintPolicy`.

Laravel auto-discovery: policy `App\Policies\{Model}Policy` otomatis terikat ke model
dengan nama sama, jadi tidak wajib daftar manual di `AuthServiceProvider`.
