# PEMBANGUNAN.md — Aplikasi Desa Digital (Android Native)

> Dokumen ini adalah **sumber kebenaran** proyek. Setiap AI agent / developer WAJIB membaca file ini sebelum menulis kode. Kerjakan **satu fase per sesi**, jangan loncat fase.

---

## 1. Visi

Satu aplikasi Android untuk seluruh urusan desa: administrasi surat, PBB, UMKM, dan pengaduan, dengan UI modern yang mudah dipakai warga awam (termasuk orang tua) dan alur kerja yang rapi untuk perangkat desa.

**Prinsip desain:**
1. **Mudah dulu, lengkap kemudian.** Warga umur 60 tahun harus bisa ajukan surat tanpa dibantu.
2. **Tidak ada uang di aplikasi.** Aplikasi hanya mencatat; uang tetap tunai/offline. Ini menghindari risiko hukum dan keamanan.
3. **Semua tindakan penting tercatat** (siapa, kapan, apa) lewat audit log.
4. **Hemat kuota & tahan sinyal jelek.** Gambar dikompres, data di-cache, aksi penting bisa antre saat offline.
5. **Satu desa dulu, tapi arsitektur siap multi-desa** (kolom `village_id` di semua tabel utama).

---

## 2. Arsitektur & Tech Stack

| Lapisan | Pilihan | Alasan |
|---|---|---|
| Aplikasi Android | **Kotlin + Jetpack Compose**, MVVM, Hilt, Retrofit + OkHttp, Room (cache), WorkManager (antrean upload), CameraX, Coil, Firebase Cloud Messaging | Native sesuai permintaan, standar modern |
| Backend API | **Laravel 11 + Sanctum (token)**, MySQL/PostgreSQL | Murah di-hosting (VPS kecil), banyak developer di Indonesia |
| Panel Admin (WEB) | **Laravel Filament** | Operator desa butuh layar besar untuk verifikasi & cetak surat |
| PDF surat | DomPDF / Browsershot | Template surat dengan kop & QR |
| Storage file | Disk lokal/S3-compatible, gambar dikompres ke ±300 KB | Hemat biaya |
| Notifikasi | FCM (push) + opsional link WhatsApp | Warga lebih aktif di WA |

> **Keputusan penting:** Android = untuk **Warga, RT, Kolektor, UMKM**. Operator & Admin utama bekerja di **panel web**. Mencetak surat dan merekap data dari HP itu menyiksa. Operator boleh juga punya akses ringan via app (notifikasi + approve cepat), tapi pekerjaan berat di web.

Struktur repo:
```
/android-app      -> proyek Kotlin
/backend          -> Laravel (API + Filament admin)
/docs             -> pembangunan.md, ERD, API spec (OpenAPI)
```

---

## 3. Peran (RBAC)

| Role | Akses |
|---|---|
| `warga` | Profil, ajukan surat, cek PBB milik sendiri, pengaduan, marketplace |
| `umkm` | Warga + kelola toko/produk |
| `rt` | Warga + pengantar surat (RT/RW), setor PBB, daftar warga wilayahnya |
| `kolektor` | Sama seperti RT untuk setoran PBB, wilayah ditentukan admin |
| `operator` | Verifikasi surat, kelola pengaduan, kelola konten |
| `kades` | Persetujuan/tanda tangan surat, lihat dashboard |
| `bendahara_pbb` | Terima setoran fisik, ACC setoran |
| `admin` | Semua + kelola user, import data, pengaturan, audit log |

Satu user bisa punya beberapa role. Semua endpoint dicek lewat policy, bukan hanya di UI.

---

## 4. Modul & Alur Detail

### 4.1 Autentikasi & Profil Warga
- Registrasi: NIK, nama, no. HP (OTP via WA/SMS), password/PIN, RT/RW.
- **Verifikasi akun oleh operator** (cocokkan NIK/KK dengan data desa) sebelum bisa ajukan surat. Status: `pending`, `verified`, `rejected`.
- Data keluarga (KK) dan anggota agar warga bisa ajukan surat untuk anggota keluarga.
- Biometrik/PIN untuk buka aplikasi.

