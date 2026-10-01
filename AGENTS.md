# Agent Instructions

## Project Context

AffiliateHub adalah website manajemen affiliator untuk penjualan produk (website dan mobile apps) dengan dua skema pembelian: **subscription** dan **beli lepas**. Affiliator mendapat komisi **10%** dari total harga. Hanya ada dua role: **admin** dan **affiliator**.

**Stack:** Laravel 13 (PHP 8.3+), Filament (admin panel dan affiliate panel; versi sesuai composer.json), Livewire (versi bawaan Filament), Tailwind CSS 4, MySQL 8, Redis, Docker.

## Before You Start

Baca file berikut sebelum mengubah kode:

- `docs/PRD.md` — scope dan aturan bisnis
- `docs/DESIGN_SYSTEM.md` — aturan UI
- `docs/ARCHITECTURE.md` — struktur teknis
- `docs/ROADMAP.md` — status pengerjaan dan tugas berikutnya (kerjakan satu tugas per sesi; perbarui status setelah selesai)

Lalu:

- Periksa komponen, Resource, Action, dan Service yang sudah ada sebelum membuat yang baru.
- Jika file-file di atas saling bertentangan atau keputusan penting belum ada, **tanyakan dulu**. Jangan berasumsi pada hal yang menyangkut uang/komisi.

## General Rules

- Gunakan fitur bawaan Laravel/Filament sebelum menambah package baru.
- Versi framework dan paket mengikuti `composer.json`/`composer.lock` (Laravel 13). Cek dokumentasi versi yang terpasang sebelum memakai API; jangan memakai sintaks versi lain.
- Versi framework dan paket mengikuti `composer.json`/`composer.lock` (Laravel 13). Cek dokumentasi versi yang terpasang sebelum memakai API; jangan memakai sintaks versi lain.
- Versi framework dan paket mengikuti `composer.json`/`composer.lock` (Laravel 13). Cek dokumentasi versi yang terpasang sebelum memakai API; jangan memakai sintaks versi lain.
- Jangan menambah dependency baru tanpa alasan jelas dan persetujuan.
- Ikuti struktur folder yang ada; jangan memindahkan folder secara massal.
- Perubahan kecil dan terfokus; satu tugas = satu perubahan yang mudah direview.
- Ubah dokumen di `docs/` bila keputusan produk/arsitektur berubah.
- Tanyakan sebelum perubahan arsitektur besar (mengganti DB, auth, struktur modul).

## Role & Otorisasi

- Hanya ada dua role: `admin` dan `affiliate`. Disimpan sebagai enum `role` di tabel `users`. **Jangan** menambah package role/permission (Spatie dsb.) atau role baru.
- Gunakan **Policy** untuk setiap model; Filament Resource wajib memakai Policy.
- Affiliator hanya boleh mengakses data miliknya. Selalu scope query dengan `affiliate_id` milik user yang login.
- Otorisasi diverifikasi di server, bukan hanya dengan menyembunyikan tombol di UI.
- Panel admin dan panel affiliator terpisah; user dengan role salah tidak boleh masuk panel lain.

## Aturan Uang & Komisi (kritis)

