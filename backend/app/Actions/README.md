# Actions

Placeholder struktur (Fase 0). Kelas Actions dipakai untuk **operasi domain reusable**
yang perlu dipanggil dari beberapa tempat (Controller, Filament Resource, Job, Seeder)
tanpa menggembungkan Controller.

Konvensi: satu action = satu verb/frasa kerja, mis.
- `IssueLetter` (Fase 2) — penomoran otomatis anti-duplikat + generate PDF.
- `ImportPbb` (Fase 4) — import CSV/Excel dengan validasi + log + re-idempoten.
- `RecordPbbDeposit` (Fase 4) — setoran RT/kolektor + audit log.

Bedanya dengan `app/Services`: Service = komponen ber-state/utilitas lintas-fitur
(mis. `AuditLogger`), Action = langkah bisnis sekali jalan dengan input/output jelas.
