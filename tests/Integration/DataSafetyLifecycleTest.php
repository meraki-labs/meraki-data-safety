<?php

namespace Meraki\Packages\DataSafety\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Meraki\Packages\DataSafety\Contracts\DataSafetyServiceContract;
use Meraki\Packages\DataSafety\Helpers\FileGenerateHelper;
use Meraki\Packages\DataSafety\Services\DataSafetyService;
use Meraki\Packages\DataSafety\Services\NullDataSafetyService;
use Meraki\Packages\DataSafety\Tests\TestCase;
use Meraki\Packages\DataSafety\Traits\MigrationDataSafety;

class DataSafetyLifecycleTest extends TestCase
{
    private string $testTable = 'meraki_lifecycle_test';
    private string $version = 'v_lifecycle_test';
    private string $diskPath = 'meraki/data-safety';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTable();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists($this->testTable);
        // Clean up backup files
        $disk = Storage::disk('testing');
        foreach ($disk->files($this->diskPath) as $file) {
            $disk->delete($file);
        }
        parent::tearDown();
    }

    private function createTestTable(): void
    {
        Schema::create($this->testTable, function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    private function seedTestData(): void
    {
        DB::table($this->testTable)->insert([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Bob',   'email' => 'bob@example.com',   'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // -------------------------------------------------------------------------
    // Full table lifecycle
    // -------------------------------------------------------------------------

    public function test_full_table_lifecycle_backup_drop_recreate_restore(): void
    {
        $this->seedTestData();

        /** @var DataSafetyServiceContract $service */
        $service = $this->app->make(DataSafetyServiceContract::class);
        $this->assertInstanceOf(DataSafetyService::class, $service);

        // Step 1: Backup
        $service->backupTable($this->testTable, ['id'], $this->version);

        // Verify backup file exists
        $disk = Storage::disk('testing');
        $relativePath = $this->diskPath . '/' . FileGenerateHelper::table($this->testTable, $this->version);
        $this->assertTrue($disk->exists($relativePath), 'Backup file should exist after backup');

        // Step 2: Drop table
        Schema::dropIfExists($this->testTable);
        $this->assertFalse(Schema::hasTable($this->testTable));

        // Step 3: Recreate table
        $this->createTestTable();
        $this->assertSame(0, DB::table($this->testTable)->count());

        // Step 4: Restore
        $service->restoreTable($this->testTable, ['id'], $this->version);

        // Step 5: Verify data matches
        $restored = DB::table($this->testTable)->orderBy('id')->get()->toArray();
        $this->assertCount(2, $restored);
        $this->assertSame('Alice', $restored[0]->name);
        $this->assertSame('alice@example.com', $restored[0]->email);
        $this->assertSame('Bob', $restored[1]->name);

        // Step 6: Backup file still exists (no @unlink)
        $this->assertTrue($disk->exists($relativePath), 'Backup file should still exist after restore');
    }

    // -------------------------------------------------------------------------
    // Cleanup after restore
    // -------------------------------------------------------------------------

    public function test_cleanupTable_after_restore_deletes_backup_file(): void
    {
        $this->seedTestData();

        /** @var DataSafetyServiceContract $service */
        $service = $this->app->make(DataSafetyServiceContract::class);

        $service->backupTable($this->testTable, ['id'], $this->version);

        $disk = Storage::disk('testing');
        $relativePath = $this->diskPath . '/' . FileGenerateHelper::table($this->testTable, $this->version);
        $this->assertTrue($disk->exists($relativePath));

        // Restore then cleanup
        $service->restoreTable($this->testTable, ['id'], $this->version);
        $service->cleanupTable($this->testTable, $this->version);

        $this->assertFalse($disk->exists($relativePath), 'Backup file should be deleted after cleanup');
    }

    // -------------------------------------------------------------------------
    // Full columns lifecycle
    // -------------------------------------------------------------------------

    public function test_full_columns_lifecycle_backup_drop_add_restore(): void
    {
        $this->seedTestData();

        /** @var DataSafetyServiceContract $service */
        $service = $this->app->make(DataSafetyServiceContract::class);

        $columns    = ['email'];
        $keyColumns = ['id'];
        $version    = $this->version . '_cols';

        // Step 1: Backup columns
        $service->backupColumns($this->testTable, $columns, $keyColumns, $version);

        $disk = Storage::disk('testing');
        $relativePath = $this->diskPath . '/' . FileGenerateHelper::columns($this->testTable, $columns, $version);
        $this->assertTrue($disk->exists($relativePath));

        // Step 2: Drop column
        Schema::table($this->testTable, function ($t) {
            $t->dropColumn('email');
        });

        // Step 3: Re-add column
        Schema::table($this->testTable, function ($t) {
            $t->string('email')->nullable();
        });

        // Verify email is NULL before restore
        $rows = DB::table($this->testTable)->orderBy('id')->get();
        foreach ($rows as $row) {
            $this->assertNull($row->email);
        }

        // Step 4: Restore columns
        $service->restoreColumns($this->testTable, $columns, $keyColumns, $version);

        // Step 5: Verify emails restored
        $restored = DB::table($this->testTable)->orderBy('id')->get()->toArray();
        $this->assertSame('alice@example.com', $restored[0]->email);
        $this->assertSame('bob@example.com',   $restored[1]->email);

        // Cleanup
        $service->cleanupColumns($this->testTable, $columns, $version);
        $this->assertFalse($disk->exists($relativePath));
    }

    // -------------------------------------------------------------------------
    // NullDataSafetyService
    // -------------------------------------------------------------------------

    public function test_NullDataSafetyService_creates_no_files_and_does_not_throw(): void
    {
        $service = new NullDataSafetyService();

        // None of these should throw or create files
        $service->backupTable($this->testTable, ['id'], 'null_v');
        $service->backupColumns($this->testTable, ['name'], ['id'], 'null_v');
        $service->restoreTable($this->testTable, ['id'], 'null_v');
        $service->restoreColumns($this->testTable, ['name'], ['id'], 'null_v');
        $service->cleanupTable($this->testTable, 'null_v');
        $service->cleanupColumns($this->testTable, ['name'], 'null_v');

        $this->assertSame([], $service->listBackups());

        // No backup files should exist
        $disk = Storage::disk('testing');
        $files = $disk->files($this->diskPath);
        $this->assertEmpty($files);
    }

    // -------------------------------------------------------------------------
    // MigrationDataSafety trait
    // -------------------------------------------------------------------------

    public function test_migration_trait_dataSafety_resolves_correct_service(): void
    {
        $migration = new class {
            use MigrationDataSafety;

            public function getService(): DataSafetyServiceContract
            {
                return $this->dataSafety();
            }
        };

        $resolved = $migration->getService();
        $this->assertInstanceOf(DataSafetyServiceContract::class, $resolved);
    }

    public function test_migration_trait_cleanupTable_delegates_to_service(): void
    {
        $this->seedTestData();

        $service = $this->app->make(DataSafetyServiceContract::class);
        $service->backupTable($this->testTable, ['id'], $this->version);

        $disk = Storage::disk('testing');
        $relativePath = $this->diskPath . '/' . FileGenerateHelper::table($this->testTable, $this->version);
        $this->assertTrue($disk->exists($relativePath));

        // Use trait via anonymous class
        $migration = new class {
            use MigrationDataSafety;
        };

        // Use reflection to call protected method
        $ref = new \ReflectionMethod(get_class($migration), 'cleanupTable');
        $ref->setAccessible(true);
        $ref->invoke($migration, $this->testTable, $this->version);

        $this->assertFalse($disk->exists($relativePath));
    }

    // -------------------------------------------------------------------------
    // listBackups
    // -------------------------------------------------------------------------

    public function test_listBackups_returns_backed_up_files(): void
    {
        $this->seedTestData();

        $service = $this->app->make(DataSafetyServiceContract::class);
        $service->backupTable($this->testTable, ['id'], $this->version);

        $backups = $service->listBackups();

        $this->assertNotEmpty($backups);
        $this->assertArrayHasKey('file', $backups[0]);
        $this->assertArrayHasKey('size', $backups[0]);
        $this->assertArrayHasKey('created_at', $backups[0]);
    }
}
