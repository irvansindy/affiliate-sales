# Product Requirements Document

## Product Overview

**Produk:** Affiliate Management Platform (nama sementara: *AffiliateHub*)
**Deskripsi singkat:** Website untuk mengelola affiliator marketing/sales yang menjual produk kami (website dan mobile apps), mencatat penjualan, menghitung komisi 10%, dan mengelola pembayaran komisi.
**Visi:** Setiap affiliator bisa melihat penjualan dan komisinya secara transparan, dan admin bisa mengelola seluruh program affiliate tanpa spreadsheet manual.

## Problem

- Penjualan dari banyak affiliator sulit dilacak dan diatribusikan ke orang yang tepat.
- Perhitungan komisi manual rawan salah dan memakan waktu.
- Affiliator tidak punya tempat untuk memantau penjualan, status komisi, dan pencairan.
- Admin tidak punya satu tampilan untuk melihat performa program affiliate.

## Goal

1. Semua penjualan teratribusi otomatis ke affiliator melalui kode/link referral.
2. Komisi 10% dihitung otomatis dan dapat diaudit.
3. Affiliator dapat memantau penjualan dan komisi secara mandiri (self-service).
4. Admin dapat mengelola affiliator, penjualan, komisi, dan pembayaran dalam satu sistem.

## Target Users

Hanya ada **dua role**:

| Role | Siapa | Tujuan utama |
|------|-------|--------------|
| **Admin** | Tim internal yang mengelola program affiliate | Mengelola affiliator, memverifikasi penjualan, menyetujui komisi, mencatat pembayaran |
| **Affiliator** | Marketing/sales mitra yang menjual produk | Mendapat link referral, memantau penjualan dan komisi, melihat riwayat pembayaran |

Tidak ada role lain (tidak ada customer login, tidak ada super-admin terpisah, tidak ada sub-affiliator/multi-level).

## Model Bisnis & Aturan Komisi

**Produk yang dijual:** Website dan Mobile Apps.

**Dua skema pembelian:**
1. **Subscription** — pelanggan membayar berkala (bulanan/tahunan).
2. **Beli lepas (one-time purchase)** — pelanggan membayar sekali.

