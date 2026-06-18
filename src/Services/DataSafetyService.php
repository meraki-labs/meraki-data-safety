<?php

namespace Meraki\Packages\DataSafety\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Meraki\Packages\DataSafety\Contracts\DataSafetyServiceContract;
use Meraki\Packages\DataSafety\Exceptions\BackupFailedException;
use Meraki\Packages\DataSafety\Exceptions\RestoreFailedException;
use Meraki\Packages\DataSafety\Helpers\FileGenerateHelper;

class DataSafetyService implements DataSafetyServiceContract
{
    protected Filesystem $disk;

    public function __construct()
    {
        $this->disk = Storage::disk(config('meraki-data-safety.disk', 'local'));
    }

    protected function diskPath(): string
    {
        return config('meraki-data-safety.storage_path', 'meraki/data-safety');
    }

    public function backupTable(string $table, array $keyColumns, string $version): void
    {
        $relativePath = $this->diskPath() . '/' . FileGenerateHelper::table($table, $version);
        $this->disk->put($relativePath, '');
        $absolutePath = $this->disk->path($relativePath);
        $handle = null;
        try {
            $handle = fopen($absolutePath, 'w');
            if ($handle === false) {
                throw new BackupFailedException("Cannot open file for writing: {$absolutePath}");
            }
            $service = new BackupService($table, $keyColumns, config('meraki-data-safety.backup_chunk', 1000));
            $service->backup(function ($row) use ($handle) {
                fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL);
            });
        } catch (\Throwable $e) {
            $this->disk->delete($relativePath);
            if ($e instanceof BackupFailedException) throw $e;
            throw new BackupFailedException("Backup failed for table [{$table}]: " . $e->getMessage(), 0, $e);
        } finally {
            if ($handle !== null && is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    public function backupColumns(string $table, array $columns, array $keyColumns, string $version): void
    {
        $allColumns = array_values(array_unique(array_merge($columns, $keyColumns)));
        $relativePath = $this->diskPath() . '/' . FileGenerateHelper::columns($table, $columns, $version);
        $this->disk->put($relativePath, '');
        $absolutePath = $this->disk->path($relativePath);
        $handle = null;
        try {
            $handle = fopen($absolutePath, 'w');
            if ($handle === false) {
                throw new BackupFailedException("Cannot open file for writing: {$absolutePath}");
            }
            $service = (new BackupService($table, $keyColumns, config('meraki-data-safety.backup_chunk', 1000)))
                ->columns($allColumns);
            $service->backup(function ($row) use ($handle) {
                fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL);
            });
        } catch (\Throwable $e) {
            $this->disk->delete($relativePath);
            if ($e instanceof BackupFailedException) throw $e;
            throw new BackupFailedException("Backup failed for table [{$table}] columns: " . $e->getMessage(), 0, $e);
        } finally {
            if ($handle !== null && is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    public function restoreTable(string $table, array $keyColumns, string $version): void
    {
        $relativePath = $this->diskPath() . '/' . FileGenerateHelper::table($table, $version);
        if (! $this->disk->exists($relativePath)) {
            throw new RestoreFailedException("Backup file not found for table [{$table}] version [{$version}]");
        }
        $absolutePath = $this->disk->path($relativePath);
        try {
            (new RestoreService($table, $keyColumns, config('meraki-data-safety.restore_chunk', 500)))->restoreFromFile($absolutePath);
        } catch (\Throwable $e) {
            if ($e instanceof RestoreFailedException) throw $e;
            throw new RestoreFailedException("Restore failed for table [{$table}]: " . $e->getMessage(), 0, $e);
        }
    }

    public function restoreColumns(string $table, array $columns, array $keyColumns, string $version): void
    {
        $relativePath = $this->diskPath() . '/' . FileGenerateHelper::columns($table, $columns, $version);
        if (! $this->disk->exists($relativePath)) {
            throw new RestoreFailedException("Backup file not found for table [{$table}] columns version [{$version}]");
        }
        $absolutePath = $this->disk->path($relativePath);
        try {
            (new RestoreService($table, $keyColumns, config('meraki-data-safety.restore_chunk', 500)))->restoreFromFile($absolutePath);
        } catch (\Throwable $e) {
            if ($e instanceof RestoreFailedException) throw $e;
            throw new RestoreFailedException("Restore failed for table [{$table}] columns: " . $e->getMessage(), 0, $e);
        }
    }

    public function cleanupTable(string $table, string $version): void
    {
        $relativePath = $this->diskPath() . '/' . FileGenerateHelper::table($table, $version);
        $this->disk->delete($relativePath);
    }

    public function cleanupColumns(string $table, array $columns, string $version): void
    {
        $relativePath = $this->diskPath() . '/' . FileGenerateHelper::columns($table, $columns, $version);
        $this->disk->delete($relativePath);
    }

    public function listBackups(): array
    {
        $files = $this->disk->files($this->diskPath());
        $result = [];
        foreach ($files as $file) {
            $result[] = [
                'file'       => $file,
                'size'       => $this->disk->size($file),
                'created_at' => date('Y-m-d H:i:s', $this->disk->lastModified($file)),
            ];
        }
        return $result;
    }
}
