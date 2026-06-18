<?php

namespace Meraki\Packages\DataSafety\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Meraki\Packages\DataSafety\Services\BackupService;
use Meraki\Packages\DataSafety\Tests\TestCase;

class BackupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS test_users');
        parent::tearDown();
    }

    public function test_backup_calls_writer_for_each_row(): void
    {
        DB::table('test_users')->insert([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob',   'email' => 'bob@example.com'],
        ]);

        $rows = [];
        (new BackupService('test_users', ['id'], 10))->backup(function ($row) use (&$rows) {
            $rows[] = $row;
        });

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('Bob',   $rows[1]['name']);
    }

    public function test_backup_does_not_call_writer_for_empty_table(): void
    {
        $called = false;
        (new BackupService('test_users', ['id'], 10))->backup(function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
    }

    public function test_columns_limits_backed_up_columns(): void
    {
        DB::table('test_users')->insert(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']);

        $rows = [];
        (new BackupService('test_users', ['id'], 10))
            ->columns(['id', 'email'])
            ->backup(function ($row) use (&$rows) {
                $rows[] = $row;
            });

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('email', $rows[0]);
        $this->assertArrayNotHasKey('name', $rows[0]);
    }

    public function test_backup_respects_chunk_size_and_returns_all_rows(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            DB::table('test_users')->insert(['id' => $i, 'name' => "User{$i}", 'email' => "u{$i}@example.com"]);
        }

        $rows = [];
        (new BackupService('test_users', ['id'], 3))->backup(function ($row) use (&$rows) {
            $rows[] = $row;
        });

        $this->assertCount(7, $rows);
    }

    public function test_backup_orders_by_first_key_column(): void
    {
        DB::table('test_users')->insert([
            ['id' => 3, 'name' => 'Charlie', 'email' => 'c@example.com'],
            ['id' => 1, 'name' => 'Alice',   'email' => 'a@example.com'],
            ['id' => 2, 'name' => 'Bob',     'email' => 'b@example.com'],
        ]);

        $ids = [];
        (new BackupService('test_users', ['id'], 10))->backup(function ($row) use (&$ids) {
            $ids[] = $row['id'];
        });

        $this->assertSame([1, 2, 3], $ids);
    }
}
