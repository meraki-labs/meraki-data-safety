<?php

namespace Meraki\Packages\DataSafety\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Meraki\Packages\DataSafety\Services\RestoreService;
use Meraki\Packages\DataSafety\Tests\TestCase;

class RestoreServiceTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
        $this->tempFile = sys_get_temp_dir() . '/restore_test_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS test_users');
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    private function writeLines(array $rows): void
    {
        $h = fopen($this->tempFile, 'w');
        foreach ($rows as $row) {
            fwrite($h, json_encode($row) . PHP_EOL);
        }
        fclose($h);
    }

    public function test_restore_inserts_rows_from_file(): void
    {
        $this->writeLines([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob',   'email' => 'bob@example.com'],
        ]);

        (new RestoreService('test_users', ['id'], 50))->restoreFromFile($this->tempFile);

        $this->assertSame(2, DB::table('test_users')->count());
        $this->assertSame('Alice', DB::table('test_users')->where('id', 1)->value('name'));
    }

    public function test_restore_upserts_existing_rows(): void
    {
        DB::table('test_users')->insert(['id' => 1, 'name' => 'OldName', 'email' => 'old@example.com']);

        $this->writeLines([
            ['id' => 1, 'name' => 'NewName', 'email' => 'new@example.com'],
        ]);

        (new RestoreService('test_users', ['id'], 50))->restoreFromFile($this->tempFile);

        $this->assertSame(1, DB::table('test_users')->count());
        $this->assertSame('NewName', DB::table('test_users')->where('id', 1)->value('name'));
    }

    public function test_restore_skips_row_missing_key_column(): void
    {
        $this->writeLines([
            ['name' => 'NoKey', 'email' => 'nokey@example.com'],
            ['id' => 2, 'name' => 'Valid', 'email' => 'v@example.com'],
        ]);

        (new RestoreService('test_users', ['id'], 50))->restoreFromFile($this->tempFile);

        $this->assertSame(1, DB::table('test_users')->count());
        $this->assertSame('Valid', DB::table('test_users')->where('id', 2)->value('name'));
    }

    public function test_restore_returns_early_if_file_not_found(): void
    {
        (new RestoreService('test_users', ['id'], 50))->restoreFromFile('/nonexistent/path/file.json');
        $this->assertSame(0, DB::table('test_users')->count());
    }

    public function test_restore_does_not_delete_file_after_restore(): void
    {
        $this->writeLines([['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);

        (new RestoreService('test_users', ['id'], 50))->restoreFromFile($this->tempFile);

        $this->assertFileExists($this->tempFile);
    }

    public function test_restore_processes_all_rows_across_multiple_batches(): void
    {
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = ['id' => $i, 'name' => "User{$i}", 'email' => "u{$i}@example.com"];
        }
        $this->writeLines($rows);

        (new RestoreService('test_users', ['id'], 3))->restoreFromFile($this->tempFile);

        $this->assertSame(10, DB::table('test_users')->count());
    }
}
