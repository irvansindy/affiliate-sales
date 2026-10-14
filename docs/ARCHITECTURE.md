# Architecture

## System Overview

```
Pelanggan ──(klik link referral)──► Landing/Redirect Endpoint ──► cookie + catat klik
                                                                      │
Sistem Checkout / Admin input ──(webhook / manual)──┐                 │
                                                    ▼                 ▼
Admin ───────┐                              ┌───────────────────────────────┐
             ├──► Filament Panel ─────────► │  Laravel Application          │
Affiliator ──┘    (/admin, /affiliate)      │  CRM · Actions · Services      │
                                            │  Policies                     │
                                            └──────┬─────────────┬──────────┘
                                                   │             │
                                            MySQL 8        Redis (cache, queue)
                                                   │             │
                                              Storage      Queue Worker / Scheduler
                                           (bukti payout)  (komisi, laporan, email)
```

## Tech Stack

| Layer | Pilihan | Alasan |
|-------|---------|--------|
| Backend | **Laravel 13** (PHP 8.3+) | Matang, produktif, cocok untuk aplikasi bisnis dengan banyak CRUD dan aturan |
| Admin & Affiliate UI | **Filament** (dua panel) | Tabel, filter, form, widget dashboard, dan notifikasi siap pakai; mempercepat pengembangan |
| Reaktivitas | **Livewire** + Alpine.js | Sudah menjadi fondasi Filament; tanpa SPA terpisah |
| Styling | **Tailwind CSS 4** | Terintegrasi dengan Filament theme |
| Database | **MySQL 8** | Relasional, transaksional, cocok untuk data uang |
| Cache & Queue | **Redis** | Queue komisi/laporan, cache dashboard, rate limiting |
| Auth | Autentikasi bawaan Laravel/Filament + kolom `role` | Hanya dua role, tidak perlu package permission |
| API (siap pakai) | **Laravel Sanctum** | Untuk webhook internal/mobile app affiliator di masa depan |
| Ekspor | `filament/actions` export atau Laravel Excel | CSV/Excel laporan |
| Email | SMTP / layanan transaksional (Resend, Mailgun, dsb.) | Notifikasi persetujuan, komisi, payout |
| Error monitoring | Sentry (opsional) | Pelacakan error produksi |
| Testing | Pest/PHPUnit | Wajib untuk logika komisi |
| Kualitas kode | Laravel Pint, Larastan | Konsistensi dan static analysis |
| Deployment | **Docker** (nginx + php-fpm + MySQL + Redis) | Mudah direplikasi, konsisten antar lingkungan |

**Mengapa bukan SPA terpisah (React/Next.js + API)?** Dengan hanya dua role dan fokus pada dashboard/tabel/form, pendekatan full-stack Laravel + Filament jauh lebih cepat dan murah dirawat. Jika nanti butuh aplikasi mobile affiliator, API ditambahkan di atas Service/Action yang sama (Sanctum).

## Project Structure

```
app/
├── Actions/            # Satu aksi bisnis per class (ApproveCommission, CreatePayout, ...)
├── Enums/              # PurchaseType, CommissionStatus, PayoutStatus, AffiliateStatus, UserRole
├── Filament/
│   ├── Admin/          # Panel admin: Resources, Pages, Widgets
│   └── Affiliate/      # Panel affiliator: Resources, Pages, Widgets
├── Http/
│   ├── Controllers/    # ReferralRedirectController, WebhookController
│   └── Middleware/     # EnsureRole, CaptureReferral
├── Models/
├── Policies/
├── Services/           # CommissionCalculator, AttributionService, ReportService
├── Jobs/               # Pembuatan komisi, ekspor laporan, notifikasi
└── Notifications/
database/
├── migrations/
├── factories/
└── seeders/
lang/id/                # Teks antarmuka Bahasa Indonesia
resources/
├── css/                # Theme Filament + design tokens
└── views/
routes/
├── web.php             # Redirect referral
└── api.php             # Webhook (signature-verified)
tests/
├── Feature/
└── Unit/
docs/                   # PRD, AGENTS, DESIGN_SYSTEM, ARCHITECTURE
docker/                 # Dockerfile, nginx conf
```

## Panel & Akses

| Panel | Path | Role | Isi |
|-------|------|------|-----|
| Admin | `/admin` | `admin` | Semua data, termasuk CRM, dan pengaturan |
| Affiliate | `/affiliate` | `affiliate` (status `active`) | Hanya data dan CRM milik sendiri |

