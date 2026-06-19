<?php

namespace Meraki\Packages\DataSafety\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Meraki\Packages\DataSafety\Contracts\DataSafetyServiceContract;
use Meraki\Packages\DataSafety\Services\DataSafetyService;
use Meraki\Packages\DataSafety\Services\NullDataSafetyService;
use Meraki\Packages\DataSafety\Traits\MigrationDataSafety;
use Meraki\Packages\DataSafety\Tests\TestCase;

class DataSafetyLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('lifecycle_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('lifecycle_users');
        parent::tearDown();
    }

    public function test_full_table_lifecycle_backup_drop_restore_cleanup(): void
    {
        DB::table('lifecycle_users')->insert([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob',   'email' => 'bob@example.com'],
        ]);

        $service = app(DataSafetyServiceContract::class);
        $this->assertInstanceOf(DataSafetyService::class, $service);

        // 1. Backup
        $service->backupTable('lifecycle_users', ['id'], 'v1');
        $this->assertTrue(
            Storage::disk($this->testDisk)->exists('backups/meraki_data_safer_lifecycle_users_v1.json')
        );

        // 2. Drop and recreate table
        Schema::drop('lifecycle_users');
        Schema::create('lifecycle_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
        });
        $this->assertSame(0, DB::table('lifecycle_users')->count());

        // 3. Restore
        $service->restoreTable('lifecycle_users', ['id'], 'v1');
        $this->assertSame(2, DB::table('lifecycle_users')->count());
        $this->assertSame('Alice', DB::table('lifecycle_users')->where('id', 1)->value('name'));

        // 4. File still exists after restore (no auto-delete)
        $this->assertTrue(
            Storage::disk($this->testDisk)->exists('backups/meraki_data_safer_lifecycle_users_v1.json')
        );

        // 5. Cleanup
        $service->cleanupTable('lifecycle_users', 'v1');
        $this->assertFalse(
            Storage::disk($this->testDisk)->exists('backups/meraki_data_safer_lifecycle_users_v1.json')
        );
    }

    public function test_full_columns_lifecycle_backup_drop_column_restore_cleanup(): void
    {
        DB::table('lifecycle_users')->insert([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        ]);

        $service = app(DataSafetyServiceContract::class);

        // Backup columns
        $service->backupColumns('lifecycle_users', ['email'], ['id'], 'v1');

        // Drop column (SQLite: recreate bảng thủ công nếu cần)
        Schema::table('lifecycle_users', fn ($t) => $t->dropColumn('email'));
        $this->assertFalse(Schema::hasColumn('lifecycle_users', 'email'));

        // Recreate column
        Schema::table('lifecycle_users', fn ($t) => $t->string('email')->nullable());

        // Restore
        $service->restoreColumns('lifecycle_users', ['email'], ['id'], 'v1');
        $this->assertSame('alice@example.com', DB::table('lifecycle_users')->where('id', 1)->value('email'));

        // Cleanup
        $service->cleanupColumns('lifecycle_users', ['email'], 'v1');
        $this->assertFalse(
            Storage::disk($this->testDisk)->exists('backups/meraki_data_safer_lifecycle_users_email_v1.json')
        );
    }

    public function test_null_service_creates_no_files_and_does_not_throw(): void
    {
        $this->app->instance(DataSafetyServiceContract::class, new NullDataSafetyService());

        $service = app(DataSafetyServiceContract::class);
        $this->assertInstanceOf(NullDataSafetyService::class, $service);

        DB::table('lifecycle_users')->insert(['id' => 1, 'name' => 'Test', 'email' => null]);

        $service->backupTable('lifecycle_users', ['id'], 'v1');
        $service->restoreTable('lifecycle_users', ['id'], 'v1');
        $service->cleanupTable('lifecycle_users', 'v1');

        $this->assertEmpty(Storage::disk($this->testDisk)->allFiles('backups'));
    }

    public function test_migration_trait_resolves_service_and_runs_without_errors(): void
    {
        DB::table('lifecycle_users')->insert(['id' => 1, 'name' => 'TraitTest', 'email' => null]);

        $migration = new class {
            use MigrationDataSafety;

            public function run(): void
            {
                $this->backupTable('lifecycle_users', ['id'], 'v_trait');
                $this->restoreTable('lifecycle_users', ['id'], 'v_trait');
                $this->cleanupTable('lifecycle_users', 'v_trait');
            }
        };

        $migration->run();
        $this->assertTrue(true);
    }

    public function test_service_provider_binds_real_service_when_enabled(): void
    {
        $this->app['config']->set('meraki-data-safety.enabled', true);
        $this->app->forgetInstance(DataSafetyServiceContract::class);

        (new \Meraki\Packages\DataSafety\DataSafetyServiceProvider($this->app))->register();

        $this->assertInstanceOf(DataSafetyService::class, app(DataSafetyServiceContract::class));
    }

    public function test_service_provider_binds_null_service_when_disabled(): void
    {
        $this->app['config']->set('meraki-data-safety.enabled', false);
        $this->app->forgetInstance(DataSafetyServiceContract::class);

        (new \Meraki\Packages\DataSafety\DataSafetyServiceProvider($this->app))->register();

        $this->assertInstanceOf(NullDataSafetyService::class, app(DataSafetyServiceContract::class));
    }
}
