<?php

namespace Meraki\Packages\DataSafety\Services;

use Meraki\Packages\DataSafety\Contracts\DataSafetyServiceContract;

class NullDataSafetyService implements DataSafetyServiceContract
{
    public function backupTable(string $table, array $keyColumns, string $version): void {}
    public function backupColumns(string $table, array $columns, array $keyColumns, string $version): void {}
    public function restoreTable(string $table, array $keyColumns, string $version): void {}
    public function restoreColumns(string $table, array $columns, array $keyColumns, string $version): void {}
    public function cleanupTable(string $table, string $version): void {}
    public function cleanupColumns(string $table, array $columns, string $version): void {}
    public function listBackups(): array { return []; }
}
