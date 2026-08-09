<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Throwable;

final class ActivityResourceTableOptions
{
    /**
     * @return array<string, string>
     */
    public static function subjectTypes(): array
    {
        $subjects = [];

        if (config('filament-logger.resources.enabled', true)) {
            $exceptResources = [...config('filament-logger.resources.exclude', []), config('filament-logger.activity_resource')];
            $removedExcludedResources = collect(Filament::getResources())->filter(function ($resource) use ($exceptResources) {
                return ! in_array($resource, $exceptResources);
            });

            foreach ($removedExcludedResources as $resource) {
                $model = $resource::getModel();
                $subjects[$model] = Str::of(class_basename($model))->headline();
            }
        }

        foreach (self::distinctColumnValues('subject_type') as $subjectType) {
            $subjects[$subjectType] ??= Str::of(class_basename($subjectType))->headline();
        }

        return $subjects;
    }

    /**
     * @return array<string, string>
     */
    public static function logNames(): array
    {
        $customs = [];

        foreach (config('filament-logger.custom') ?? [] as $custom) {
            $customs[$custom['log_name']] = $custom['log_name'];
        }

        $customEventLogName = config('filament-logger.custom_events.default_log_name');

        if (filled($customEventLogName)) {
            $customs[$customEventLogName] = $customEventLogName;
        }

        foreach (self::distinctColumnValues('log_name') as $logName) {
            $customs[$logName] ??= $logName;
        }

        return array_merge(
            config('filament-logger.resources.enabled') ? [
                config('filament-logger.resources.log_name') => config('filament-logger.resources.log_name'),
            ] : [],
            config('filament-logger.models.enabled') ? [
                config('filament-logger.models.log_name') => config('filament-logger.models.log_name'),
            ] : [],
            config('filament-logger.access.enabled')
                ? [config('filament-logger.access.log_name') => config('filament-logger.access.log_name')]
                : [],
            config('filament-logger.notifications.enabled') ? [
                config('filament-logger.notifications.log_name') => config('filament-logger.notifications.log_name'),
            ] : [],
            $customs,
        );
    }

    /**
     * @return array<string, string>
     */
    public static function logNameColors(): array
    {
        $customs = [];

        if (filled(config('filament-logger.custom_events.color')) && filled(config('filament-logger.custom_events.default_log_name'))) {
            $customs[config('filament-logger.custom_events.color')] = config('filament-logger.custom_events.default_log_name');
        }

        foreach (config('filament-logger.custom') ?? [] as $custom) {
            if (filled($custom['color'] ?? null)) {
                $customs[$custom['color']] = $custom['log_name'];
            }
        }

        return array_merge(
            (config('filament-logger.resources.enabled') && config('filament-logger.resources.color')) ? [
                config('filament-logger.resources.color') => config('filament-logger.resources.log_name'),
            ] : [],
            (config('filament-logger.models.enabled') && config('filament-logger.models.color')) ? [
                config('filament-logger.models.color') => config('filament-logger.models.log_name'),
            ] : [],
            (config('filament-logger.access.enabled') && config('filament-logger.access.color')) ? [
                config('filament-logger.access.color') => config('filament-logger.access.log_name'),
            ] : [],
            (config('filament-logger.notifications.enabled') && config('filament-logger.notifications.color')) ? [
                config('filament-logger.notifications.color') => config('filament-logger.notifications.log_name'),
            ] : [],
            $customs,
        );
    }

    /**
     * Forget the cached filter options. Call this after a bulk import or a prune
     * if the option lists need to reflect the new state immediately.
     */
    public static function flushCache(): void
    {
        foreach (['subject_type', 'log_name'] as $column) {
            self::cache()->forget(self::cacheKey($column));
        }
    }

    /**
     * `SELECT DISTINCT` over the activity table is a full scan on every render,
     * so the result is cached. The option lists only gain a new entry when a
     * previously unseen log name or subject type is recorded, which makes a
     * short TTL an acceptable trade for the scan.
     *
     * @return array<int, string>
     */
    protected static function distinctColumnValues(string $column): array
    {
        $ttl = (int) config('filament-logger.performance.filter_options_cache_ttl', 300);

        if ($ttl <= 0) {
            return self::queryDistinctColumnValues($column);
        }

        try {
            return self::cache()->remember(
                self::cacheKey($column),
                $ttl,
                fn (): array => self::queryDistinctColumnValues($column),
            );
        } catch (Throwable) {
            // A misconfigured cache store should never take the resource down.
            return self::queryDistinctColumnValues($column);
        }
    }

    /**
     * @return array<int, string>
     */
    protected static function queryDistinctColumnValues(string $column): array
    {
        try {
            return self::getModel()::query()
                ->whereNotNull($column)
                ->distinct()
                ->limit((int) config('filament-logger.performance.filter_options_limit', 200))
                ->pluck($column)
                ->filter(fn (?string $value): bool => filled($value))
                ->values()
                ->all();
        } catch (Throwable) {
            // Ignore before the activity table exists.
            return [];
        }
    }

    protected static function cacheKey(string $column): string
    {
        return 'filament-logger:filter-options:'.$column;
    }

    protected static function cache(): CacheRepository
    {
        $store = config('filament-logger.performance.cache_store');

        return filled($store) ? Cache::store((string) $store) : Cache::store();
    }

    protected static function getModel(): string
    {
        return ActivitylogServiceProvider::determineActivityModel();
    }
}
