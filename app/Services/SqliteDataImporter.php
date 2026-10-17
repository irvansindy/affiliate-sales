<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Connection;
use RuntimeException;

final class SqliteDataImporter
{
    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    public function copy(Connection $source, Connection $target, array $tables): array
    {
        if ($source === $target) {
            throw new RuntimeException('Koneksi sumber dan tujuan harus berbeda.');
        }

        foreach ($tables as $table) {
            if (! $source->getSchemaBuilder()->hasTable($table) || ! $target->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException("Tabel {$table} tidak tersedia pada kedua database.");
            }

            if ($target->table($table)->exists()) {
                throw new RuntimeException("Database tujuan tidak kosong: tabel {$table} sudah berisi data.");
            }
        }

        if ($source->getDriverName() === 'sqlite' && $source->select('PRAGMA foreign_key_check') !== []) {
            throw new RuntimeException('Database SQLite sumber memiliki relasi yang tidak valid.');
        }

        $target->getSchemaBuilder()->disableForeignKeyConstraints();

        try {
            return $target->transaction(function () use ($source, $target, $tables): array {
                $counts = [];

                foreach ($tables as $table) {
                    $batch = [];
                    $counts[$table] = 0;

                    foreach ($source->table($table)->cursor() as $row) {
                        $batch[] = (array) $row;

                        if (count($batch) === 100) {
                            $target->table($table)->insert($batch);
                            $counts[$table] += count($batch);
                            $batch = [];
                        }
                    }

                    if ($batch !== []) {
                        $target->table($table)->insert($batch);
                        $counts[$table] += count($batch);
                    }

                    if ($target->table($table)->count() !== $source->table($table)->count()) {
                        throw new RuntimeException("Jumlah record tabel {$table} tidak cocok setelah disalin.");
                    }
                }

                return $counts;
            });
        } finally {
            $target->getSchemaBuilder()->enableForeignKeyConstraints();
        }
    }
}
