# Panduan Affiliator AffiliateHub

Panduan ini untuk pengguna panel `/affiliate`. Akun baru harus disetujui admin sebelum dapat masuk. Setelah aktif, Anda dapat membagikan link referral, mengelola kontak dan prospek, mengajukan penjualan manual, serta memantau transaksi, komisi, dan payout milik sendiri.

## Mulai menggunakan akun

Daftar melalui halaman registrasi affiliator, lalu tunggu persetujuan admin. Pendaftaran berhasil tidak berarti akun langsung aktif. Setelah admin menyetujui akun, masuk ke panel `/affiliate`.

Buka **Profil dan rekening** untuk memeriksa nama, nomor telepon, bank, nama pemilik rekening, dan nomor rekening. Email ditampilkan tetapi tidak dapat diubah dari halaman ini. Nomor rekening lama ditampilkan termask; kosongkan kolom nomor rekening jika tidak ingin menggantinya. Lengkapi data rekening sebelum komisi Anda dapat dibuatkan payout oleh admin.

## Mendapatkan link referral produk

Buka **Link referral**. Daftar ini menampilkan produk aktif beserta link yang dapat disalin. Klik link pada kolom **Link untuk dibagikan**, lalu kirim ke calon pelanggan. Jika tertulis **Belum siap**, admin belum mengisi URL halaman produk; minta admin menyiapkan produk tersebut. Link hanya berfungsi saat akun affiliator dan produk masih aktif.

Link referral membantu mencatat kunjungan dan identitas referral. **Klik link saja tidak mencatat penjualan atau membuat komisi.** Selama penjualan masih diproses manual, ajukan penjualan berbayar melalui menu **Pengajuan penjualan**. Jika kelak sistem checkout terhubung melalui webhook, transaksi dapat masuk otomatis tanpa pengajuan manual untuk pembayaran yang sama.

## Mencatat calon pelanggan di CRM

Gunakan **Kontak** untuk menyimpan nama calon pelanggan; email, telepon, dan perusahaan dapat diisi bila tersedia. Lalu buka **Prospek** untuk menghubungkan kontak dengan produk dan memilih tahap **Baru**, **Dihubungi**, **Penawaran**, **Negosiasi**, **Menang**, atau **Kalah**. Anda hanya dapat memilih kontak milik sendiri.

Pada halaman prospek, tambahkan **Aktivitas** untuk mencatat interaksi atau tindak lanjut. Isi ringkasan; untuk tindak lanjut, isi jadwalnya. Setelah tindak lanjut dilakukan, gunakan **Tandai selesai**. Prospek yang bertahap **Menang** tetap hanya catatan peluang penjualan: status ini tidak otomatis membuat transaksi atau komisi.

## Mengajukan penjualan manual

Setelah pelanggan benar-benar membayar, buka **Pengajuan penjualan** → **Buat Pengajuan penjualan**. Pilih produk aktif dan tipe pembelian (**Subscription** atau **Beli lepas**), isi nama serta email pelanggan, dan tambahkan referensi pembayaran bila ada. Unggah **bukti pembayaran wajib** berupa PDF, JPG, atau PNG dengan ukuran maksimal 5 MB, lalu kirim pengajuan.

Anda tidak perlu mengisi harga atau nominal komisi. Admin akan memeriksa bukti dan pembayaran sebenarnya, lalu mengisi rincian nominal saat menyetujui pengajuan. Pengajuan awal berstatus **Menunggu verifikasi**. Jika disetujui, transaksi dicatat dan komisi dihitung otomatis berdasarkan rate saat transaksi dibuat. Jika ditolak, lihat alasan penolakan pada detail pengajuan. Jangan membuat pengajuan ganda untuk pembayaran yang sama.

## Memahami Transaksi, Komisi, dan Payout

| Menu | Artinya |
|------|---------|
| **Transaksi** | Riwayat pembayaran yang sudah dicatat melalui persetujuan admin, input manual admin, atau webhook. |
| **Komisi** | Hak komisi dari transaksi penjualan awal yang valid; nominalnya dihitung dari harga dasar sebelum PPN. |
| **Payout** | Riwayat proses transfer komisi yang sudah disetujui ke rekening Anda. |

Komisi baru berstatus **Menunggu**. Setelah masa tunggu selesai, admin masih harus meninjaunya sebelum status menjadi **Disetujui**. Komisi **Dibayar** berarti admin telah mengonfirmasi transfer dalam payout dan mengunggah bukti. Payout **Diproses** belum berarti dana sudah dibayar; jika transfer gagal, payout menjadi **Gagal** dan komisi yang masih layak dapat diproses pada payout baru.

Hanya penjualan awal yang menghasilkan komisi. Perpanjangan subscription dan perubahan paket tidak berkomisi. Jika transaksi di-refund sebelum komisi dibayar, komisi dapat dibatalkan. Periksa detail pada masing-masing menu bila status yang terlihat berbeda dari perkiraan.

## Dasbor dan bantuan awal

**Dasbor** menampilkan total klik, penjualan dibayar, serta komisi menunggu, disetujui, dan dibayar. Angka prospek CRM berbeda dari angka penjualan berbayar: kontak atau prospek baru tidak menambah transaksi. Jika belum ada komisi, periksa apakah pengajuan sudah disetujui admin dan apakah transaksi tersebut merupakan penjualan awal yang valid.

Jika akun tidak dapat masuk, periksa apakah admin sudah mengaktifkannya. Jika link referral belum tersedia, periksa status produk bersama admin. Jika payout belum bisa dibuat, lengkapi data rekening dan pastikan komisi telah berstatus **Disetujui** serta totalnya memenuhi minimum payout yang berlaku.