### 4.2 Surat Menyurat (E-Layanan)
**Jenis surat awal (template dinamis):** SK Usaha, SK Domisili, SK Tidak Mampu, SKCK pengantar, SK Kelahiran/Kematian pengantar, Surat Pengantar Nikah, dll. Admin bisa **menambah jenis surat sendiri** (nama, field form, template, lampiran wajib) tanpa ubah kode.

**Alur status:**
```
draft -> diajukan -> verifikasi_RT (jika perlu) -> verifikasi_operator
   -> (perlu_perbaikan <-> diajukan)
   -> menunggu_ttd_kades -> selesai (siap cetak / diambil) 
   atau ditolak (wajib ada alasan)
```
Detail:
- Warga isi form dari HP + upload lampiran (foto KTP/KK, foto usaha, dll).
- **Pengantar RT/RW** adalah syarat umum di dunia nyata; sediakan opsi: RT menyetujui lewat app.
- Operator bisa **menandai field yang salah** (bukan cuma "ditolak"), warga cukup memperbaiki bagian itu.
- Nomor surat otomatis sesuai format desa (mis. `470/012/DS/X/2026`), tidak boleh ganda.
- PDF memakai kop desa + **QR code verifikasi keaslian** (halaman publik: scan -> tampil "surat sah, dikeluarkan tanggal X untuk Y").
- Tanda tangan: tahap awal = **cetak lalu TTD basah + stempel**; fitur tanda tangan digital (gambar TTD kades) hanya jika desa memastikan legalitasnya.
- Riwayat & lacak status real-time, notifikasi push tiap perubahan status.
- Arsip: semua surat tersimpan dan bisa dicari/rekap per bulan (untuk buku agenda desa).

### 4.3 PBB (Modul Inti)

**Konsep:** Desa punya **database awal PBB** (import Excel/CSV dari Bapenda/desa) yang berisi NOP, nama wajib pajak, alamat objek, luas, dan tunggakan per tahun.

**Tabel utama:**
- `pbb_objects` — NOP (unik), nama WP tercatat, alamat objek, RT/RW, luas tanah/bangunan.
- `pbb_bills` — NOP, tahun, jumlah tagihan, denda, status (`belum_bayar`, `disetor_pending`, `lunas`).
- `pbb_claims` — klaim kepemilikan (user, NOP, bukti, status `pending/approved/rejected`).
- `pbb_deposits` — setoran dari RT/kolektor (lihat di bawah).
- `pbb_import_logs` — riwayat import & versi data.

**Fitur warga:**
1. **Cek NOP**: input NOP -> tampil nama WP tercatat (disamarkan sebagian untuk privasi, mis. "S*** B*****"), status tunggakan, tahun mana saja yang belum bayar.
2. **Klaim kepemilikan**: "NOP ini milik saya". Wajib **diverifikasi** (cocokkan nama/NIK/foto SPPT/surat tanah) oleh operator atau RT sebelum NOP muncul permanen di akun. Tanpa verifikasi, siapa pun bisa mengaku punya NOP orang lain.
3. Satu akun bisa punya banyak NOP (tanah warisan, dll.).
4. Notifikasi pengingat tunggakan menjelang jatuh tempo.

**Alur setoran RT / Kolektor (tanpa uang di aplikasi):**
```
Warga bayar tunai ke RT/Kolektor
  -> RT input setoran: pilih NOP, tahun, nominal, foto bukti (foto SPPT + foto kuitansi/serah terima)
  -> status bill: disetor_pending (BELUM lunas)
  -> RT/Kolektor menyerahkan uang fisik ke bendahara desa
  -> Bendahara/Admin mencocokkan fisik uang vs daftar pending (bisa per batch/"slip setoran")
  -> ACC -> status: dilunasi_desa / lunas
  -> (opsional tahap berikutnya) konfirmasi pelunasan resmi dari Bapenda
```
Aturan penting:
- Setoran dikelompokkan dalam **batch/slip** (misal "Setoran RT 03, 27 Okt, total Rp X, 14 NOP") supaya bendahara cukup cek satu total, bukan satu per satu.
- Admin bisa **ACC sebagian** (sebagian NOP tidak cocok -> ditolak/diminta cek ulang).
- **Timer & eskalasi:** pending > N hari -> notifikasi ke RT dan admin. Dashboard menampilkan "uang tertahan di RT".
- Foto bukti: dikompres, ada watermark waktu otomatis, tidak bisa diganti setelah dikirim (hanya ditambah).
- Setiap perubahan status tercatat di audit log (tidak bisa dihapus).
- **Pembayaran sah tetap lewat jalur resmi Bapenda/bank**; aplikasi harus memberi label jelas "status internal desa, bukan bukti pelunasan resmi" sampai ada konfirmasi resmi.

