<?php

namespace MrAdder\FilamentLogger\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\ActivitylogServiceProvider;

class PruneActivitiesCommand extends Command
{
    protected $signature = 'filament-logger:prune
        {--days= : Delete activities older than this many days}
        {--log-name=* : Only prune these log names}
        {--except-log-name=* : Exclude these log names from pruning}
        {--dry-run : Show how many records would be removed without deleting them}';

    protected $description = 'Prune old activity log entries using filament-logger retention rules.';

    public function handle(): int
    {
        $days = $this->option('days');
        $days ??= config('filament-logger.pruning.days');

        if (! is_numeric($days) || ((int) $days < 0)) {
            $this->components->error('A non-negative pruning age is required. Set filament-logger.pruning.days or pass --days.');

            return self::FAILURE;
        }

        $days = (int) $days;
        $logNames = $this->normalizeLogNames(array_merge(
            config('filament-logger.pruning.only', []),
            $this->option('log-name'),
        ));
        $excludedLogNames = $this->normalizeLogNames(array_merge(
            config('filament-logger.pruning.except', []),
            $this->option('except-log-name'),
        ));

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $query = $activityModel::query()
            ->where('created_at', '<', now()->subDays($days));

        if ($logNames !== []) {
            $query->whereIn('log_name', $logNames);
        }

        if ($excludedLogNames !== []) {
            $query->whereNotIn('log_name', $excludedLogNames);
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->components->info('No activity records matched the pruning rules.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info("{$count} activity record(s) would be pruned.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->components->info("Pruned {$deleted} activity record(s).");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $logNames
     * @return array<int, string>
     */
    protected function normalizeLogNames(array $logNames): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $value): ?string => is_string($value) && filled($value) ? $value : null,
                $logNames,
            ),
        )));
    }
}
