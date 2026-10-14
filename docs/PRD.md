# Product Requirements Document

## Product Overview

**Produk:** Affiliate Management Platform (nama sementara: *AffiliateHub*)
**Deskripsi singkat:** Website untuk mengelola affiliator marketing/sales yang menjual produk kami (website dan mobile apps), mencatat prospek dan tindak lanjut pelanggan, mencatat penjualan, menghitung komisi 10%, dan mengelola pembayaran komisi.
**Visi:** Setiap affiliator bisa mengelola prospek serta melihat penjualan dan komisinya secara transparan, sementara admin mengawasi proses penjualan dan program affiliate tanpa spreadsheet manual.

## Problem

- Penjualan dari banyak affiliator sulit dilacak dan diatribusikan ke orang yang tepat.
- Perhitungan komisi manual rawan salah dan memakan waktu.
- Affiliator tidak punya tempat untuk memantau penjualan, status komisi, dan pencairan.
- Admin tidak punya satu tampilan untuk melihat performa program affiliate.
- Kontak calon pelanggan, tahap penawaran, dan jadwal tindak lanjut tersebar di luar sistem sehingga perkembangan penjualan sulit dipantau.

## Goal

1. Semua penjualan teratribusi otomatis ke affiliator melalui kode/link referral.
2. Komisi 10% dihitung otomatis dan dapat diaudit.
3. Affiliator dapat memantau penjualan dan komisi secara mandiri (self-service).
4. Admin dapat mengelola affiliator, penjualan, komisi, dan pembayaran dalam satu sistem.
5. Affiliator dapat mencatat kontak, memantau prospek hingga keputusan pelanggan, dan menjadwalkan tindak lanjut; admin dapat mengawasi seluruhnya.

## Target Users

Hanya ada **dua role**:

| Role | Siapa | Tujuan utama |
|------|-------|--------------|
| **Admin** | Tim internal yang mengelola program affiliate | Mengawasi prospek, mengelola affiliator, memverifikasi penjualan, menyetujui komisi, mencatat pembayaran |
| **Affiliator** | Marketing/sales mitra yang menjual produk | Mengelola kontak dan prospek miliknya, menindaklanjuti pelanggan, memantau penjualan dan komisi |

Tidak ada role lain (tidak ada customer login, tidak ada super-admin terpisah, tidak ada sub-affiliator/multi-level).

## Model Bisnis & Aturan Komisi

**Produk yang dijual:** Website dan Mobile Apps.

**Dua skema pembelian:**
1. **Subscription** — pelanggan membayar berkala (bulanan/tahunan).
2. **Beli lepas (one-time purchase)** — pelanggan membayar sekali.