**Dashboard PBB (admin/kades):**
Total target vs terkumpul, persentase per RT/dusun, daftar tunggakan terbesar, setoran pending beserta umurnya, performa per kolektor, grafik per tahun. Export Excel/PDF.

### 4.4 Marketplace UMKM
- Warga daftar jadi pelaku UMKM -> **verifikasi operator** (cegah penipuan/produk ilegal).
- Profil toko: nama, deskripsi, kategori (makanan, jasa, kerajinan, dll.), lokasi/RT, jam buka, foto.
- Produk: foto (maks 5), nama, harga, deskripsi, status stok (tersedia/habis).
- Tombol **"Chat WhatsApp"** dengan pesan otomatis terisi (`wa.me/62...?text=Halo, saya lihat *Seblak Pedas* di Aplikasi Desa...`).
- Pencarian, filter kategori, "terdekat dari saya" (per RT/dusun), toko baru/populer.
- Laporkan produk/toko (moderasi); operator bisa menyembunyikan.
- Statistik untuk penjual: jumlah dilihat, jumlah klik WA.
- Ulasan/rating **ditunda** ke fase lanjut (rawan konflik antar tetangga).

### 4.5 Pengaduan
Kategori (jalan rusak, sampah, keamanan, pelayanan, dll.), foto + lokasi, status (`diterima`, `diproses`, `selesai`, `ditolak`), balasan petugas, riwayat. Opsi anonim ke publik tapi tetap tercatat pelapornya ke admin.

### 4.6 Modul Standar Lainnya
Pengumuman & berita desa, agenda kegiatan, profil & struktur pemerintahan desa, data statistik penduduk (agregat), transparansi APBDes (ringkasan), nomor penting/darurat, galeri. Semua dikelola dari panel web.

### 4.7 Panel Admin Web
Manajemen user & verifikasi, import data (warga, PBB), template surat, antrean surat, setoran PBB, moderasi UMKM, pengaduan, laporan & export, audit log, pengaturan desa (kop, logo, nomor surat, nama kades).

---

## 5. Keamanan & Privasi (WAJIB)

- Data NIK/KK/NOP adalah **data pribadi** (UU PDP). Enkripsi saat transit (HTTPS) dan field sensitif di database; batasi siapa bisa lihat.
- Samarkan data di tampilan publik/cek NOP.
- Rate limit pada cek NOP & login (cegah scraping data pajak orang).
- Token Sanctum dengan masa berlaku, logout semua perangkat.
- Backup database otomatis harian + uji restore.
- Audit log immutable untuk: ACC surat, setoran, perubahan status PBB, perubahan role.
- Validasi upload (tipe/ukuran), scan nama file, simpan di luar public dengan signed URL untuk dokumen sensitif (KTP/KK).
- Persetujuan penggunaan data (consent) saat registrasi + halaman Kebijakan Privasi (wajib untuk Play Store).

---

## 6. Rekomendasi UI/UX (pembeda utama aplikasi ini)

- Desain Material 3 yang diberi identitas desa sendiri (warna, ilustrasi, maskot opsional); **dark mode**.
- Beranda berupa **menu besar berikon** (Surat, PBB, UMKM, Pengaduan, Info) bukan menu bertingkat.
- Font besar, kontras tinggi, bahasa Indonesia sederhana (hindari istilah birokrasi).
- Form surat berbentuk **wizard bertahap** dengan progres, bukan satu halaman panjang.
- Kamera dengan panduan bingkai (foto KTP/SPPT) + auto-kompres.
- Mode **offline-friendly**: data terakhir di-cache, upload masuk antrean WorkManager.
- Skeleton loading, empty state yang ramah, pesan error yang jelas.
- Uji di HP kelas bawah (RAM 2-3 GB, Android 8-10).

---

## 7. Skema Database Ringkas (ERD level tinggi)

