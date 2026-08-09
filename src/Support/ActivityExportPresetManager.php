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

    /**
     * The exportable columns, as select options.
     *
     * @return array<string, string>
     */
    public static function columnOptions(): array
    {
        $columns = config('filament-logger.exports.columns', []);

        return collect(is_array($columns) ? $columns : [])
            ->mapWithKeys(fn (mixed $column): array => [(string) $column => (string) $column])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::saved())
            ->mapWithKeys(fn (mixed $v, string $k): array => [$k => (string) data_get($v, 'label', $k)])
            ->all();
    }

    /**
     * Apply a preset's filters to a query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $preset
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, array $preset): Builder
    {
        if ($filters = data_get($preset, 'filters')) {
            return ActivityFilterPresetManager::apply($query, $filters);
        }

        return $query;
    }
}