- Simpan semua nilai uang sebagai **integer rupiah** (`unsignedBigInteger`). **Dilarang** memakai `float`/`double`.
- Perhitungan komisi **hanya** dilakukan di `App\Services\CommissionCalculator` (atau Action yang ditunjuk). Jangan menghitung komisi di controller, view, atau Livewire component.
- Dasar komisi = harga kotor produk **sebelum PPN** (`base_amount`). Jangan memakai total yang sudah termasuk PPN.
- Komisi hanya untuk **penjualan awal**: hanya `sale_type = new` yang menghasilkan komisi; `renewal` dan `plan_change` tidak boleh.
- `discount_amount` dicatat terpisah untuk pelaporan dan tidak boleh mengurangi `base_amount`.
- Kontrak webhook ada di `docs/ARCHITECTURE.md` (*Webhook Contract*). Jangan mengubah format payload tanpa persetujuan; verifikasi signature dan idempotensi wajib.
- Transaksi masuk lewat webhook dan input manual admin; keduanya wajib memakai Action `RecordTransaction` yang sama.
- Refund sebelum komisi `paid`: batalkan komisi. Refund setelah `paid`: komisi tetap dibayarkan (tanpa clawback) dan dicatat di audit log. Logika ini hanya di Action `HandleRefund`.
- Masa tunggu approval 14 hari dan minimum payout Rp 100.000 dibaca dari `settings`, bukan hard-coded.
- Rate komisi disalin (snapshot) ke tiap record komisi. Jangan menghitung ulang komisi lama memakai rate baru.
- Pembuatan komisi harus **idempoten**: satu transaksi hanya menghasilkan satu komisi (unique constraint pada `transaction_id`).
- Bungkus perubahan yang menyangkut uang dalam `DB::transaction()`.
- Transisi status komisi/payout hanya lewat Action/Service yang menulis ke audit log. Jangan mengubah kolom `status` langsung.
- Tulis test untuk setiap aturan komisi (subscription vs beli lepas, refund, self-referral, pembulatan).

## Code Guidelines

- `declare(strict_types=1);` pada file PHP baru.
- Ikuti PSR-12; format dengan **Laravel Pint**.
- Controller/Livewire tipis: logika bisnis di `app/Actions` atau `app/Services`.
- Gunakan Form Request atau validasi Filament untuk semua input.
- Gunakan Enum PHP untuk status (`CommissionStatus`, `PayoutStatus`, `PurchaseType`, `AffiliateStatus`).
- Penamaan: model singular PascalCase, tabel plural snake_case, method camelCase.
- Hindari N+1: gunakan eager loading dan index pada kolom filter.
- Tugas berat (ekspor, laporan) lewat queue (Redis).
- Jangan `dd()`, `dump()`, atau `console.log` tertinggal di kode.

## Design Rules

- Ikuti `docs/DESIGN_SYSTEM.md`. Gunakan token warna/spacing, bukan nilai hard-coded.
- Gunakan komponen Filament/Blade yang sudah ada sebelum membuat komponen kustom.
- Semua teks UI dalam Bahasa Indonesia, disimpan di file lang (`lang/id`), bukan hard-coded.
- Format Rupiah dan tanggal memakai helper yang sama di seluruh aplikasi.
- Setiap tampilan data wajib punya state: loading, empty, dan error.

## Security Rules

- Jangan commit `.env`, API key, atau kredensial. Gunakan environment variable.
- Jangan expose data rekening pembayaran penuh; tampilkan termask (mis. `****1234`).
- Endpoint webhook wajib memverifikasi signature dan idempotency key.
- Rate limit login, registrasi, dan endpoint publik (klik referral, webhook).
- Gunakan mass-assignment protection (`$fillable`), jangan `$guarded = []`.
- Jangan menulis raw SQL dengan input user tanpa binding.
- Hindari kebocoran informasi di pesan error produksi.

## Commands

```bash
# Setup
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed

# Development
php artisan serve
npm run dev
php artisan queue:work

# Kualitas kode
./vendor/bin/pint
php artisan test

# Build
npm run build

# Docker (jika dipakai)
docker compose up -d
docker compose exec app php artisan migrate
```

## Boundaries (jangan ubah tanpa persetujuan)

- Migrasi yang sudah dijalankan di production (buat migrasi baru, jangan edit lama).
- Logika `CommissionCalculator` dan state machine komisi/payout.
- Konfigurasi auth, panel provider, dan policy.
- `docker-compose.yml`, konfigurasi server, dan CI/CD.
- Menambah role, mengganti DB, atau mengganti framework.
- Menghapus data (gunakan soft delete untuk transaksi, komisi, dan payout).

## Definition of Done

- Fitur sesuai PRD dan lolos `php artisan test` serta `pint`.
- Otorisasi dan scope data dicek (affiliator tidak bisa melihat data affiliator lain).
- State loading/empty/error tersedia.
- Dokumen di `docs/` diperbarui jika ada keputusan baru.

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
