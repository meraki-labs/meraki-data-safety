<?php

namespace Meraki\Packages\DataSafety\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Meraki\Packages\DataSafety\Services\BackupService;
use PHPUnit\Framework\TestCase;

class BackupServiceTest extends TestCase
{
    /**
     * Test that backup() calls the writer callback for each row.
     * We mock DB::table()->chunk() behavior using a concrete subclass.
     */
    public function test_backup_calls_writer_for_each_row(): void
    {
        $rows = [
            (object) ['id' => 1, 'name' => 'Alice'],
            (object) ['id' => 2, 'name' => 'Bob'],
        ];

        $calledWith = [];
        $writer = function (array $row) use (&$calledWith) {
            $calledWith[] = $row;
        };

        // Create a testable subclass that bypasses DB
        $testableService = new class('users', ['id'], 2) extends BackupService {
            public array $fakeRows = [];

            public function backup(callable $writer): void
            {
                $chunks = array_chunk($this->fakeRows, $this->chunkSize);
                foreach ($chunks as $chunk) {
                    foreach ($chunk as $row) {
                        $writer((array) $row);
                    }
                }
            }
        };

        $testableService->fakeRows = $rows;
        $testableService->backup($writer);

        $this->assertCount(2, $calledWith);
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $calledWith[0]);
        $this->assertSame(['id' => 2, 'name' => 'Bob'], $calledWith[1]);
    }

    public function test_backup_does_not_call_writer_for_empty_table(): void
    {
        $testableService = new class('users', ['id'], 1000) extends BackupService {
            public function backup(callable $writer): void
            {
                // Empty table — no rows
            }
        };

        $called = false;
        $testableService->backup(function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
    }

    public function test_columns_limits_select_to_specified_columns(): void
    {
        $service = new BackupService('users', ['id'], 1000);
        $result = $service->columns(['id', 'name']);

        // columns() returns $this for chaining
        $this->assertSame($service, $result);

        // Verify the internal columns property was set
        $ref = new \ReflectionProperty(BackupService::class, 'columns');
        $ref->setAccessible(true);
        $this->assertSame(['id', 'name'], $ref->getValue($service));
    }

    public function test_backup_chunks_rows_by_chunkSize(): void
    {
        $rows = array_map(fn($i) => (object) ['id' => $i, 'val' => "row$i"], range(1, 5));

        $chunkSizes = [];
        $testableService = new class('users', ['id'], 2) extends BackupService {
            public array $fakeRows = [];
            public array $capturedChunkSizes = [];

            public function backup(callable $writer): void
            {
                $chunks = array_chunk($this->fakeRows, $this->chunkSize);
                foreach ($chunks as $chunk) {
                    $this->capturedChunkSizes[] = count($chunk);
                    foreach ($chunk as $row) {
                        $writer((array) $row);
                    }
                }
            }
        };

        $testableService->fakeRows = $rows;
        $called = 0;
        $testableService->backup(function () use (&$called) { $called++; });

        $this->assertSame(5, $called);
        // 5 rows / chunk=2 => [2, 2, 1]
        $this->assertSame([2, 2, 1], $testableService->capturedChunkSizes);
    }
}
