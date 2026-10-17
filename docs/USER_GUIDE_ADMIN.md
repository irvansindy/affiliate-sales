# Panduan Admin AffiliateHub

Panduan ini untuk pengguna dengan role **admin** pada panel `/admin`. Admin mengelola affiliator, produk, pengajuan penjualan, transaksi, komisi, payout, dan data CRM. Menu yang muncul mengikuti hak akses akun.

## Alur kerja harian

1. Tinjau affiliator baru di **Affiliator** dan aktifkan akun yang memenuhi syarat.
2. Pastikan produk aktif memiliki **URL halaman produk** agar link referral dapat dibagikan.
3. Periksa **Pengajuan penjualan** yang menunggu verifikasi; cocokkan bukti pembayaran dengan catatan pembayaran sebenarnya.
4. Pantau **Transaksi** yang perlu ditinjau dan **Komisi** yang masa tunggunya telah selesai.
5. Buat **Payout** dari komisi yang disetujui, lalu catat hasil transfer.

Pengajuan penjualan, transaksi, komisi, dan payout adalah tahap yang berbeda. Pengajuan belum menjadi transaksi sampai admin menyetujuinya. Komisi berasal dari transaksi penjualan awal yang valid. Payout adalah proses membayar komisi yang telah disetujui.

## Affiliator dan link referral

Buka **Affiliator**, pilih akun, lalu gunakan aksi pada halaman detail:

- **Setujui**: mengaktifkan pendaftar berstatus menunggu persetujuan.
- **Tolak**: menolak pendaftar yang masih menunggu persetujuan.
- **Tangguhkan**: menonaktifkan sementara akun aktif.
- **Aktifkan kembali**: mengaktifkan akun yang ditangguhkan atau ditolak.

Setiap perubahan status meminta konfirmasi dan dicatat dalam audit log. Affiliator hanya dapat masuk ke panel `/affiliate` jika statusnya aktif. Pada detail affiliator, admin juga dapat melihat link referral per produk yang siap dibagikan. Link tidak tersedia bila affiliator tidak aktif atau produk belum memiliki URL tujuan.

## Produk

Di **Produk**, pilih **Tambah produk** atau buka produk lalu **Ubah**. Isi kode unik, nama, tipe produk (**Website** atau **Mobile App**), dan status aktif. Isi **URL halaman produk** dengan alamat lengkap `http://` atau `https://` agar link referral produk dapat dibuat. Produk yang belum siap dipromosikan boleh disimpan tanpa URL; link-nya belum dapat disalin. Untuk menghentikan penawaran, nonaktifkan produk, bukan menghapus riwayatnya.

## Verifikasi pengajuan penjualan

Buka **Pengajuan penjualan**, pilih pengajuan berstatus **Menunggu verifikasi**, lalu periksa produk, pelanggan, tipe pembelian, referensi yang diklaim, dan bukti pembayaran. Bukti harus dibandingkan dengan sumber pembayaran yang sebenarnya; unggahan affiliator saja bukan konfirmasi bahwa pembayaran berhasil.

Jika valid, pilih **Setujui dan catat transaksi**. Isi nomor referensi pembayaran final yang unik, harga dasar sebelum PPN, diskon, PPN, total dibayar, dan waktu pembayaran. Untuk subscription, isi juga paket subscription. Pastikan `total dibayar = harga dasar − diskon + PPN`. Setelah disetujui, sistem mencatat transaksi penjualan awal dan menghitung komisi dari harga dasar sebelum PPN menggunakan rate yang berlaku saat itu. Jika tidak valid, pilih **Tolak pengajuan** dan isi alasan penolakan.

Pengajuan yang sudah diproses tidak dapat disetujui ulang. Aksi persetujuan dan penolakan tercatat dalam audit log.

## Mencatat dan meninjau transaksi

Untuk penjualan yang tidak berasal dari pengajuan affiliator, buka **Transaksi** → **Catat transaksi**. Isi referensi unik, produk, kode referral bila ada, tipe pembelian, jenis penjualan, pelanggan, rincian nominal, dan waktu pembayaran. Subscription memerlukan paket serta siklus pembayaran. Pilih **Penjualan awal** hanya untuk pembayaran awal; **Perpanjangan** dan **Perubahan paket** tidak menghasilkan komisi. Beli lepas dicatat sebagai penjualan awal.

