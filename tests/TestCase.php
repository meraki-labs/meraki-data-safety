<?php

namespace Meraki\Packages\DataSafety\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Meraki\Packages\DataSafety\DataSafetyServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [DataSafetyServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('meraki-data-safety.enabled', true);
        $app['config']->set('meraki-data-safety.disk', 'testing');
        $app['config']->set('filesystems.disks.testing', [
            'driver' => 'local',
            'root'   => storage_path('framework/testing/disks/meraki-data-safety'),
        ]);
    }
}
