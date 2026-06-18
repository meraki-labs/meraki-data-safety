<?php

namespace Meraki\Packages\DataSafety\Tests\Unit;

use Meraki\Packages\DataSafety\Contracts\DataSafetyServiceContract;
use Meraki\Packages\DataSafety\Services\NullDataSafetyService;
use PHPUnit\Framework\TestCase;

class NullDataSafetyServiceTest extends TestCase
{
    private NullDataSafetyService $service;

    protected function setUp(): void
    {
        $this->service = new NullDataSafetyService();
    }

    public function test_implements_contract(): void
    {
        $this->assertInstanceOf(DataSafetyServiceContract::class, $this->service);
    }

    public function test_backup_table_does_not_throw(): void
    {
        $this->service->backupTable('users', ['id'], 'v1');
        $this->assertTrue(true);
    }

    public function test_backup_columns_does_not_throw(): void
    {
        $this->service->backupColumns('users', ['email'], ['id'], 'v1');
        $this->assertTrue(true);
    }

    public function test_restore_table_does_not_throw(): void
    {
        $this->service->restoreTable('users', ['id'], 'v1');
        $this->assertTrue(true);
    }

    public function test_restore_columns_does_not_throw(): void
    {
        $this->service->restoreColumns('users', ['email'], ['id'], 'v1');
        $this->assertTrue(true);
    }

    public function test_cleanup_table_does_not_throw(): void
    {
        $this->service->cleanupTable('users', 'v1');
        $this->assertTrue(true);
    }

    public function test_cleanup_columns_does_not_throw(): void
    {
        $this->service->cleanupColumns('users', ['email'], 'v1');
        $this->assertTrue(true);
    }

    public function test_list_backups_returns_empty_array(): void
    {
        $this->assertSame([], $this->service->listBackups());
    }
}
