# Panduan UI/UX — Design System (Fase 0)

Rujukan desain layar. Token di bawah **sudah diimplement** di Android
(`android-app/.../core/theme/`) sehingga dokumen & kode tidak drift.
Belum ada file Figma — dokumen ini jadi sumber kebenaran desain sampai Fase 1.

Prinsip (PEMBANGUNAN.md §6 & §11): bahasa Indonesia sederhana, ramah pengguna HP
kelas bawah & layar kecil, kontras jelas, status selalu disertai pesan yang dimengerti
warga (mis. status PBB **bukan** bukti pelunasan resmi).

## 1. Warna (Material 3 dynamic-capable)

| Token | HEX | Pakai untuk |
|---|---|---|
| `DesaGreen` | `#2E7D32` | Primary: tombol utama, app bar, aksen aktif |
| `DesaGreenLight` | `#60B25B` | Hover/disabled tint, chart |
| `DesaGreenDark` | `#00501A` | On-primary teks terang, pressed |
| `DesaAmber` | `#F9A825` | Sekunder/aksi: pending, peringatan, CTA lunak |
| `DesaAmberDark` | `#C17900` | Teks di atas amber |
| `SurfaceTint` | `#F3FBF1` | Latar terang (light mode) |
| `OnSurfaceDark` | `#E0E8DC` | Teks di atas surface gelap (dark mode) |

Warna bisa dioverride per-desa lewat `villages.settings` (branding) di Fase 1.

### Makna status (konsisten lintas modul)
- Hijau = selesai/lunas/terverifikasi
- Amber = proses/pending/menunggu
- Merah = ditolak/gagal (ditambah di fase terkait)

## 2. Tipografi
Material 3 type scale; judul besar, body minimal 16sp demi keterbacaan di HP low-end.
Font default system (Roboto) agar ringan & tidak menambah aset.

## 3. Komponen inti
- **Button**: pill, primary hijau; secondary outlined.
- **Card**: radius 16dp, shadow lembut, satu aksi jelas.
- **Status chip**: badge kecil + ikon + teks, bukan cuma warna (aksesibilitas).
- **Form**: label di atas field, pesan error inline bahasa sederhana.

## 4. Peta layar Fase 0 (fondasi)

```
[Login]                [Home]
 ┌────────────┐         ┌────────────────────┐
 │  logo desa │         │ Halo, {nama} 👋     │
 │ Desa Digital│        │ [Akun terverifikasi]│
 │            │         │ ┌──────┐ ┌──────┐   │
 │ [Nomor HP] │         │ │Surat │ │ PBB  │   │
 │ [Password] │         │ └──────┘ └──────┘   │
 │ ☑ Ingat    │         │ ┌──────┐ ┌──────┐   │
 │ (Masuk)    │         │ │ UMKM │ │Aduan │   │
 │ [Daftar]   │         │ └──────┘ └──────┘   │
 └────────────┘         │ Nav: Beranda·Lacak·Profil│
                        └────────────────────┘
```

Layar **Login** & **Home** sudah ada sebagai Compose (`feature/auth/ui/LoginScreen.kt`,
`feature/home/ui/HomeScreen.kt`). Grid layanan di Home baru jadi placeholder navigasi —
fiturnya dibangun per fase, bukan sekarang.

## 5. Navigasi dasar
`AppNavigation.kt` (Compose Navigation): `login` ↔ `home`. Rute fitur
(`letter`, `pbb`, `umkm`, `complaint`) menyusul sesuai fase — stub lama ditandai di
`android-app/reference-future-phases/`.

## 6. Aksesibilitas & offline
- Target sentuh ≥ 48dp, kontras minimal AA.
- Dark mode aktif (`values-night/themes.xml`).
- Aksi yang butuh jaringan punya antrean offline (WorkManager) — diaktifkan fase fitur.
