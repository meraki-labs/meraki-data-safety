<?php

namespace Meraki\Packages\DataSafety\Console\Commands;

use Illuminate\Console\Command;
use Meraki\Packages\DataSafety\Contracts\DataSafetyServiceContract;

class ListBackupsCommand extends Command
{
    protected $signature = 'meraki:data-safety:list
                            {--disk=local : The disk to use}
                            {--path=meraki/data-safety : The path to list}';
    protected $description = 'List all data safety backup files';

    public function handle(DataSafetyServiceContract $service): int
    {
        $backups = $service->listBackups();

        if (empty($backups)) {
            $this->info('No backup files found.');
            return self::SUCCESS;
        }

        $this->table(['File', 'Size (bytes)', 'Created At'], array_map(fn($b) => [
            $b['file'],
            $b['size'],
            $b['created_at'],
        ], $backups));

        return self::SUCCESS;
    }
}