```
villages(id, name, settings...)
users(id, village_id, nik, name, phone, password, status, ...)
roles / user_roles(user_id, role, scope_rt, scope_rw)
families / family_members
letter_types(id, village_id, name, form_schema_json, template, required_attachments_json)
letters(id, village_id, letter_type_id, user_id, data_json, status, number, pdf_path, verified_by, approved_by, ...)
letter_status_logs(letter_id, from, to, note, actor_id, created_at)
pbb_objects, pbb_bills, pbb_claims, pbb_deposit_batches, pbb_deposit_items, pbb_import_logs
shops(id, user_id, name, status, wa_number, ...)
products(id, shop_id, name, price, photos, is_available)
product_reports, shop_stats
complaints, complaint_replies
announcements, events
notifications, audit_logs, device_tokens
```

---

## 8. Roadmap Per Fase

| Fase | Isi | Tujuan |
|---|---|---|
| **0** | Setup repo, CI, desain UI (Figma), ERD, API spec | Fondasi |
| **1** | Auth + verifikasi warga + profil + pengumuman + panel admin dasar | Warga bisa masuk & terverifikasi |
| **2** | **Surat menyurat** end-to-end (pilot 2-3 jenis surat) | Fitur paling terasa manfaatnya |
| **3** | Pengaduan | Mudah, menambah engagement |
| **4** | **PBB**: import, cek NOP, klaim, setoran RT/kolektor, ACC, dashboard | Fitur kompleks, dibangun setelah fondasi stabil |
| **5** | Marketplace UMKM | Ekosistem lokal |
| **6** | Pilot di 1 RT/dusun -> evaluasi -> rilis ke seluruh desa | Validasi lapangan |
| **Future** | Pesan-antar (lihat bagian 10) | Ekspansi |

Aturan: **jangan mulai fase berikutnya sebelum fase sebelumnya dipakai nyata oleh minimal beberapa warga & operator.**

---

## 9. Prompt Siap Pakai untuk AI Agent

> Tempel satu prompt per sesi. Selalu awali dengan: *"Baca `docs/pembangunan.md` terlebih dahulu."*

### Prompt Fase 0 — Setup
```
Baca docs/pembangunan.md. Buat struktur monorepo (/android-app, /backend, /docs).
Backend: inisialisasi Laravel 11 + Sanctum + Filament, konfigurasi env, struktur
folder (Actions/Services/Policies), migrasi dasar (villages, users, roles, audit_logs).
Android: proyek Kotlin + Compose + Hilt + Retrofit + Room, struktur package per fitur
(feature/auth, feature/letter, feature/pbb, ...), tema Material 3, navigasi dasar.
Tambahkan OpenAPI spec awal di /docs. Jangan membuat fitur bisnis dulu.
```

### Prompt Fase 1 — Auth & Verifikasi
```
Implementasikan modul Auth sesuai bagian 4.1 dan 5 di pembangunan.md.
Backend: registrasi (NIK, HP, OTP), login, verifikasi akun oleh operator di Filament,
RBAC via Policy, rate limiting, audit log. Android: layar onboarding, registrasi,
login, PIN/biometrik, profil, status verifikasi. Sertakan unit test untuk policy dan
validasi NIK. Jelaskan asumsi yang kamu ambil di akhir.
```

### Prompt Fase 2 — Surat
```
Implementasikan modul Surat sesuai bagian 4.2. Backend: letter_types dinamis
(form_schema_json), alur status + letter_status_logs, penomoran otomatis anti-duplikat,
generate PDF dengan kop desa dan QR verifikasi, halaman publik verifikasi QR, panel
Filament untuk antrean/verifikasi/koreksi per-field/cetak. Android: wizard pengajuan
surat, upload lampiran dengan kompres, lacak status, notifikasi FCM, perbaikan
bagian yang ditandai operator. Mulai dengan SK Usaha dan SK Domisili.
```

### Prompt Fase 4 — PBB
```
Implementasikan modul PBB sesuai bagian 4.3. Backend: import CSV/Excel (validasi,
preview, log, bisa re-import tanpa menggandakan data), cek NOP dengan data tersamarkan
+ rate limit, klaim kepemilikan dengan verifikasi, setoran RT/kolektor berbasis
batch dengan foto bukti, ACC sebagian oleh bendahara, status pending->lunas,
pengingat pending > N hari, dashboard Filament (target vs terkumpul, per RT,
uang tertahan, performa kolektor, export). Android: cek NOP, klaim, daftar NOP milik
saya + tunggakan, layar RT/kolektor untuk input setoran + kamera bukti + antrean
upload offline. Semua perubahan status wajib tercatat di audit_logs. Tambahkan label
bahwa status bukan bukti pelunasan resmi.
```

