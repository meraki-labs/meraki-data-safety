<?php

namespace Meraki\Packages\DataSafety\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Meraki\Packages\DataSafety\Exceptions\RestoreFailedException;
use Meraki\Packages\DataSafety\Services\DataSafetyService;
use Meraki\Packages\DataSafety\Tests\TestCase;

class DataSafetyServiceTest extends TestCase
{
    private DataSafetyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE TABLE test_items (id INTEGER PRIMARY KEY, value TEXT)');
        $this->service = app(DataSafetyService::class);
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS test_items');
        parent::tearDown();
    }

    public function test_backup_table_creates_json_file_at_correct_relative_path(): void
    {
        DB::table('test_items')->insert([
            ['id' => 1, 'value' => 'foo'],
            ['id' => 2, 'value' => 'bar'],
        ]);

        $this->service->backupTable('test_items', ['id'], 'v1');

        $disk = Storage::disk($this->testDisk);
        $this->assertTrue($disk->exists('backups/meraki_data_safer_test_items_v1.json'));

        $lines = array_filter(explode(PHP_EOL, trim($disk->get('backups/meraki_data_safer_test_items_v1.json'))));
        $this->assertCount(2, $lines);
        $this->assertSame(['id' => 1, 'value' => 'foo'], json_decode(array_values($lines)[0], true));
    }

    public function test_backup_columns_only_backs_up_specified_columns_plus_key(): void
    {
        DB::table('test_items')->insert(['id' => 1, 'value' => 'secret']);

        $this->service->backupColumns('test_items', ['value'], ['id'], 'v1');

        $disk = Storage::disk($this->testDisk);
        $file = 'backups/meraki_data_safer_test_items_value_v1.json';
        $this->assertTrue($disk->exists($file));

        $row = json_decode(trim($disk->get($file)), true);
        $this->assertArrayHasKey('value', $row);
        $this->assertArrayHasKey('id', $row);
    }

    public function test_restore_table_repopulates_data_from_backup(): void
    {
        DB::table('test_items')->insert(['id' => 1, 'value' => 'original']);
        $this->service->backupTable('test_items', ['id'], 'v1');

        DB::table('test_items')->delete();
        $this->assertSame(0, DB::table('test_items')->count());

        $this->service->restoreTable('test_items', ['id'], 'v1');

        $this->assertSame(1, DB::table('test_items')->count());
        $this->assertSame('original', DB::table('test_items')->value('value'));
    }

    public function test_restore_table_throws_restore_failed_exception_if_backup_not_found(): void
    {
        $this->expectException(RestoreFailedException::class);
        $this->service->restoreTable('test_items', ['id'], 'nonexistent_version');
    }

    public function test_cleanup_table_deletes_backup_file(): void
    {
        DB::table('test_items')->insert(['id' => 1, 'value' => 'x']);
        $this->service->backupTable('test_items', ['id'], 'v1');

        $disk = Storage::disk($this->testDisk);
        $this->assertTrue($disk->exists('backups/meraki_data_safer_test_items_v1.json'));

        $this->service->cleanupTable('test_items', 'v1');

        $this->assertFalse($disk->exists('backups/meraki_data_safer_test_items_v1.json'));
    }

    public function test_list_backups_returns_metadata_with_required_keys(): void
    {
        DB::table('test_items')->insert(['id' => 1, 'value' => 'x']);
        $this->service->backupTable('test_items', ['id'], 'v1');

        $backups = $this->service->listBackups();

        $this->assertNotEmpty($backups);
        $this->assertArrayHasKey('file',       $backups[0]);
        $this->assertArrayHasKey('size',       $backups[0]);
        $this->assertArrayHasKey('created_at', $backups[0]);
    }

    public function test_list_backups_returns_empty_when_no_backups(): void
    {
        $this->assertSame([], $this->service->listBackups());
    }
}
