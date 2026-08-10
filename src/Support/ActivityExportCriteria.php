<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializable description of the filters applied to an activity export.
 *
 * A queued export cannot carry an Eloquent builder through the queue, so the
 * table's filter state is reduced to plain data here and rebuilt inside the
 * job. Keeping the query construction in one place is also what stops the
 * queued and direct export paths from drifting apart.
 *
 * @implements Arrayable<string, mixed>
 */
final class ActivityExportCriteria implements Arrayable
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly array $filters = [],
    ) {}

    /**
     * Reduce Filament's table filter state to the subset this package defines.
     *
     * @param  array<string, mixed>  $tableFilters
     */
    public static function fromTableFilters(array $tableFilters): self
    {
        $filters = [];

        $map = [
            'search' => ['query', 'search'],
            'log_name' => ['value', 'log_name'],
            'subject_type' => ['value', 'subject_type'],
            'risk' => ['risk', 'risk'],
            'created_at' => ['logged_at', 'logged_at'],
            'properties->old' => ['old', 'old'],
            'properties->attributes' => ['new', 'new'],
        ];

        foreach ($map as $filter => [$field, $target]) {
            $value = data_get($tableFilters, "{$filter}.{$field}");

            if (filled($value)) {
                $filters[$target] = $value;
            }
        }

        $preset = data_get($tableFilters, 'created_at.preset');

        if (filled($preset)) {
            $filters['date_preset'] = $preset;
        }

        return new self($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(array_filter($data, static fn (mixed $value): bool => filled($value)));
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query): Builder
    {
        foreach ($this->filters as $key => $value) {
            match ($key) {
                'search' => self::applySearch($query, (string) $value),
                'log_name' => $query->where('log_name', $value),
                'subject_type' => $query->where('subject_type', $value),
                'risk' => $query->where('properties->risk', $value),
                'logged_at' => $query->whereDate('created_at', $value),
                'old' => $query->where('properties->old', 'like', '%'.$value.'%'),
                'new' => $query->where('properties->attributes', 'like', '%'.$value.'%'),
                'date_preset' => ActivityDatePreset::apply($query, (string) $value),
                'preset' => $this->applyExportPreset($query, (string) $value),
                default => null,
            };
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function applyExportPreset(Builder $query, string $key): void
    {
        $preset = ActivityExportPresetManager::saved()[$key] ?? null;

        if (is_array($preset)) {
            ActivityExportPresetManager::apply($query, $preset);
        }
    }

    /**
     * The broad search used by the activity table filter.
     *
     * Shared so the queued export, the direct export, and the table itself all
     * interpret a search term the same way.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applySearch(Builder $query, string $search): Builder
    {
        if (! filled($search)) {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($search): void {
            $nested->where('description', 'like', "%{$search}%")
                ->orWhere('subject_type', 'like', "%{$search}%")
                ->orWhereHas('causer', function (Builder $causer) use ($search): void {
                    $causer->where('name', 'like', "%{$search}%");
                });

            // A LIKE over the JSON properties column can never use an index, so
            // it is opt-out for large activity tables.
            if (config('filament-logger.search.include_properties', true)) {
                $nested->orWhere('properties', 'like', "%{$search}%");
            }
        });
    }

    public function isEmpty(): bool
    {
        return $this->filters === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->filters;
    }
}
