# Agent Instructions

## Project Context

AffiliateHub adalah website manajemen affiliator untuk penjualan produk (website dan mobile apps) dengan dua skema pembelian: **subscription** dan **beli lepas**. Affiliator mendapat komisi **10%** dari total harga. Hanya ada dua role: **admin** dan **affiliator**.

**Stack:** Laravel 13 (PHP 8.3+), Filament 5.9, Livewire 4 (versi bawaan Filament), Tailwind CSS 4, MySQL 8, Redis, Docker.

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
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
