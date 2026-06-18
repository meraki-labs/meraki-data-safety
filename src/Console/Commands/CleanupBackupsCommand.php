<?php

namespace Meraki\Packages\DataSafety\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupBackupsCommand extends Command
{
    protected $signature = 'meraki:data-safety:cleanup
                            {--older-than=30 : Delete files older than N days}
                            {--dry-run : List files to be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';
    protected $description = 'Clean up old data safety backup files';

    public function handle(): int
    {
        $disk = Storage::disk(config('meraki-data-safety.disk', 'local'));
        $path = config('meraki-data-safety.storage_path', 'meraki/data-safety');
        $olderThan = (int) $this->option('older-than');
        $dryRun = $this->option('dry-run');
        $cutoff = time() - ($olderThan * 86400);

        $files = $disk->files($path);
        $toDelete = array_filter($files, fn($f) => $disk->lastModified($f) < $cutoff);

        if (empty($toDelete)) {
            $this->info('No backup files to clean up.');
            return self::SUCCESS;
        }

        $this->table(['File', 'Size (bytes)', 'Last Modified'], array_map(fn($f) => [
            $f,
            $disk->size($f),
            date('Y-m-d H:i:s', $disk->lastModified($f)),
        ], $toDelete));

        if ($dryRun) {
            $this->info('[dry-run] ' . count($toDelete) . ' file(s) would be deleted.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(count($toDelete) . ' file(s) will be deleted. Continue?')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        foreach ($toDelete as $file) {
            $disk->delete($file);
            $this->line("Deleted: {$file}");
        }

        $this->info('Cleanup complete.');
        return self::SUCCESS;
    }
}
