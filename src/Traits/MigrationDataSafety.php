<?php

namespace Meraki\DataSafety\Traits;

use Meraki\DataSafety\Services\BackupService;
use Meraki\DataSafety\Services\RestoreService;
use Meraki\DataSafety\Helpers;

trait MigrationDataSafety
{
    protected string $storagePath;

    public function initDataSafety(): void
    {
        $this->storagePath = config('migrate.data_safety.storage_path', storage_path('snapshots'));
        Helpers::ensureStoragePath($this->storagePath);
    }

    public function backupTable(string $table, array $keyColumns, string $version): string
    {
        $this->initDataSafety();

        $fileName = Helpers::makeSnapshotFileName($table, $keyColumns, null, $version);
        $filePath = $this->storagePath . '/' . $fileName;

        // Mở file để ghi chunked, overwrite nếu đã tồn tại
        $handle = fopen($filePath, 'w');

        $backupService = (new BackupService($table))->keyColumns($keyColumns);
        $backupService->backup(function ($rows) use ($handle) {
            foreach ($rows as $row) {
                fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n");
            }
        });

        fclose($handle);

        return $fileName;
    }

    public function backupColumn(string $table, array $keyColumns, array $columns, string $version): string
    {
        $this->initDataSafety();

        $fileName = Helpers::makeSnapshotFileName($table, $keyColumns, $columns, $version);
        $filePath = $this->storagePath . '/' . $fileName;

        $handle = fopen($filePath, 'w');

        $backupService = (new BackupService($table))
            ->keyColumns($keyColumns)
            ->columns(array_merge($columns, $keyColumns));

        $backupService->backup(function ($rows) use ($handle) {
            foreach ($rows as $row) {
                fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n");
            }
        });

        fclose($handle);

        return $fileName;
    }

    public function restoreTable(string $table, array $keyColumns, string $version): void
    {
        $this->initDataSafety();

        $filePattern = Helpers::makeSnapshotFileName($table, $keyColumns, null, $version);
        $filePath = $this->storagePath . '/' . $filePattern;

        if (!file_exists($filePath)) return;

        $restore = new RestoreService($table, $keyColumns);
        $restore->restoreFromFile($filePath);
    }

    public function restoreColumn(string $table, array $keyColumns, array $columns, string $version): void
    {
        $this->initDataSafety();

        $filePattern = Helpers::makeSnapshotFileName($table, $keyColumns, $columns, $version);
        $filePath = $this->storagePath . '/' . $filePattern;

        if (!file_exists($filePath)) return;

        $restore = new RestoreService($table, $keyColumns);
        $restore->restoreFromFile($filePath);
    }
}
