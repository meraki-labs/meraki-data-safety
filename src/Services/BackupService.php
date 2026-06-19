<?php

/**
 * @internal
 * Managed by Meraki Core Team
 */

namespace Meraki\Packages\DataSafety\Services;

use Illuminate\Support\Facades\DB;

class BackupService
{
    protected string $table;
    protected array $columns = ['*'];
    protected array $keyColumns = ['id'];
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
     * Set columns for backup
     * @param array $columns
     * @return $this
     */
    public function columns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Action backup
     * @param callable $writer
     * @return void
     */
    public function backup(callable $writer): void
    {
        DB::table($this->table)
            ->select($this->columns)
            ->orderBy($this->keyColumns[0])
            ->chunk($this->chunkSize, function ($rows) use ($writer) {
                foreach ($rows as $row) {
                    $writer((array)$row);
                }
            });
    }
}