**Aturan komisi:**
- Komisi affiliator = **10% dari `base_amount`** pada penjualan awal.
- Rate disimpan sebagai pengaturan (default 10%) dan **disalin (snapshot) ke setiap komisi** agar perubahan rate di masa depan tidak mengubah komisi lama.
- **Dasar komisi** = harga kotor produk (harga dasar/*list price*), **sebelum PPN**. PPN tidak ikut dihitung dan nilai ini tidak dikurangi diskon. Disimpan sebagai `base_amount` pada transaksi.
- **Subscription:** komisi hanya untuk **penjualan awal** (pembayaran pertama). Perpanjangan/renewal **tidak** menghasilkan komisi.
- **Beli lepas:** komisi 10% dari harga kotor produk pada penjualan tersebut.
- **Upgrade/downgrade paket subscription:** bukan penjualan baru dan **tidak** menghasilkan komisi.
- **Diskon** dicatat terpisah (`discount_amount`) untuk pelaporan; tidak memengaruhi dasar komisi.
- Komisi dibuat dengan status `pending` → `approved` → `paid`; dapat `rejected`/`cancelled` jika transaksi di-refund **sebelum** komisi dibayar atau tidak valid.
- **Refund setelah komisi dibayar (default sementara):** komisi yang sudah `paid` tetap dibayarkan, tanpa pemotongan/clawback. Transaksi ditandai `refunded` dan kejadian dicatat di audit log. Kebijakan ini belum final dan dapat berubah.
- Refund sebagian mengikuti aturan status komisi yang sama dengan refund penuh: komisi `pending`/`approved` dibatalkan, sedangkan komisi `paid` tidak ditarik kembali. Nominal refund dicatat pada transaksi.
- Masa tunggu approval **14 hari** dan minimum payout **Rp 100.000**.

## Core Features (Versi 1)

### Untuk Affiliator
- Registrasi dan login (akun menunggu persetujuan admin).
- Dashboard: total klik, total penjualan, komisi pending/approved/paid.
- Kode referral unik per affiliator; link per produk memakai `/r/{referral_code}?product=KODE_PRODUK`. Admin mengisi URL halaman tujuan tiap produk sebelum link dapat digunakan.
- Daftar penjualan yang teratribusi (filter tanggal, produk, tipe pembelian, status).
- Daftar komisi dan statusnya.
- Riwayat pembayaran komisi.
- Profil dan data rekening pembayaran.
- Kontak calon pelanggan miliknya: nama, email/telepon, dan perusahaan bila ada.
- Prospek miliknya per produk dengan tahapan penjualan dan catatan komunikasi.
- Daftar tindak lanjut terjadwal dan penanda selesai.

### Untuk Admin
- Dashboard ringkasan: jumlah dan nilai transaksi berstatus `paid` (refund tidak dihitung), nilai komisi `approved` dan `paid`, lima affiliator teratas berdasarkan total nilai transaksi `paid`, serta tren penjualan enam bulan kalender terakhir.
- Manajemen affiliator: approve, reject, suspend, lihat detail.
- Admin boleh mengaktifkan kembali affiliator yang suspended atau rejected. Reaktivasi mencatat waktu persetujuan baru; suspend mempertahankan tanggal persetujuan sebelumnya.
- Manajemen produk (Website, Mobile Apps) dan tipe pembelian.
- Manajemen penjualan: lihat, input manual, tandai refund/batal.
- Manajemen komisi: review, approve, reject.
- Manajemen pembayaran (payout): buat batch pembayaran dari komisi `approved`, tandai `paid` dengan bukti transfer, atau tandai `failed` saat transfer gagal.
- Pengaturan: rate komisi default, masa tunggu approval, minimum payout.
- Ekspor detail transaksi dan komisi (CSV/Excel) melalui queue; hasil unduhan hanya dapat diakses admin pemilik ekspor.
- Audit log aktivitas penting.
- Tampilan seluruh kontak, prospek, tahapan penjualan, dan tindak lanjut affiliator.

### CRM sederhana
- Satu kontak dapat memiliki lebih dari satu prospek; setiap kontak dan prospek dimiliki oleh satu affiliator. Kontak yang sama dapat dicatat terpisah oleh affiliator berbeda.
- Tahapan prospek: `new` (baru), `contacted` (dihubungi), `proposal` (penawaran), `negotiation` (negosiasi), `won` (menang), `lost` (kalah). Affiliator memperbarui tahapan berdasarkan perkembangan nyata; admin dapat mengoreksi.
- Catatan aktivitas mencatat jenis interaksi, waktu, dan ringkasan. Tindak lanjut memiliki tanggal/jam jatuh tempo dan dapat ditandai selesai.
- Admin melihat seluruh data CRM. Affiliator hanya dapat melihat dan mengubah kontak, prospek, serta aktivitas miliknya. Kontak pada prospek harus milik affiliator yang sama.
- Status `won` adalah catatan hasil prospek, bukan bukti pembayaran. Hanya transaksi yang dicatat melalui `RecordTransaction` dapat menghasilkan komisi; perubahan tahap CRM tidak membuat atau mengubah transaksi maupun komisi.
- Data CRM diisi melalui panel; webhook transaksi tetap memakai kontrak yang ada. Pencocokan otomatis kontak dengan data pelanggan dari webhook tidak termasuk versi 1.

## User Flows

**Flow 1 — Onboarding affiliator**
Daftar → admin review → disetujui → affiliator dapat kode referral → mulai membagikan link.

**Flow 2 — Penjualan teratribusi**
Pelanggan klik link referral → kode tersimpan (cookie + parameter) → pelanggan membeli → transaksi tercatat dengan `affiliate_id` → komisi 10% dibuat berstatus `pending`.
Transaksi dapat masuk lewat **webhook** dari sistem checkout atau **input manual admin** (admin memilih affiliator/kode referral).

**Flow 3 — Persetujuan komisi**
Setelah masa tunggu (default 14 hari, untuk antisipasi refund) → komisi siap ditinjau tetapi tetap `pending` → admin menyetujui secara manual → komisi `approved`. Admin dapat menolak komisi `pending` sebelum atau sesudah masa tunggu.

**Flow 4 — Pembayaran komisi**
Admin memilih komisi `approved` yang belum terikat payout dan data rekening affiliator harus lengkap → membuat payout dengan snapshot rekening terenkripsi → transfer manual. Komisi tetap `approved` selama payout `processing`. Jika berhasil, admin mengunggah bukti lalu menandai `paid` dan komisi terkait menjadi `paid`. Jika gagal, admin menandai `failed`; komisi yang belum dibatalkan dilepas dalam status `approved` agar bisa dibuatkan payout baru. Payout `paid` tidak dapat diproses ulang. Hasil akhir diberitahukan melalui email.

**Flow 5 — Subscription**
Pembayaran pertama subscription tercatat sebagai penjualan awal → komisi 10% dibuat. Pembayaran perpanjangan tetap dicatat sebagai transaksi (untuk pelaporan) tetapi **tidak** menghasilkan komisi. Perubahan paket (upgrade/downgrade) juga dicatat tanpa komisi.

**Flow 6 — Prospek dan tindak lanjut**
Affiliator menyimpan kontak → membuat prospek untuk produk → mencatat interaksi dan jadwal tindak lanjut → memperbarui tahap penjualan menjadi menang atau kalah. Jika pelanggan membayar, transaksi tetap masuk lewat webhook atau input manual admin sesuai Flow 2.

## Requirements

### Functional
- Atribusi penjualan berbasis kode referral, dengan aturan *last-click* dan masa berlaku cookie (default 30 hari).
- Perhitungan komisi otomatis dan idempoten (satu transaksi tidak menghasilkan komisi ganda).
- Transaksi dapat dibuat lewat dua jalur, **webhook** dan **input manual admin**; keduanya melewati logika atribusi dan perhitungan komisi yang sama.
- Perubahan status komisi dan payout tercatat di audit log.
- Affiliator hanya dapat melihat data miliknya sendiri.
- Pencegahan self-referral (affiliator tidak boleh mendapat komisi dari pembelian sendiri).
- Kontak, prospek, dan aktivitas CRM dibatasi berdasarkan pemilik di Policy dan query server; affiliator tidak dapat mengakses data affiliator lain melalui URL atau permintaan langsung.
- Perubahan tahapan CRM tidak memicu `RecordTransaction` atau `CreateCommissionForTransaction`.

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
- Data kontak pelanggan hanya tersedia untuk admin dan affiliator pemiliknya; tidak ada akun pelanggan atau endpoint CRM publik pada versi 1.

## Success Metrics

- Persentase penjualan yang berhasil teratribusi otomatis.
- Selisih perhitungan komisi vs audit manual (target: 0).
- Waktu rata-rata dari komisi `approved` hingga `paid`.
- Jumlah affiliator aktif (minimal 1 penjualan per bulan).
- Waktu admin untuk rekap komisi bulanan (target turun signifikan).
- Jumlah prospek per tahapan dan tindak lanjut yang melewati jadwal, ditampilkan terpisah dari metrik transaksi berbayar.

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
- Otomatisasi kampanye email/WhatsApp, integrasi inbox, penilaian prospek berbasis AI, tiket dukungan, dan impor kontak otomatis dari webhook.

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
| 9 | Scope CRM | Kontak, prospek bertahap, catatan interaksi, dan tindak lanjut untuk affiliator; admin mengawasi seluruhnya |
| 10 | Hubungan CRM dan komisi | Tahap prospek tidak menghasilkan transaksi atau komisi; sumber transaksi tetap webhook dan input manual admin |
| 11 | Persetujuan komisi | Masa tunggu hanya membuat komisi siap ditinjau; perubahan ke `approved` selalu keputusan manual admin |

## Masih Terbuka

Tidak ada keputusan produk yang tertunda. Format webhook pada `ARCHITECTURE.md` (*Webhook Contract*) sudah disetujui; tinggal dikomunikasikan ke pihak yang mengelola sistem checkout. Selama checkout belum mengirim webhook, transaksi dicatat lewat input manual admin.
