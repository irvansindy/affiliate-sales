# Roadmap & Status Pengerjaan

Dokumen ini adalah sumber kebenaran untuk **apa yang sudah selesai dan apa tugas berikutnya**. Agent wajib membacanya sebelum mulai, mengerjakan **satu tugas per sesi**, dan memperbarui status di sini setelah selesai.

Aturan umum ada di `AGENTS.md`. Scope dan aturan bisnis ada di `docs/PRD.md`, kontrak webhook di `docs/ARCHITECTURE.md`.

## Cara kerja per tugas

1. Baca `AGENTS.md`, dokumen terkait, dan tugas di bawah.
2. Kerjakan hanya tugas itu. Jangan menyentuh tugas lain.
3. Tulis test untuk logika bisnis; jalankan `php artisan test` dan `./vendor/bin/pint`.
4. Perbarui status tugas di file ini, lalu commit (satu tugas = satu commit/PR).
5. Jika ada keputusan bisnis yang belum ada, **berhenti dan tanyakan**; jangan berasumsi, terutama soal uang/komisi.

## Status

### Selesai
- [x] Dokumen: PRD, AGENTS, DESIGN_SYSTEM, ARCHITECTURE
- [x] Migrasi, enum, dan model awal (users+role, affiliates, products, referral_clicks, webhook_events, transactions, payouts, commissions, audit_logs, settings)
- [x] Seeder: setting default (rate 10, masa tunggu 14 hari, minimum payout 100000) dan akun admin dari `.env`
- [x] `CommissionCalculator` dan `CreateCommissionForTransaction` beserta test
- [x] T1: panel Filament `/admin` dan `/affiliate`, theme, serta akses berbasis role/status affiliator
- [x] T2: registrasi affiliator pending, Resource admin, approve/reject/suspend/reaktivasi, audit log, dan Policy untuk semua model
- [x] T3: Resource admin produk dengan create/edit, pencarian, filter, status aktif/nonaktif, dan Policy admin

### Perlu dipastikan lebih dulu (T0)
- [x] `php artisan test` hijau (28 test, 53 assertion pada penyelesaian T1).

## Tugas berikutnya (urut)

### T1. Panel Filament dan akses berbasis role — Selesai
- Pasang Filament (versi terbaru yang kompatibel dengan Laravel 13; catat versinya di `AGENTS.md` setelah terpasang).
- Buat dua panel: `/admin` dan `/affiliate`.
- `User` mengimplementasikan `FilamentUser::canAccessPanel`: admin hanya ke `/admin`; affiliator **berstatus `active`** hanya ke `/affiliate`.
- Terapkan theme sesuai `docs/DESIGN_SYSTEM.md` (token warna, Inter, radius).
- Test: affiliator tidak bisa membuka `/admin`, admin tidak bisa membuka `/affiliate`, affiliator `pending`/`suspended` ditolak.

### T2. Registrasi affiliator dan manajemen affiliator (admin) — Selesai
- Halaman registrasi publik: membuat `User` (role `affiliate`) + `Affiliate` berstatus `pending`.
- Admin Resource Affiliate: daftar, detail, aksi approve / reject / suspend (mengisi `approved_at`), semua tercatat di `audit_logs`.
- Buat helper/Action audit log yang dipakai ulang oleh tugas-tugas berikutnya.
- Policy untuk semua model; Resource wajib memakai Policy.
- Reaktivasi `suspended` dan `rejected` ke `active` diizinkan; mengisi ulang `approved_at` dan mencatat audit.
- Registrasi di `/affiliate/register` membuat akun pending secara atomik, lalu keluar dari sesi dan kembali ke login dengan pesan menunggu persetujuan.
- Aksi umum edit/delete dinonaktifkan. Policy data affiliator membatasi akses per pemilik aktif; akses baca data internal hanya admin. Izin tulis fitur berikutnya ditambahkan lewat Action/Policy pada tugas terkait.
- Verifikasi: `php artisan test` lulus 62 test/244 assertion; Pint dan build produksi lulus.

