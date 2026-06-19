<?php

/**
 * @internal
 * Managed by Meraki Core Team
 */

namespace Meraki\Packages\DataSafety\Services;

use Illuminate\Support\Facades\DB;

class RestoreService
{
    protected string $table;
    protected array $keyColumns;
    protected int $chunkSize;

    /**
     * Constructor
     * @param string $table
     * @param array $keyColumns
     * @param int $chunkSize
     */
    public function __construct(string $table, array $keyColumns, int $chunkSize)
    {
        $this->table = $table;
        $this->keyColumns = $keyColumns;
        $this->chunkSize = $chunkSize;
    }

    /**
     * Action restore from file
     * @param string $filePath
     * @return void
     */
    public function restoreFromFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $handle = fopen($filePath, 'r');
        $batch = [];

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);
            if (!$row) {
                continue;
            }

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
    }

    /**
     * Batch to restoreFromFile
     * @param array $rows
     * @return void
     */
    protected function restoreBatch(array $rows): void
    {
        foreach ($rows as $row) {
            $key = [];

            foreach ($this->keyColumns as $column) {
                if (!array_key_exists($column, $row)) {
                    continue 2;
                }
                $key[$column] = $row[$column];
            }

            DB::table($this->table)->updateOrInsert($key, $row);
        }
    }
}
