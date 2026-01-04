<?php

namespace Meraki\DataSafety\Services;

use Illuminate\Support\Facades\DB;

class BackupService
{
    protected string $table;
    protected ?array $columns = null;
    protected ?array $keyColumns = null;
    protected int $chunkSize = 1000;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function columns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function keyColumns(array $keyColumns): self
    {
        $this->keyColumns = $keyColumns;
        return $this;
    }

    /**
     * @param callable $callback
     * @return void
     */
    public function backup(callable $callback): void
    {
        $keyColumns = $this->keyColumns ?? ['id'];
        $columns = $this->columns ?? ['*'];

        DB::table($this->table)
            ->select($columns)
            ->orderBy($keyColumns[0]) // cần keyColumn
            ->chunk($this->chunkSize, function ($rows) use ($callback) {
                $callback($rows->toArray());
            });
    }
}