Gate panel Filament + Policy memastikan role tidak dapat saling mengakses.
Implementasi panel memakai middleware autentikasi Filament dengan `User::canAccessPanel()`. Policy model ditemukan otomatis oleh Laravel; Resource admin Affiliate memakainya untuk akses daftar/detail dan aksi status.

Registrasi publik di `/affiliate/register` memakai validasi dan rate limiting bawaan Filament. Action `RegisterAffiliate` membuat User role affiliate dan Affiliate pending dalam satu transaksi. Setelah registrasi, sesi login ditutup dan pengguna kembali ke login dengan pesan menunggu persetujuan.
Alur bawaan Filament memancarkan event Registered/Login sebelum sesi tersebut ditutup. Listener di masa depan wajib membedakan event registrasi/login dari persetujuan affiliator; status active hanya berasal dari aksi admin.

`ChangeAffiliateStatus` mengotorisasi actor, mengunci record affiliator, lalu menyimpan status dan audit log secara atomik melalui `WriteAuditLog`. Transisi: pending → active/rejected, active → suspended, suspended/rejected → active. Persetujuan/reaktivasi mengisi `approved_at`; penolakan/suspend mempertahankannya. Pengulangan status yang sama oleh admin tidak menulis ulang audit atau tanggal.

Policy awal mengizinkan pembacaan data internal oleh admin dan pembacaan transaksi/komisi/payout/klik milik affiliator aktif. Operasi edit/delete umum ditolak; operasi tulis domain akan diizinkan melalui Action dan Policy tugas terkait. Query Resource affiliator tetap wajib di-scope saat dibuat pada T10.

## Data Model (ringkas)

```
users               id, name, email, password, role (admin|affiliate), ...
affiliates          id, user_id, referral_code (unique), status, phone,
                    bank_name, bank_account_name, bank_account_number (encrypted),
                    approved_at, ...
products            id, code, name, type (website|mobile_app), landing_url?, is_active
referral_clicks     id, affiliate_id, product_id?, ip_hash, user_agent, clicked_at
transactions        id, external_ref (unique), product_id, affiliate_id?,
                    source (webhook|manual), purchase_type (subscription|one_time),
                    sale_type (new|renewal|plan_change),
                    subscription_plan?, subscription_cycle?,
                    customer_name, customer_email,
                    base_amount (bigint, harga kotor sebelum PPN),
                    discount_amount (bigint, default 0, hanya pelaporan),
                    tax_amount (bigint), total_paid (bigint),
                    status (paid|refunded|cancelled), paid_at,
                    refund_amount?, refunded_at?,
                    needs_review (bool), review_note?, ...
webhook_events      id, event_id (unique), event, payload (json),
                    status (received|processed|failed), processed_at?, error?
commissions         id, transaction_id (unique), affiliate_id,
                    base_amount, rate (decimal 5,2 — snapshot), amount (bigint),
                    status (pending|approved|paid|rejected|cancelled),
                    approvable_at, ready_for_review_at?, approved_at, payout_id?
payouts             id, affiliate_id, total_amount, status,
                    bank_name, bank_account_name, bank_account_number (snapshot terenkripsi),
                    paid_at, proof_path, note
audit_logs          id, user_id, action, subject_type, subject_id, old, new, created_at
settings            key, value  (default_commission_rate=10, approval_delay_days=14,
                                 min_payout=100000)
```

### Model CRM yang direncanakan

Tabel berikut ditambahkan lewat migrasi baru pada tugas CRM; belum termasuk skema yang sudah terpasang.

```
crm_contacts        id, affiliate_id, name, email?, phone?, company?, timestamps
crm_leads           id, affiliate_id, contact_id, product_id, stage,
                    notes?, timestamps
crm_activities      id, lead_id, author_user_id, type, summary,
                    occurred_at?, due_at?, completed_at?, timestamps
```

- `crm_contacts.affiliate_id` dan `crm_leads.affiliate_id` menentukan pemilik. Kontak pada prospek harus milik affiliator yang sama; validasi ini dilakukan di server.
- Admin dapat melihat dan mengelola semua record. Affiliator aktif hanya dapat melihat dan mengubah record miliknya, termasuk aktivitas melalui relasi prospek. Policy dan query Resource sama-sama membatasi akses.
- `crm_leads.stage` menggunakan enum `new|contacted|proposal|negotiation|won|lost`. `crm_activities.type` membedakan catatan interaksi dan tindak lanjut; tindak lanjut memiliki `due_at` serta `completed_at` saat selesai.
- Tambahkan indeks pada `affiliate_id`, `contact_id`, `lead_id`, `stage`, dan `due_at` sesuai query daftar/filter. Data kontak tidak tersedia melalui endpoint publik.
- `transactions.customer_name` dan `customer_email` tetap merupakan snapshot dari sumber transaksi. CRM tidak melakukan pencocokan otomatis dengan data tersebut pada versi 1.

