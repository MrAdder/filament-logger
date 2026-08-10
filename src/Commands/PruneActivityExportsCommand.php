<?php

namespace MrAdder\FilamentLogger\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
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

        $dryRun = (bool) $this->option('dry-run');

        [$deleted, $bytes] = $this->prune($disk, $directory, $days, $dryRun);

        $this->reportPruned($deleted, $bytes, $days, $dryRun);

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} Files removed, and the bytes they occupied.
     */
    protected function prune(Filesystem $disk, string $directory, int $days, bool $dryRun): array
    {
        $cutoff = now()->subDays($days)->getTimestamp();
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

        return [$deleted, $bytes];
    }

    protected function reportPruned(int $deleted, int $bytes, int $days, bool $dryRun): void
    {
        if ($deleted === 0) {
            $this->components->info("No exports older than {$days} day(s).");

            return;
        }

        $size = number_format($bytes / 1024, 1);

        $this->components->info($dryRun
            ? "Would delete {$deleted} export file(s), freeing {$size} KB."
            : "Deleted {$deleted} export file(s), freeing {$size} KB.");
    }
}