Transaksi juga dapat masuk melalui webhook dari sistem checkout jika integrasi pengirim sudah tersedia. Pada daftar transaksi, periksa penanda **Perlu ditinjau**. Kode referral yang tidak dikenal, affiliator nonaktif, atau pembelian oleh affiliator sendiri dapat membuat transaksi tercatat tanpa komisi dan memerlukan pemeriksaan admin. Jangan membuat transaksi baru untuk pembayaran yang sama hanya karena statusnya perlu ditinjau.

## Komisi dan pengaturan rate

Di **Pengaturan komisi**, admin dapat mengubah rate untuk transaksi baru. Nilai default adalah 10%; perubahan rate tidak menghitung ulang komisi yang sudah ada. Masa tunggu approval dan minimum payout dibaca dari pengaturan sistem.

Buka **Komisi** untuk melihat nominal, transaksi asal, status, dan batas masa tunggu. Komisi baru berstatus **Menunggu**. Setelah masa tunggu selesai dan transaksi masih dibayar, buka detail lalu pilih **Setujui**. Admin juga dapat **Tolak** komisi yang masih menunggu. Persetujuan tidak otomatis terjadi hanya karena masa tunggu selesai. Nominal komisi berasal dari harga dasar sebelum PPN; diskon dan PPN tidak menjadi dasar penghitungan.

## Payout

Sebelum membuat payout, pastikan affiliator telah melengkapi nama bank, nama pemilik, dan nomor rekening di profilnya. Buka **Payout** → buat payout, pilih affiliator, lalu pilih komisi berstatus **Disetujui** milik affiliator tersebut yang belum terikat payout. Total harus mencapai minimum payout pada pengaturan. Sistem menyimpan snapshot rekening untuk payout itu.

Payout baru berstatus **Diproses**; komisinya tetap **Disetujui** sampai transfer dikonfirmasi. Setelah transfer berhasil, buka detail payout, pilih **Tandai dibayar**, unggah bukti transfer PDF/JPG/PNG, lalu konfirmasi. Barulah payout dan komisi terkait menjadi **Dibayar**. Jika transfer gagal, pilih **Tandai gagal**; komisi yang masih layak dilepas agar dapat masuk payout baru. Payout yang sudah dibayar tidak dapat diproses ulang. Hasil akhir payout dikirim melalui email jika layanan email telah dikonfigurasi.

Nomor rekening ditampilkan termask dalam antarmuka; gunakan hanya data yang diperlukan untuk transfer dan jangan membagikan bukti payout di luar kanal yang berwenang.

## Kontak, prospek, dan aktivitas CRM

Admin dapat melihat serta mengelola **Kontak** dan **Prospek** dari semua affiliator. Saat membuat prospek, pilih kontak dan produk; kontak harus dimiliki affiliator yang sama dengan prospek. Tahap prospek tersedia sebagai **Baru**, **Dihubungi**, **Penawaran**, **Negosiasi**, **Menang**, dan **Kalah**. Pada prospek, catat **Aktivitas** berupa interaksi atau tindak lanjut. Tindak lanjut memerlukan jadwal dan dapat ditandai selesai.

Tahap **Menang** hanya mencatat kemajuan CRM. Tahap ini tidak membuktikan pembayaran dan tidak membuat transaksi atau komisi. Penjualan tetap harus dicatat melalui pengajuan yang disetujui, input manual admin, atau webhook.

## Dasbor dan ekspor

Dasbor admin merangkum penjualan dibayar, komisi disetujui/dibayar, affiliator teratas, dan tren penjualan. Ekspor transaksi dan komisi dijalankan melalui antrean. Setelah proses selesai, admin pemilik ekspor menerima notifikasi dan dapat mengunduh hasilnya. Jika ekspor belum muncul, pastikan worker antrean aplikasi berjalan.

## Jika menu atau aksi tidak muncul

Periksa role akun dan status data yang dibuka. Beberapa aksi hanya tersedia pada status tertentu: misalnya **Setujui** komisi setelah masa tunggu, atau **Tandai dibayar** hanya pada payout yang masih diproses. Muat ulang halaman sebelum mencoba lagi bila status baru saja diubah oleh admin lain.