Catatan desain:
- Uang disimpan sebagai **bigint rupiah**.
- `commissions.transaction_id` bersifat **unique** agar pembuatan komisi idempoten.
- `commissions.rate` adalah snapshot saat komisi dibuat.
- Soft delete pada `transactions`, `commissions`, `payouts`.
- Index pada `affiliate_id`, `status`, `paid_at`, `referral_code`.
- Kolom rekening dienkripsi (`encrypted` cast).

## State Machine

**Komisi**
```
pending ──► approved ──► paid
   │            │
   └──► rejected / cancelled   (refund, batal, atau tidak valid)
```

**Payout:** `processing` → `paid` | `failed`

Transisi hanya lewat Action tertentu dan selalu menulis `audit_logs`.

## Data Flow

**Atribusi**
1. Pelanggan membuka `https://domain/r/{referral_code}?product=KODE_PRODUK`.
2. `ReferralRedirectController` memvalidasi affiliator dan produk aktif serta URL tujuan produk, mencatat klik, menyimpan cookie (30 hari, *last-click*), lalu redirect ke URL tujuan produk dengan parameter `ref`.
3. Saat pembelian, kode referral dibawa ke transaksi (parameter/metadata checkout).

**CRM**
1. Affiliator aktif mencatat kontak miliknya, membuat prospek untuk produk yang dipilih, lalu memperbarui tahap dan mencatat aktivitas atau jadwal tindak lanjut.
2. Admin memantau seluruh prospek dan dapat memperbaiki data. Setiap operasi tulis memverifikasi kepemilikan kontak, prospek, dan aktivitas di server.
3. Tahap `won` hanya menandai hasil prospek. Pembayaran tetap dicatat melalui `RecordTransaction`; CRM tidak memanggil `CommissionCalculator`.

**Pencatatan transaksi & komisi**
1. Transaksi masuk lewat dua jalur: **webhook** (signature + idempotency key) dan **input manual admin**. Keduanya memanggil Action yang sama (`RecordTransaction`) sehingga atribusi dan komisi konsisten.
2. `AttributionService` menentukan `affiliate_id` (tolak self-referral dan affiliator non-aktif).
3. Job `CreateCommissionForTransaction` memanggil `CommissionCalculator`:
   - `amount = round(base_amount × rate / 100)`
   - `base_amount` = harga kotor produk sebelum PPN.
   - Komisi hanya dibuat jika `sale_type = new`; perpanjangan (`renewal`) dan perubahan paket (`plan_change`) dicatat tanpa komisi.
4. Komisi dibuat berstatus `pending` dengan `approvable_at = paid_at + approval_delay_days`.
5. Scheduler harian mengisi `ready_for_review_at` pada komisi `pending` yang masa tunggunya lewat dan transaksinya masih dibayar. Status tetap `pending`; pengulangan command tidak mengubah waktu penandaan.
6. Hanya admin dapat menyetujui komisi setelah `approvable_at` atau menolak komisi `pending` kapan saja. `ChangeCommissionStatus` mengunci transaksi lalu komisi, memverifikasi status pembayaran, dan menulis perubahan beserta audit log dalam satu transaksi database. Tidak ada approval otomatis.

**Pembayaran**
1. Admin memilih komisi `approved` yang belum terikat payout, milik satu affiliator, dan totalnya memenuhi `settings.min_payout`. Data rekening harus lengkap.
2. `CreatePayout` mengunci komisi dalam transaksi database, membuat payout `processing`, menyalin snapshot rekening terenkripsi, dan mengisi `commissions.payout_id`. Status komisi tetap `approved`.
3. Setelah transfer berhasil, bukti disimpan pada disk privat. `MarkPayoutPaid` mengunci payout/komisi, memvalidasi ulang seluruh komisi dan total, lalu mengubah keduanya menjadi `paid` secara atomik. Bukti ditampilkan lewat signed URL sementara.
4. Jika transfer gagal, `FailPayout` mengubah payout menjadi `failed` dan melepas `payout_id` komisi; komisi yang masih `approved` dapat dimasukkan ke payout baru. Komisi yang batal akibat refund tetap `cancelled`. Riwayat keanggotaan batch gagal ada di audit log.
5. Seluruh aksi dan perubahan status ditulis ke audit log dalam transaksi. Email hasil akhir diantrekan setelah perubahan database selesai. Payout `paid` dan `failed` terminal.

