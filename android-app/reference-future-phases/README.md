# Referensi Fase Mendatang — Android (BUKAN kode aktif)

Stub Compose/ViewModel untuk fitur yang belum waktunya dibangun dulu dipindah ke sini
dari `app/src/main/java/com/desa/digital/feature/` agar source set yang di-compile hanya
berisi fitur fase yang sudah dibangun (menjunjung aturan **satu fase per sesi**,
PEMBANGUNAN.md bagian 8).

## Status: TIDAK ADA stub tersisa

Stub `letter/` (Fase 2), `complaint/` (Fase 3), `pbb/` (Fase 4), & `umkm/` (Fase 5) telah
**dihapus** — implementasi aslinya sudah hidup di:
- `feature/letter/` — wizard dinamis dari `form_schema_json`, lacak status, perbaikan;
- `feature/complaint/` — formulir + foto + anonim, lacak & balasan;
- `feature/pbb/` — cek NOP, klaim + bukti foto, "NOP saya" & tunggakan, slip setoran RT/kolektor;
- `feature/umkm/` — marketplace (cari/sortir), detail toko/produk + tombol Chat WhatsApp,
  "Toko Saya" + CRUD produk (foto maks 5), lapor produk/toko.

> **Fase 6 & seterusnya** belum punya stub — dibangun langsung di `feature/` saat fasenya
> resmi dimulai, mengikuti spesifikasi PEMBANGUNAN.md bagian 4 & Definition of Done bagian 12.

Folder ini dipertahankan hanya sebagai catatan historis; tambahkan stub baru ke sini bila
kelak ada kerangka fitur yang perlu "diparkir" di luar source set aktif.
