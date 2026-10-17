<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SqliteDataImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SqliteDataImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.import_target' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('import_target');

        foreach (['copy_people', 'copy_notes'] as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('value');
            });
            Schema::connection('import_target')->create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('value');
            });
        }
    }

    public function test_it_copies_rows_and_preserves_ids_without_changing_source(): void
    {
        DB::table('copy_people')->insert(['id' => 19, 'value' => 'Pelanggan lama']);
        DB::table('copy_notes')->insert(['id' => 5, 'value' => 'Catatan lama']);

        $counts = app(SqliteDataImporter::class)->copy(DB::connection(), DB::connection('import_target'), ['copy_people', 'copy_notes']);

        $this->assertSame(['copy_people' => 1, 'copy_notes' => 1], $counts);
        $this->assertDatabaseHas('copy_people', ['id' => 19, 'value' => 'Pelanggan lama'], 'import_target');
        $this->assertDatabaseHas('copy_notes', ['id' => 5, 'value' => 'Catatan lama'], 'import_target');
        $this->assertDatabaseHas('copy_people', ['id' => 19]);
    }

    public function test_it_refuses_any_nonempty_destination_before_copying(): void
    {
        DB::table('copy_people')->insert(['id' => 19, 'value' => 'Sumber']);
        DB::connection('import_target')->table('copy_notes')->insert(['id' => 7, 'value' => 'Sudah ada']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('copy_notes');
        app(SqliteDataImporter::class)->copy(DB::connection(), DB::connection('import_target'), ['copy_people', 'copy_notes']);
    }

    public function test_import_command_refuses_to_run_without_mysql_as_default(): void
    {
        $this->assertSame(1, Artisan::call('app:import-legacy-sqlite', ['--yes' => true]));
    }
}