**Aturan komisi:**
- Komisi affiliator = **10% dari total harga** transaksi.
- Rate disimpan sebagai pengaturan (default 10%) dan **disalin (snapshot) ke setiap transaksi** agar perubahan rate di masa depan tidak mengubah komisi lama.
- **Dasar komisi** = harga kotor produk (harga dasar/*list price*), **sebelum PPN**. PPN tidak ikut dihitung dan nilai ini tidak dikurangi diskon. Disimpan sebagai `base_amount` pada transaksi.
- **Subscription:** komisi hanya untuk **penjualan awal** (pembayaran pertama). Perpanjangan/renewal **tidak** menghasilkan komisi.
- **Beli lepas:** komisi 10% dari harga kotor produk pada penjualan tersebut.
- **Upgrade/downgrade paket subscription:** bukan penjualan baru dan **tidak** menghasilkan komisi.
- **Diskon** dicatat terpisah (`discount_amount`) untuk pelaporan; tidak memengaruhi dasar komisi.
- Komisi dibuat dengan status `pending` → `approved` → `paid`; dapat `rejected`/`cancelled` jika transaksi di-refund **sebelum** komisi dibayar atau tidak valid.
- **Refund setelah komisi dibayar (default sementara):** komisi yang sudah `paid` tetap dibayarkan, tanpa pemotongan/clawback. Transaksi ditandai `refunded` dan kejadian dicatat di audit log. Kebijakan ini belum final dan dapat berubah.
- Masa tunggu approval **14 hari** dan minimum payout **Rp 100.000**.

## Core Features (Versi 1)

### Untuk Affiliator
- Registrasi dan login (akun menunggu persetujuan admin).
- Dashboard: total klik, total penjualan, komisi pending/approved/paid.
- Kode referral dan link referral unik (per produk).
- Daftar penjualan yang teratribusi (filter tanggal, produk, tipe pembelian, status).
- Daftar komisi dan statusnya.
- Riwayat pembayaran komisi.
- Profil dan data rekening pembayaran.

### Untuk Admin
- Dashboard ringkasan: penjualan, komisi, top affiliator, tren per periode.
- Manajemen affiliator: approve, reject, suspend, lihat detail.
- Admin boleh mengaktifkan kembali affiliator yang suspended atau rejected. Reaktivasi mencatat waktu persetujuan baru; suspend mempertahankan tanggal persetujuan sebelumnya.
- Manajemen produk (Website, Mobile Apps) dan tipe pembelian.
- Manajemen penjualan: lihat, input manual, tandai refund/batal.
- Manajemen komisi: review, approve, reject.
- Manajemen pembayaran (payout): buat batch pembayaran, tandai `paid`, unggah bukti transfer.
- Pengaturan: rate komisi default, masa tunggu approval, minimum payout.
- Ekspor laporan (CSV/Excel).
- Audit log aktivitas penting.

## User Flows

**Flow 1 — Onboarding affiliator**
Daftar → admin review → disetujui → affiliator dapat kode referral → mulai membagikan link.

**Flow 2 — Penjualan teratribusi**
Pelanggan klik link referral → kode tersimpan (cookie + parameter) → pelanggan membeli → transaksi tercatat dengan `affiliate_id` → komisi 10% dibuat berstatus `pending`.
Transaksi dapat masuk lewat **webhook** dari sistem checkout atau **input manual admin** (admin memilih affiliator/kode referral).

**Flow 3 — Persetujuan komisi**
Setelah masa tunggu (mis. 14 hari, untuk antisipasi refund) → admin/sistem menyetujui → komisi `approved`.

**Flow 4 — Pembayaran komisi**
Admin memilih komisi `approved` per affiliator → membuat payout → transfer manual → admin tandai `paid` + bukti → affiliator melihat riwayat.

**Flow 5 — Subscription**
Pembayaran pertama subscription tercatat sebagai penjualan awal → komisi 10% dibuat. Pembayaran perpanjangan tetap dicatat sebagai transaksi (untuk pelaporan) tetapi **tidak** menghasilkan komisi. Perubahan paket (upgrade/downgrade) juga dicatat tanpa komisi.

## Requirements

### Functional
- Atribusi penjualan berbasis kode referral, dengan aturan *last-click* dan masa berlaku cookie (default 30 hari).
- Perhitungan komisi otomatis dan idempoten (satu transaksi tidak menghasilkan komisi ganda).
- Transaksi dapat dibuat lewat dua jalur, **webhook** dan **input manual admin**; keduanya melewati logika atribusi dan perhitungan komisi yang sama.
- Perubahan status komisi dan payout tercatat di audit log.
- Affiliator hanya dapat melihat data miliknya sendiri.
- Pencegahan self-referral (affiliator tidak boleh mendapat komisi dari pembelian sendiri).

### UX
- Bahasa antarmuka: Bahasa Indonesia (siap multi-bahasa).
- Responsif; affiliator banyak mengakses dari ponsel.
- Setiap tabel punya filter, pencarian, dan pagination.
- Format mata uang Rupiah (Rp 1.250.000), zona waktu Asia/Jakarta.

### Performance
- Halaman dashboard < 2 detik untuk 100.000 transaksi.
- Laporan besar diproses lewat queue.

### Platform
- Web (desktop + mobile browser). Aplikasi mobile native untuk affiliator **belum** dibangun di versi 1, tetapi backend disiapkan agar dapat menyediakan API.

### Security
- Autentikasi aman, rate limiting login, validasi input, otorisasi di sisi server.
- Data rekening pembayaran dilindungi dan tidak tampil penuh di UI.

## Success Metrics

- Persentase penjualan yang berhasil teratribusi otomatis.
- Selisih perhitungan komisi vs audit manual (target: 0).
- Waktu rata-rata dari komisi `approved` hingga `paid`.
- Jumlah affiliator aktif (minimal 1 penjualan per bulan).
- Waktu admin untuk rekap komisi bulanan (target turun signifikan).

## Out of Scope (Versi 1)

- Komisi multi-level/jaringan (MLM) dan sub-affiliator.
- Role selain admin dan affiliator.
- Aplikasi mobile native untuk affiliator.
- Pembayaran komisi otomatis via bank/e-wallet API (v1 transfer manual).
- Checkout/payment gateway untuk pelanggan (sistem ini mencatat transaksi, bukan toko).
- Materi marketing berbasis galeri/asset manager yang kompleks.
- Rate komisi berbeda per affiliator/produk (v1 flat 10%; desain data sudah siap).
- Komisi dari perpanjangan subscription dan dari upgrade/downgrade paket.
- Diskon untuk pelanggan yang memakai kode referral (direncanakan setelah sistem stabil).
- Pemotongan komisi (clawback) atas refund yang terjadi setelah komisi dibayar.

## Keputusan Bisnis

| # | Topik | Keputusan |
|---|-------|-----------|
| 1 | Komisi subscription | Hanya untuk penjualan awal; perpanjangan tidak berkomisi |
| 2 | Sumber transaksi | Webhook dan input manual admin |
| 3 | Dasar komisi | Harga kotor produk, sebelum PPN |
| 4 | Masa tunggu & minimum payout | 14 hari dan Rp 100.000 |
| 5 | Refund setelah komisi dibayar | Komisi tetap dibayarkan (default sementara, belum dibahas) |
| 6 | Diskon kode referral | Belum ada; akan dibuat setelah sistem stabil |
| 7 | Upgrade/downgrade subscription | Bukan penjualan baru, tidak berkomisi |
| 8 | Diskon pada transaksi | Dicatat terpisah untuk pelaporan; dasar komisi tetap harga kotor |

## Masih Terbuka

Tidak ada keputusan produk yang tertunda. Format webhook pada `ARCHITECTURE.md` (*Webhook Contract*) sudah disetujui; tinggal dikomunikasikan ke pihak yang mengelola sistem checkout. Selama checkout belum mengirim webhook, transaksi dicatat lewat input manual admin.
