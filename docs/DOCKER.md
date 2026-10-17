# Docker untuk server Ubuntu

Di komputer lokal, AffiliateHub **tetap dijalankan manual** dengan `php artisan serve`, `npm run dev`, dan `php artisan queue:work` bila memerlukan antrean. Tidak perlu Docker atau migrasi database lokal. Konfigurasi di sini disiapkan untuk deployment server nanti; **belum dijalankan atau diuji pada VPS**.

Compose menjalankan PHP-FPM, nginx, Redis, queue worker, dan scheduler. **Tidak ada container/image MySQL baru.** MySQL 8 harus tersedia terpisah pada external Docker network yang dapat diakses container aplikasi.

## Persiapan server

1. Sediakan Docker Engine dan Compose, reverse proxy dengan HTTPS, MySQL 8, external Docker network, dan database `affiliatehub` yang khusus untuk aplikasi ini. Buat user MySQL **khusus AffiliateHub** dengan izin hanya untuk database tersebut; jangan gunakan `root` atau user proyek lain.
2. Salin `.env.docker.example` menjadi `.env.docker` di server. Isi `APP_URL`, `APP_KEY`, `WEBHOOK_SECRET`, `DB_HOST`, `DB_PASSWORD`, konfigurasi SMTP, dan `MYSQL_DOCKER_NETWORK`. Gunakan `DB_HOST` berupa nama host/container MySQL yang dapat di-resolve dari external network. Jangan commit atau kirim file ini.
3. Sesuaikan `APP_UID`/`APP_GID` dengan pemilik direktori `storage` host. Simpan `storage` secara persisten; pastikan dapat ditulis. `APP_BIND_IP` default `127.0.0.1`, sehingga port HTTP hanya untuk reverse proxy di host, bukan untuk akses publik langsung.
4. Validasi konfigurasi, build image, lalu jalankan aplikasi dan Redis:

```bash
docker compose --env-file .env.docker -f compose.yaml config --quiet
docker compose --env-file .env.docker -f compose.yaml build
docker compose --env-file .env.docker -f compose.yaml up -d app nginx redis
docker compose --env-file .env.docker -f compose.yaml exec app php artisan migrate --force
```

Untuk database baru yang tidak diimpor dari SQLite, jalankan seeder yang diperlukan sebelum membuka akses. Setelah data dan login diverifikasi, hidupkan worker serta scheduler:

```bash
docker compose --env-file .env.docker -f compose.yaml up -d queue scheduler
docker compose --env-file .env.docker -f compose.yaml ps
```

Konfigurasikan reverse proxy host agar HTTPS diteruskan ke port `APP_HTTP_PORT` pada `127.0.0.1`. Uji domain, TLS, email, webhook, storage, queue, dan backup sebelum aplikasi dibuka untuk umum. Saat rilis berikutnya, jalankan migrasi lalu `php artisan queue:restart` di container app. Detail domain, sertifikat, monitoring, dan jadwal backup ditetapkan saat VPS tersedia.

## Jika data SQLite lokal perlu dipindahkan nanti

Pemindahan data **opsional**; jangan lakukan hanya untuk menyiapkan Docker. Pertahankan instalasi lokal manual sampai migrasi server direncanakan. Saat waktunya tiba:

1. Cadangkan `database/database.sqlite` dan `storage`, lalu hentikan penulisan ke SQLite agar salinan konsisten.
2. Migrasikan skema pada database MySQL **kosong** tanpa seeder. Perintah impor akan menolak tabel bisnis tujuan yang sudah berisi data.
3. Salin berkas SQLite ke lokasi server yang aman, lalu mount **read-only** ke container `app` sebagai `/tmp/affiliatehub-source.sqlite`. Jangan masukkan database sumber ke image atau Git.
4. Jalankan `docker compose --env-file .env.docker -f compose.yaml exec app php artisan app:import-legacy-sqlite --source=/tmp/affiliatehub-source.sqlite --yes`.
5. Salin berkas `storage` privat, verifikasi jumlah record dan login, lalu lepas mount sumber sebelum membuka aplikasi. APP_KEY server harus sama dengan APP_KEY sumber agar rekening terenkripsi tetap dapat dibaca.

Impor mempertahankan ID tabel bisnis, termasuk transaksi, komisi, payout, audit, pengajuan penjualan, dan CRM; session, cache, serta antrean lama tidak disalin. Jangan menjalankan seeder pada hasil impor kecuali diperlukan.

## Backup

Saat container `app` berjalan, `sh docker/backup.sh` membuat dump MySQL konsisten dan arsip `storage` di `backups/` dengan izin privat. Folder ini diabaikan Git. Simpan salinan di lokasi terpisah dari server dan uji restore ke database **baru**; jangan menimpa database aktif hanya untuk menguji pemulihan.
