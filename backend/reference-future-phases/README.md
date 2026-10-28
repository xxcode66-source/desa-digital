# Referensi Fase Mendatang (BUKAN kode aktif)

Folder ini dulu menampung kerangka (skeleton) fitur yang **sudah ada sebelumnya** tapi
belum seharusnya dibangun, sesuai aturan **satu fase per sesi** di
`docs/PEMBANGUNAN.md` (bagian 8). File-file tersebut sengaja dikeluarkan dari jalur
aktif (`app/Http/Controllers/Api` dan `database/migrations`) supaya `php artisan migrate`
pada fase awal tidak membuat tabel fitur yang belum waktunya, dan controller tidak
ter-daftar ke router.

## Status: TIDAK ADA stub tersisa

Seluruh stub Fase 0–5 sudah **diimplementasikan dan dihapus** dari folder ini:

| Fase | Modul | Implementasi aktif |
|---|---|---|
| Fase 2 | Surat / E-Layanan | `LetterController`, model/service/`LetterStatus`, migrasi `create_letters_tables`, panel Filament |
| Fase 3 | Pengaduan | `ComplaintController`, `ComplaintStatus`, migrasi `create_complaints_tables`, panel Filament |
| Fase 4 | PBB / NPD | `PbbController`, `PbbStatus`, `PbbImportService`/`PbbDepositService`, migrasi `create_pbb_tables`, panel Filament |
| Fase 5 | Marketplace UMKM | `UmkmController`, `Shop`/`Product`/`ProductPhoto`/`ProductReport`/`ShopStats`, `UmkmStatus`/`UmkmService`, `Shop`/`Product`/`ProductReport`Policy, migrasi `create_umkm_tables`, panel Filament |

> **Fase 6 & seterusnya** belum punya stub — akan dibuat langsung di jalur aktif
> saat fasenya resmi dimulai, mengikuti spesifikasi lengkap PEMBANGUNAN.md bagian 4
> dan Definition of Done bagian 12 (RBAC/policy, audit log, validasi, dsb.).

Folder ini dipertahankan hanya sebagai catatan historis; tambahkan stub baru ke sini
bila kelak ada kerangka fitur yang perlu "diparkir" di luar jalur aktif.
