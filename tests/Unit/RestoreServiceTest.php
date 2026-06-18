<?php

namespace Meraki\Packages\DataSafety\Tests\Unit;

use Meraki\Packages\DataSafety\Services\RestoreService;
use PHPUnit\Framework\TestCase;

class RestoreServiceTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/meraki_restore_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->tmpDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Create a RestoreService subclass that captures updateOrInsert calls
     * instead of hitting a real DB.
     */
    private function makeTestableService(string $table, array $keyColumns, int $chunkSize): object
    {
        return new class($table, $keyColumns, $chunkSize) extends RestoreService {
            public array $upsertCalls = [];

            protected function restoreBatch(array $rows): void
            {
                foreach ($rows as $row) {
                    $key = [];
                    foreach ($this->keyColumns as $column) {
                        if (! array_key_exists($column, $row)) {
                            continue 2;
                        }
                        $key[$column] = $row[$column];
                    }
                    $this->upsertCalls[] = ['key' => $key, 'row' => $row];
                }
            }
        };
    }

    public function test_restoreFromFile_calls_updateOrInsert_for_each_row(): void
    {
        $filePath = $this->tmpDir . '/backup.json';
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
        file_put_contents($filePath, implode(PHP_EOL, array_map('json_encode', $rows)) . PHP_EOL);

        $service = $this->makeTestableService('users', ['id'], 500);
        $service->restoreFromFile($filePath);

        $this->assertCount(2, $service->upsertCalls);
        $this->assertSame(['id' => 1], $service->upsertCalls[0]['key']);
        $this->assertSame(['id' => 2], $service->upsertCalls[1]['key']);
    }

    public function test_restoreFromFile_returns_early_when_file_does_not_exist(): void
    {
        $service = $this->makeTestableService('users', ['id'], 500);
        // No exception should be thrown
        $service->restoreFromFile($this->tmpDir . '/nonexistent.json');
        $this->assertCount(0, $service->upsertCalls);
    }

    public function test_restoreFromFile_skips_rows_missing_key_column(): void
    {
        $filePath = $this->tmpDir . '/partial.json';
        // Row 1 has id, row 2 is missing id
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['name' => 'Bob'],             // missing 'id'
        ];
        file_put_contents($filePath, implode(PHP_EOL, array_map('json_encode', $rows)) . PHP_EOL);

        $service = $this->makeTestableService('users', ['id'], 500);
        $service->restoreFromFile($filePath);

        // Only row 1 should be upserted
        $this->assertCount(1, $service->upsertCalls);
        $this->assertSame(['id' => 1], $service->upsertCalls[0]['key']);
    }

    public function test_restoreFromFile_batches_by_chunkSize(): void
    {
        $filePath = $this->tmpDir . '/chunked.json';
        $rows = array_map(fn($i) => ['id' => $i, 'val' => "row$i"], range(1, 5));
        file_put_contents($filePath, implode(PHP_EOL, array_map('json_encode', $rows)) . PHP_EOL);

        $batchSizes = [];
        $service = new class('users', ['id'], 2) extends RestoreService {
            public array $batchSizes = [];
            protected function restoreBatch(array $rows): void
            {
                $this->batchSizes[] = count($rows);
            }
        };

        $service->restoreFromFile($filePath);

        // 5 rows / chunk=2 => batches of [2, 2, 1]
        $this->assertSame([2, 2, 1], $service->batchSizes);
    }

    public function test_file_not_deleted_after_restore(): void
    {
        $filePath = $this->tmpDir . '/no_unlink.json';
        $rows = [['id' => 1, 'name' => 'Alice']];
        file_put_contents($filePath, json_encode($rows[0]) . PHP_EOL);

        $service = $this->makeTestableService('users', ['id'], 500);
        $service->restoreFromFile($filePath);

        // File must still exist — @unlink was removed
        $this->assertFileExists($filePath);
    }
}