### T3. Produk (admin) — Selesai
- Resource Product (code unik, nama, tipe website/mobile_app, aktif/nonaktif).
- Halaman daftar, tambah, dan edit tersedia di `/admin/products`; produk tidak dihapus, tetapi dinonaktifkan agar referensi transaksi tetap utuh.
- Tabel mendukung pencarian kode/nama, filter tipe/status, pagination, empty state, dan tampilan responsif bawaan Filament.
- ProductPolicy mengizinkan admin membaca, membuat, dan mengubah produk; affiliate ditolak dan operasi delete tetap dilarang.
- Verifikasi: `php artisan test` lulus 75 test/327 assertion; Pint dan build produksi lulus.

### T4. RecordTransaction + AttributionService + input manual admin
- Action `RecordTransaction` (satu-satunya pintu masuk transaksi, dipakai webhook dan input manual).
- `AttributionService`: tentukan `affiliate_id` dari `referral_code`; kode tidak dikenal / affiliator non-aktif / self-referral → transaksi tetap dicatat tanpa komisi, `needs_review = true` + `review_note`.
- Setelah transaksi tersimpan, panggil `CreateCommissionForTransaction`.
- Form input manual di panel admin (pilih produk, affiliator/kode referral, tipe pembelian, `sale_type`, nominal).
- Validasi: `total_paid = base_amount - discount_amount + tax_amount`.
- Test: semua kombinasi atribusi dan `sale_type`.

### T5. Endpoint webhook
- `POST /api/webhooks/transactions` sesuai kontrak di `docs/ARCHITECTURE.md` (*Webhook Contract*).
- Verifikasi signature HMAC + timestamp (toleransi 5 menit), idempotensi via `webhook_events.event_id`, simpan payload dulu lalu proses lewat job.
- Event `payment.succeeded` → `RecordTransaction`; `payment.refunded` → `HandleRefund` (T9).
- Balasan: 401 signature salah, 422 payload tidak valid, 200 duplikat, 202 diterima.
- Test: signature salah, timestamp kedaluwarsa, duplikat, payload tidak valid, alur sukses.

### T6. Link referral
- `GET /r/{referral_code}`: validasi kode, catat `referral_clicks` (IP di-hash), set cookie 30 hari (*last-click*), redirect ke halaman produk dengan `?ref=KODE`.
- Rate limiting pada endpoint ini.

### T7. Persetujuan komisi
- Perintah terjadwal harian: komisi `pending` yang `approvable_at`-nya sudah lewat ditandai siap/`approved` (sesuai keputusan di PRD; admin tetap bisa approve/reject manual).
- Action transisi status komisi dengan audit log; Resource Commission di panel admin.

### T8. Payout
- Action `CreatePayout` (komisi `approved` milik satu affiliator, total ≥ minimum payout dari `settings`).
- Aksi admin "tandai paid" + unggah bukti transfer (disk privat, signed URL); komisi terkait menjadi `paid`.
- Notifikasi email ke affiliator.

### T9. Refund
- Action `HandleRefund`: refund sebelum komisi `paid` → komisi `cancelled`; refund setelah `paid` → komisi tetap dibayarkan (tanpa clawback), dicatat di audit log.

### T10. Panel affiliator
- Dashboard (klik, penjualan, komisi pending/approved/paid), daftar penjualan, daftar komisi, riwayat payout, profil + data rekening.
- Semua query ter-scope ke affiliator yang login. Test: affiliator A tidak bisa melihat data affiliator B.

### T11. Dashboard admin dan laporan
- Widget ringkasan (penjualan, komisi, top affiliator, tren).
- Ekspor CSV/Excel lewat queue.

### T12. Produksi
- Docker (nginx + php-fpm + MySQL + Redis + queue worker + scheduler), `.env` produksi, backup database, HTTPS.

## Area yang wajib direview manusia sebelum merge

Kode komisi, payout, refund, verifikasi webhook, dan Policy. Tinjau diff-nya sendiri; jangan hanya mengandalkan test hijau.

## Catatan keputusan bisnis (ringkas)

- Komisi 10% dari harga kotor produk sebelum PPN (tidak dikurangi diskon); hanya penjualan awal (`sale_type = new`).
- Renewal dan upgrade/downgrade tidak berkomisi.
- Masa tunggu 14 hari, minimum payout Rp 100.000.
- Refund setelah komisi dibayar: komisi tetap dibayarkan (default sementara).
- Diskon untuk pengguna kode referral belum ada (rencana setelah sistem stabil).
- Admin boleh mengaktifkan kembali affiliator suspended/rejected. Suspend mempertahankan tanggal persetujuan sebelumnya; reaktivasi mencatat waktu persetujuan baru.
