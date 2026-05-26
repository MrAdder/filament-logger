<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Builder;
use MrAdder\FilamentLogger\Models\ExportPreset;

class ActivityExportPresetManager
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function saved(): array
    {
        $config = config('filament-logger.exports.presets', []);

        $db = [];
        if (config('filament-logger.exports.db_presets_enabled', false)) {
            foreach (ExportPreset::all() as $preset) {
                $db[$preset->key] = [
                    'label' => $preset->label,
                    'icon' => $preset->icon,
                    'columns' => $preset->columns,
                    'filters' => $preset->filters,
                    'source' => 'db',
                ];
            }
        }

        return array_merge($config, $db);
    }

    public static function options(): array
    {
        return collect(self::saved())->mapWithKeys(fn ($v, $k) => [$k => data_get($v, 'label', (string) $k)])->toArray();
    }

    /**
     * Apply a preset's filters to a query.
     * @param Builder $query
     * @param array<string, mixed> $preset
     * @return Builder
     */
    public static function apply(Builder $query, array $preset): Builder
    {
        if ($filters = data_get($preset, 'filters')) {
            return ActivityFilterPresetManager::apply($query, $filters);
        }

        return $query;
    }
}
