<?php

/**
 * @internal
 * Managed by Meraki Core Team
 */

namespace Meraki\Packages\DataSafety\Traits;

use Meraki\Packages\DataSafety\Contracts\DataSafetyServiceContract;

trait MigrationDataSafety
{
    protected function dataSafety(): DataSafetyServiceContract
    {
        return app(DataSafetyServiceContract::class);
    }

    protected function backupTable(string $table, array $keyColumns, string $version): void
    {
        $this->dataSafety()->backupTable($table, $keyColumns, $version);
    }

    protected function backupColumns(string $table, array $columns, array $keyColumns, string $version): void
    {
        $this->dataSafety()->backupColumns($table, $columns, $keyColumns, $version);
    }

    protected function restoreTable(string $table, array $keyColumns, string $version): void
    {
        $this->dataSafety()->restoreTable($table, $keyColumns, $version);
    }

    protected function restoreColumns(string $table, array $columns, array $keyColumns, string $version): void
    {
        $this->dataSafety()->restoreColumns($table, $columns, $keyColumns, $version);
    }

    protected function cleanupTable(string $table, string $version): void
    {
        $this->dataSafety()->cleanupTable($table, $version);
    }

    protected function cleanupColumns(string $table, array $columns, string $version): void
    {
        $this->dataSafety()->cleanupColumns($table, $columns, $version);
    }
}
