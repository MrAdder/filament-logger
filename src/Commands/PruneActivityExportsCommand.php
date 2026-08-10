<?php

namespace MrAdder\FilamentLogger\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneActivityExportsCommand extends Command
{
    protected $signature = 'filament-logger:prune-exports
        {--days= : Delete generated exports older than this many days}
        {--dry-run : Report what would be deleted without removing anything}';

    protected $description = 'Delete generated activity export files past their retention window.';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('filament-logger.exports.queue.retention_days', 7);

        if (! is_numeric($days) || (int) $days < 0) {
            $this->components->error('A non-negative retention age is required. Set exports.queue.retention_days or pass --days.');

            return self::FAILURE;
        }

        $days = (int) $days;
        $disk = Storage::disk((string) config('filament-logger.exports.queue.disk', 'local'));
        $directory = trim((string) config('filament-logger.exports.queue.path', 'filament-logger/exports'), '/');

        if (! $disk->exists($directory)) {
            $this->components->info('No generated exports found.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->getTimestamp();
        $dryRun = (bool) $this->option('dry-run');
        $deleted = 0;
        $bytes = 0;

        foreach ($disk->allFiles($directory) as $file) {
            if ($disk->lastModified($file) >= $cutoff) {
                continue;
            }

            $bytes += $disk->size($file);
            $deleted++;

            if (! $dryRun) {
                $disk->delete($file);
            }
        }

        $size = number_format($bytes / 1024, 1);

        if ($deleted === 0) {
            $this->components->info("No exports older than {$days} day(s).");

            return self::SUCCESS;
        }

        $this->components->info($dryRun
            ? "Would delete {$deleted} export file(s), freeing {$size} KB."
            : "Deleted {$deleted} export file(s), freeing {$size} KB.");

        return self::SUCCESS;
    }
}