**Refund**
Transaksi `refunded` → komisi `pending/approved` menjadi `cancelled`. Jika komisi sudah `paid`, komisi **tetap dibayarkan** (tanpa clawback) dan kejadian dicatat di audit log. Logika ini diisolasi di satu Action (`HandleRefund`) agar mudah diubah jika kebijakan berganti.

## Webhook Contract (Draft)

Draf kontrak antara sistem checkout (pengirim) dan AffiliateHub (penerima). Ini usulan awal yang bisa diserahkan ke pihak yang mengelola sistem checkout.

### Bagaimana kode referral sampai ke webhook

1. Affiliator membagikan link `https://<domain-affiliatehub>/r/{referral_code}?product=KODE_PRODUK`.
2. AffiliateHub mencatat klik, menyimpan cookie, lalu **redirect** ke `products.landing_url` dengan parameter `ref={referral_code}` (mengganti `ref` lama bila ada). Produk tanpa URL tujuan tidak dapat dipromosikan lewat link ini.
3. Sistem checkout menyimpan nilai `ref` bersama pesanan pelanggan.
4. Saat pembayaran sukses, sistem checkout mengirim `referral_code` tersebut di webhook.

Jika checkout berada di domain yang sama, `ref` dapat dibaca dari cookie; prinsipnya tetap sama: **checkout wajib menyimpan kode referral dan mengirimkannya kembali**.

### Endpoint

`POST /api/webhooks/transactions`

| Header | Isi |
|--------|-----|
| `Content-Type` | `application/json` |
| `X-Timestamp` | Unix timestamp saat dikirim (ditolak jika selisih > 5 menit) |
| `X-Signature` | `sha256=` + HMAC-SHA256(`X-Timestamp` + `.` + raw body, `WEBHOOK_SECRET`) |
| `Idempotency-Key` | Sama dengan `event_id` |

### Event: `payment.succeeded`

```json
{
  "event_id": "evt_01J9XYZ",
  "event": "payment.succeeded",
  "occurred_at": "2026-10-12T14:30:00+07:00",
  "transaction": {
    "external_ref": "INV-2026-000123",
    "sale_type": "new",
    "purchase_type": "subscription",
    "product": { "code": "WEBSITE_PRO", "type": "website" },
    "referral_code": "AB12CD",
    "base_amount": 5000000,
    "discount_amount": 0,
    "tax_amount": 550000,
    "total_paid": 5550000,
    "currency": "IDR",
    "paid_at": "2026-10-12T14:30:00+07:00",
    "subscription": { "plan": "yearly", "cycle": 1 }
  },
  "customer": { "name": "PT Contoh", "email": "kontak@contoh.co.id" }
}
```

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `event_id` | Ya | ID unik event; dipakai untuk idempotensi |
| `transaction.external_ref` | Ya | Nomor invoice/pesanan unik di sistem checkout |
| `transaction.sale_type` | Ya | `new` (penjualan awal), `renewal` (perpanjangan), `plan_change` (upgrade/downgrade). **Hanya `new` yang berkomisi.** |
| `transaction.purchase_type` | Ya | `subscription` atau `one_time` |
| `transaction.product.code` | Ya | Kode produk, harus cocok dengan tabel `products` |
| `transaction.referral_code` | Untuk `new` | Boleh `null` jika pelanggan tidak datang dari link referral |
| `transaction.base_amount` | Ya | Harga kotor produk sebelum PPN (integer rupiah). Dasar komisi. |
| `transaction.discount_amount` | Ya | Diskon (integer rupiah), 0 jika tidak ada. Hanya untuk pelaporan. |
| `transaction.tax_amount` | Ya | PPN (integer rupiah) |
| `transaction.total_paid` | Ya | Total yang dibayar pelanggan |
| `transaction.paid_at` | Ya | Waktu pembayaran (ISO 8601) |
| `transaction.subscription` | Jika subscription | `plan` dan `cycle` (1 = pembayaran pertama) |
| `customer.name`, `customer.email` | Ya | Data pelanggan |

