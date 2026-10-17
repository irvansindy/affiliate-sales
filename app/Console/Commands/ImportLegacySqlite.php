<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SqliteDataImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('app:import-legacy-sqlite {--source= : Berkas SQLite lama} {--yes : Konfirmasi salin data ke database kosong}')]
#[Description('Salin data aplikasi dari SQLite lama ke MySQL kosong setelah migrasi')]
class ImportLegacySqlite extends Command
{
    private const TABLES = [
        'users', 'affiliates', 'products', 'settings', 'referral_clicks',
        'webhook_events', 'transactions', 'commissions', 'payouts',
        'sales_submissions', 'audit_logs', 'exports', 'notifications',
        'crm_contacts', 'crm_leads', 'crm_activities',
    ];

    public function handle(SqliteDataImporter $importer): int
    {
        if (DB::getDefaultConnection() !== 'mysql') {
            $this->error('DB_CONNECTION harus mysql. Database sumber SQLite tidak akan diubah.');

            return self::FAILURE;
        }

        if (! $this->option('yes')) {
            $this->error('Tambahkan --yes setelah memeriksa database MySQL tujuan dan mencadangkan SQLite.');

            return self::FAILURE;
        }

        $sourcePath = realpath((string) ($this->option('source') ?: database_path('database.sqlite')));
        if ($sourcePath === false || ! is_file($sourcePath)) {
            $this->error('Berkas SQLite sumber tidak ditemukan.');

            return self::FAILURE;
        }

        config(['database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => $sourcePath]);
        DB::purge('sqlite');

        try {
            $counts = $importer->copy(DB::connection('sqlite'), DB::connection('mysql'), self::TABLES);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($counts as $table => $count) {
            $this->line("{$table}: {$count}");
        }

        $this->info('Salin data selesai. Verifikasi data dan berkas storage sebelum beralih ke MySQL.');

        return self::SUCCESS;
    }
}
