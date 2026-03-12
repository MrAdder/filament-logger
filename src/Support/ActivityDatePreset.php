<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ActivityDatePreset
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return config('filament-logger.activity_filters.date_presets', [
            'today' => 'Today',
            'last_24_hours' => 'Last 24 Hours',
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'this_month' => 'This Month',
        ]);
    }

    /**
     * @return array{start: Carbon, end: Carbon}|null
     */
    public static function resolve(?string $preset, ?Carbon $now = null): ?array
    {
        $now ??= now();

        return match ($preset) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'last_24_hours' => [
                'start' => $now->copy()->subDay(),
                'end' => $now->copy(),
            ],
            'last_7_days' => [
                'start' => $now->copy()->subDays(6)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'last_30_days' => [
                'start' => $now->copy()->subDays(29)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            default => null,
        };
    }

    public static function apply(Builder $query, ?string $preset, string $column = 'created_at'): Builder
    {
        $bounds = static::resolve($preset);

        if ($bounds === null) {
            return $query;
        }

        return $query->whereBetween($column, [$bounds['start'], $bounds['end']]);
    }
}
