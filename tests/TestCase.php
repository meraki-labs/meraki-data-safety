<?php

namespace Meraki\Packages\DataSafety\Tests;

use Meraki\Packages\DataSafety\DataSafetyServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends OrchestraTestCase
{
    protected string $testDisk = 'meraki-data-safety-test';

    protected function getPackageProviders($app): array
    {
        return [DataSafetyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $testStoragePath = sys_get_temp_dir() . '/meraki-data-safety-tests';

        $app['config']->set('filesystems.disks.' . $this->testDisk, [
            'driver' => 'local',
            'root'   => $testStoragePath,
            'throw'  => false,
        ]);

        $app['config']->set('meraki-data-safety.enabled', true);
        $app['config']->set('meraki-data-safety.disk', $this->testDisk);
        $app['config']->set('meraki-data-safety.storage_path', 'backups');
        $app['config']->set('meraki-data-safety.backup_chunk', 100);
        $app['config']->set('meraki-data-safety.restore_chunk', 50);
    }

    protected function tearDown(): void
    {
        $testPath = sys_get_temp_dir() . '/meraki-data-safety-tests/backups';
        if (is_dir($testPath)) {
            array_map('unlink', glob("{$testPath}/*.json") ?: []);
        }
        parent::tearDown();
    }
}
