# ERD — Skema Database (Fase 0)

Level tinggi, sesuai `PEMBANGUNAN.md` §7. **Hanya tabel fondasi yang benar-benar
dibuat di Fase 0.** Tabel fitur (surat/PBB/UMKM/pengaduan) diberi tanda _planned_
dan baru di-migrate pada fasenya masing-masing (aturan satu fase per sesi).

Prinsip: **setiap tabel data menempel `village_id`** (multi-desa) dan **aksi penting
tercatat di `audit_logs`** (immutabel).

## Tabel Fase 0 (sudah ada)

```mermaid
erDiagram
    VILLAGES ||--o{ USERS : punya
    USERS ||--o{ USER_ROLES : memegang
    ROLES ||--o{ USER_ROLES : ditugaskan
    USERS ||--o{ AUDIT_LOGS : melakukan
    USERS ||--o{ PERSONAL_ACCESS_TOKENS : punya

    VILLAGES {
        bigint id PK
        string name
        string logo_path "nullable"
        string letter_number_format "nullable, mis 470/{seq}/DS/..."
        json settings "nullable"
    }
    USERS {
        bigint id PK
        bigint village_id FK
        string nik "16, unique, nullable"
        string name
        string phone "unique"
        string email "nullable, unique (staff/operator)"
        string password
        enum status "pending|verified|rejected"
        string rt "nullable"
        string rw "nullable"
    }
    ROLES {
        bigint id PK
        string name "unique: warga|umkm|rt|kolektor|operator|kades|bendahara_pbb|admin"
        string label "nullable, tampilan Indonesia"
    }
    USER_ROLES {
        bigint id PK
        bigint user_id FK
        bigint role_id FK
        string scope_rt "nullable"
        string scope_rw "nullable"
    }
    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK "nullable"
        string action "mis pbb.deposit.approved"
        string subject_type "nullable (polimorfik)"
        bigint subject_id "nullable"
        string description "nullable"
        json properties "nullable"
    }
    PERSONAL_ACCESS_TOKENS {
        bigint id PK
        bigint tokenable_id "polimorfik (user)"
        string tokenable_type
        string name
        string token "sha256"
        json abilities
    }
```

## Tabel _planned_ (belum dibangun — rujukan fase berikutnya)

Dokumen ini hanya memetakan rencana; **jangan** dibuat migrasinya sekarang.
Referensi kode lama ada di `backend/reference-future-phases/`.

| Modul | Tabel utama | Fase |
|---|---|---|
| Surat | `letter_types`, `letters`, `letter_status_logs`, `letter_attachments` | Fase 2 |
| Pengaduan | `complaints`, `complaint_replies` | Fase 3 |
| PBB | `pbb_bills`, `pbb_claims`, `pbb_deposits`, `pbb_batches` | Fase 4 |
| UMKM | `umkm_shops`, `umkm_products`, `umkm_reports` | Fase 5 |

> Catatan: tabel `sessions`, `cache`, `jobs` bawaan Laravel juga aktif namun tidak
> digambar di ERD bisnis ini.
