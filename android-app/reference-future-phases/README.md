# Referensi Fase Mendatang — Android (BUKAN kode aktif)

Stub Compose/ViewModel untuk modul **complaint (Fase 3),
pbb (Fase 4), umkm (Fase 5)** dipindah ke sini dari
`app/src/main/java/com/desa/digital/feature/` agar:

- source set yang di-compile hanya berisi fitur fase yang sudah dibangun
  (Fase 0–2: auth, home, lock, profile, letter);
- tidak ada referensi ke layar/rute fitur yang belum seharusnya dibangun
  (menjunjung aturan **satu fase per sesi**, PEMBANGUNAN.md bagian 8).

## Pemetaan fase
| Folder | Fase |
|---|---|
| `complaint/` | Fase 3 — Pengaduan |
| `pbb/` | Fase 4 — PBB |
| `umkm/` | Fase 5 — Marketplace UMKM |

> Stub `letter/` (Fase 2) telah **dihapus** — implementasi aslinya sudah hidup di
> `feature/letter/` (wizard dinamis, lacak status, perbaikan) sesuai PEMBANGUNAN.md 4.2.

## Cara memakai
Saat fasenya dimulai, pindahkan folder kembali ke `feature/`, lalu **tulis ulang**
mengikuti spesifikasi bagian 4 PEMBANGUNAN.md (wizard form, upload+kompres, offline
queue, notifikasi FCM, dsb). Stub lama tidak memenuhi Definition of Done (bagian 12).
