<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Support;

use Filament\Facades\Filament;
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
            $exceptResources = [...config('filament-logger.resources.exclude'), config('filament-logger.activity_resource')];
            $removedExcludedResources = collect(Filament::getResources())->filter(function ($resource) use ($exceptResources) {
                return ! in_array($resource, $exceptResources);
            });

            foreach ($removedExcludedResources as $resource) {
                $model = $resource::getModel();
                $subjects[$model] = Str::of(class_basename($model))->headline();
            }
        }

        try {
            self::getModel()::query()
                ->whereNotNull('subject_type')
                ->distinct()
                ->pluck('subject_type')
                ->each(function (?string $subjectType) use (&$subjects): void {
                    if (blank($subjectType)) {
                        return;
                    }

                    $subjects[$subjectType] ??= Str::of(class_basename($subjectType))->headline();
                });
        } catch (Throwable) {
            // Ignore before the activity table exists.
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

        try {
            self::getModel()::query()
                ->whereNotNull('log_name')
                ->distinct()
                ->pluck('log_name')
                ->each(function (?string $logName) use (&$customs): void {
                    if (blank($logName)) {
                        return;
                    }

                    $customs[$logName] ??= $logName;
                });
        } catch (Throwable) {
            // Ignore before the activity table exists.
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

    protected static function getModel(): string
    {
        return ActivitylogServiceProvider::determineActivityModel();
    }
}
