<?php

namespace Meraki\DataSafety\Services;

use Illuminate\Support\Facades\DB;

class RestoreService
{
    protected string $table;
    protected array $keyColumns;
    protected int $chunkSize = 500;

    public function __construct(string $table, array $keyColumns)
    {
        $this->table = $table;
        $this->keyColumns = $keyColumns;
    }

    /**
     * Restore dữ liệu từ batch
     */
    public function restoreBatch(array $batch): void
    {
        foreach ($batch as $row) {
            $key = [];
            foreach ($this->keyColumns as $col) {
                if (isset($row[$col])) {
                    $key[$col] = $row[$col];
                }
            }
            if (!empty($key)) {
                DB::table($this->table)->updateOrInsert($key, $row);
            }
        }
    }

    /**
     * Restore từ file JSON chunked và xóa file sau khi hoàn tất
     */
    public function restoreFromFile(string $filePath): void
    {
        if (!file_exists($filePath)) return;

        $handle = fopen($filePath, 'r');
        $batch = [];

        while (($line = fgets($handle)) !== false) {
            $row = json_decode($line, true);
            if (!$row) continue;
            $batch[] = $row;
            if (count($batch) >= $this->chunkSize) {
                $this->restoreBatch($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->restoreBatch($batch);
        }

        fclose($handle);

        // Xóa file sau khi restore thành công
        @unlink($filePath);
    }
}