### Prompt Fase 5 — UMKM
```
Implementasikan Marketplace UMKM sesuai bagian 4.4: pendaftaran toko + verifikasi,
CRUD produk dengan foto, daftar/cari/filter, detail produk, tombol chat WhatsApp dengan
pesan terisi otomatis, laporan produk, moderasi di Filament, statistik penjual.
Jangan membuat keranjang/pembayaran.
```

---

## 10. Rencana Masa Depan: Pesan-Antar ("Gojek Desa")

**Jangan dibangun sebelum Fase 1-5 terbukti dipakai.** Pertimbangan:
- Butuh **tiga sisi** sekaligus: pembeli, penjual, kurir. Tanpa ketiganya aktif, tidak berjalan.
- Butuh fitur baru yang berat: keranjang, order, tracking, pembagian ongkir, pembayaran (tunai/COD dulu), aplikasi kurir, penanganan komplain/pembatalan.
- Pembayaran digital (QRIS/e-wallet) -> perlu payment gateway dan tanggung jawab keuangan/regulasi.

**Jalur bertahap yang aman:**
1. **Tahap A:** pesan lewat WA (sudah ada di MVP).
2. **Tahap B:** form pemesanan di aplikasi -> notifikasi ke penjual (tetap COD, antar oleh penjual sendiri).
3. **Tahap C:** daftarkan ojek/kurir desa (verifikasi), ongkir flat per zona (RT/dusun), penugasan manual dulu oleh admin.
4. **Tahap D:** otomatisasi penugasan + tracking + rekap bagi hasil. Pertimbangkan dikelola BUMDes sebagai badan penyelenggara.

---

## 11. Kritik & Risiko yang Harus Diantisipasi

1. **Scope terlalu besar.** Surat + PBB + UMKM + pengaduan + (nanti) ojek = beberapa produk berbeda. Kerjakan berurutan, bukan paralel.
2. **Operator di HP = tidak realistis.** Panel web wajib ada.
3. **Klaim NOP tanpa verifikasi = celah privasi/penipuan.** Harus ada verifikasi.
4. **Bukti foto bisa dipalsukan / uang bisa hilang di RT.** Mitigasi: batch + rekonsiliasi + timer + audit log, tapi tetap butuh SOP fisik di desa (kuitansi bertanda tangan, dll.).
5. **Status "lunas" di app belum tentu lunas di Bapenda.** Beri label yang jelas, rencanakan sinkron/konfirmasi resmi.
6. **Kualitas data awal PBB.** Jika data desa berantakan (nama ganda, NOP salah), aplikasi memperlihatkan kekacauan itu. Sediakan alat pembersihan & log import.
7. **Adopsi.** Teknologi bukan hambatan utama; kebiasaan warga dan perangkat desa. Siapkan: pelatihan, pendamping di balai desa, loket bantuan, dan **tetap melayani cara manual** selama masa transisi.
8. **Legalitas surat digital.** Konsultasikan dengan pemerintah kecamatan/dinas PMD soal format & tanda tangan; QR + TTD basah adalah jalan aman awal.
9. **Biaya & keberlanjutan.** Siapa bayar server, siapa maintenance setelah rilis? Rencanakan dari awal (dana desa / BUMDes / sistem langganan per desa jika multi-desa).
10. **Hanya Android.** Warga pengguna iPhone tidak terlayani. Pertimbangkan versi web ringan (PWA) untuk fitur cek surat/PBB sebagai cadangan.
11. **Play Store butuh** kebijakan privasi, penjelasan penggunaan data, dan akun developer. Siapkan sejak awal.

---

## 12. Definition of Done per Fitur

- [ ] Alur utama + alur error berjalan
- [ ] Role & policy dicek di backend
- [ ] Audit log tercatat untuk aksi penting
- [ ] Ada test untuk logika bisnis kritis (status, penomoran, rekonsiliasi PBB)
- [ ] UI diuji di HP kelas bawah & layar kecil
- [ ] Teks bahasa Indonesia sederhana, pesan error jelas
- [ ] Dokumentasi API & catatan asumsi diperbarui