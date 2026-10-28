# Referensi Fase Mendatang (BUKAN kode aktif)

Folder ini menampung kerangka (skeleton) fitur yang **sudah ada sebelumnya** tapi
belum seharusnya dibangun. sesuai aturan **satu fase per sesi** di
`docs/PEMBANGUNAN.md` (bagian 8), file-file ini sengaja dikeluarkan dari jalur
aktif (`app/Http/Controllers/Api` dan `database/migrations`) supaya:

- `php artisan migrate` pada fase awal **tidak** membuat tabel pbb/umkm/complaints.
- Controller tidak didaftarkan ke router & tidak autoload (folder ini di luar `app/`).

## Pemetaan ke Fase

| File | Modul | Fase (PEMBANGUNAN.md) |
|---|---|---|
| `Controllers/PbbController.php` + `migrations/*_create_pbb_tables.php` | PBB | **Fase 4** |
| `Controllers/UmkmController.php` + `migrations/*_create_umkm_tables.php` | Marketplace UMKM | **Fase 5** |

> Stub Surat (Fase 2) & Pengaduan (Fase 3) telah **dihapus** — implementasi aslinya sudah
> hidup: `app/Http/Controllers/Api/Letter*.php` + `Complaint*.php`, model/service/
> `LetterStatus`/`ComplaintStatus`, migrasi `create_letters_tables` & `create_complaints_tables`
> aktif, plus panel Filament & 102 test hijau.

## Cara memakai saat fasenya tiba
1. Pindahkan file kembali ke `app/Http/Controllers/Api/` dan `database/migrations/`.
2. **Tulis ulang** isinya mengikuti spesifikasi lengkap di PEMBANGUNAN.md bagian 4 —
   kerangka lama ini hanya stub dan **tidak** memenuhi Definition of Done (bagian 12):
   belum ada RBAC/policy, audit log, validasi, nomor surat anti-duplikat, dsb.
3. Tambah test untuk logika kritis.

> Jangan memindahkan file ini kembali sebelum fase-nya secara resmi dimulai.
