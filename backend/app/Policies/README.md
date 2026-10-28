# Policies

Fase 1 (Auth & RBAC) sudah menambahkan kebijakan nyata (bukan placeholder lagi):
- `UserPolicy`    — verifikasi/reject akun warga oleh operator, manageRoles (kades/admin),
                    update/delete, semuanya scoped ke satu desa (multi-tenant).
- `VillagePolicy` — view/update data desa; create/delete khusus admin.

Belum dibuat (sengaja, menunggu fasenya — aturan "satu fase per sesi"):
- per-modul: `LetterPolicy` (Fase 2), `ComplaintPolicy` (Fase 3), `PbbPolicy` (Fase 4),
  `UmkmPolicy` (Fase 5).

Prinsip RBAC `PEMBANGUNAN.md` §4.1/§5: kecuali super-admin, tindakan staff dibatasi ke
`village_id` yang sama. Verifikasi identitas warga = keputusan operator, tidak otomatis.

Laravel auto-discovery: policy `App\Policies\{Model}Policy` otomatis terikat ke model
dengan nama sama, jadi tidak wajib daftar manual di `AuthServiceProvider`.