### Event: `payment.refunded`

```json
{
  "event_id": "evt_01J9ABC",
  "event": "payment.refunded",
  "occurred_at": "2026-10-20T09:00:00+07:00",
  "transaction": {
    "external_ref": "INV-2026-000123",
    "refund_amount": 5550000,
    "refunded_at": "2026-10-20T09:00:00+07:00"
  }
}
```

### Aturan pemrosesan

- Verifikasi signature dan timestamp sebelum memproses apa pun. Gagal → `401`.
- Payload tidak valid (field wajib hilang, `total_paid` tidak sama dengan `base_amount - discount_amount + tax_amount`, produk tidak dikenal) → `422`, tidak disimpan.
- Nilai uang dan siklus subscription pada JSON wajib bertipe integer, bukan string numerik. Header `Content-Type` wajib JSON.
- `event_id` atau `external_ref` yang sudah pernah diproses → balas `200` tanpa efek (duplikat aman).
- Jika `event_id` yang sama masih `received` atau `failed`, retry pengirim tetap mendapat `200` tetapi job dijadwalkan ulang; pemrosesan job idempoten sehingga penjualan/komisi/refund tidak digandakan.
- Berhasil diterima → `202`. Payload disimpan dulu, lalu diproses oleh job (atribusi dan komisi).
- Refund yang tiba sebelum transaksi pembayaran tersedia → `503` tanpa menyimpan event, agar pengirim mencoba ulang. Setiap refund, termasuk sebagian, membatalkan komisi `pending`/`approved`; komisi `paid` tetap dibayarkan tanpa clawback. Refund berikutnya untuk transaksi yang sudah `refunded` dianggap duplikat.
- `referral_code` tidak ditemukan atau affiliator non-aktif → transaksi tetap dicatat tanpa affiliator dan ditandai untuk ditinjau admin; tidak ada komisi.
- Self-referral (email pelanggan sama dengan email affiliator) → transaksi dicatat, komisi tidak dibuat, ditandai untuk ditinjau.
- Pengirim sebaiknya retry dengan exponential backoff jika menerima `5xx` atau timeout.
- Setiap percobaan ulang harus memakai `X-Timestamp` baru dan menghitung ulang `X-Signature`; signature lama akan ditolak setelah lewat 5 menit.
- Input manual admin memakai field yang sama (formulir di panel admin) dan melewati Action `RecordTransaction` yang sama.

## Database & Storage

- **MySQL 8:** data utama.
- **Redis:** cache widget dashboard, queue, rate limiter, session (opsional).
- **File storage:** bukti transfer payout (disk privat, akses via signed URL).
- **Backup:** dump database terjadwal harian + retensi; uji restore berkala.

## External Services

| Layanan | Fungsi | Status |
|---------|--------|--------|
| Sistem checkout/produk | Mengirim webhook transaksi (selain input manual admin) | Wajib; format payload perlu disepakati |
| SMTP / email transaksional | Notifikasi | Wajib |
| Sentry | Error monitoring | Opsional |
| Bank/e-wallet payout API | Payout otomatis | Di luar scope v1 |

## Deployment

- Container: `app` (php-fpm), `nginx`, `mysql`, `redis`, `queue` (worker), `scheduler`.
- Environment: `local`, `staging`, `production` dengan `.env` terpisah.
- HTTPS wajib; terminasi TLS di reverse proxy.
- Perintah rilis: `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`, `filament:optimize`.
- Queue worker dikelola supervisor/restart policy Docker.
- CI: jalankan Pint, Larastan, dan test pada setiap pull request.

## Scalability & Operational Notes

- Dashboard memakai agregasi dengan index + cache singkat (1–5 menit).
- Laporan/ekspor besar diproses lewat queue.
- Webhook diproses asinkron (simpan dulu, proses di job) agar respons cepat dan aman terhadap retry.
- Monitoring: failed jobs, antrian, error rate, dan alert untuk selisih komisi.
- Rate limiting pada login, registrasi, redirect referral, dan webhook.
- Audit log untuk semua aksi sensitif (approve komisi, payout, ubah rate, suspend affiliator).
- Jika kelak ada aplikasi mobile affiliator, tambahkan `routes/api.php` + Sanctum yang memanggil Action/Service yang sama.
- Diskon kode referral untuk pelanggan belum ada di v1. Kolom `discount_amount` sudah tersedia untuk pelaporan; saat fitur diskon referral dibuat, tinjau ulang dasar komisi.
