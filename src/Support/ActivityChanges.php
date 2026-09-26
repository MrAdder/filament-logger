<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Reads and queries the old and new values recorded against an activity,
 * wherever the installed spatie/laravel-activitylog keeps them.
 *
 * v4 stores them under `properties.old` and `properties.attributes`. v5 stores
 * tracked model changes in an `attribute_changes` column and keeps `properties`
 * for custom data only. Activities logged through the custom event API, rows
 * written before an upgrade, and rows logged by Spatie's own trait can still
 * carry them in either place, so every reader goes through here.
 */
final class ActivityChanges
{
    /**
     * @var array<int, string>
     */
    private const SECTIONS = ['old', 'attributes'];

    /**
     * The activity's properties, shaped the way v4 stored them: `old` and
     * `attributes` sit alongside any custom properties.
     *
     * A section recorded in `attribute_changes` wins over the same section in
     * `properties`; an empty one falls back to it.
     *
     * @return array<string, mixed>
     */
    public static function properties(mixed $activity): array
    {
        $properties = self::toArray(data_get($activity, 'properties'));
        $changes = self::storedChanges($activity);

        foreach (self::SECTIONS as $section) {
            $values = self::toArray($changes[$section] ?? null);

            if ($values !== []) {
                $properties[$section] = $values;
            }
        }

        return $properties;
    }

    /**
     * The attribute names an activity records a change to.
     *
     * @return array<int, string>
     */
    public static function changedKeys(mixed $activity): array
    {
        $properties = self::properties($activity);

        return array_values(array_unique(array_merge(
            array_keys(self::toArray($properties['old'] ?? null)),
            array_keys(self::toArray($properties['attributes'] ?? null)),
        )));
    }

    /**
     * Split the old and new values out of a properties array.
     *
     * Used when writing on v5, so tracked changes land in `attribute_changes`
     * the way Spatie's own logger stores them.
     *
     * @param  array<string, mixed>  $properties
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} The properties without them, then the changes.
     */
    public static function split(array $properties): array
    {
        $changes = [];

        foreach (self::SECTIONS as $section) {
            if (array_key_exists($section, $properties)) {
                $changes[$section] = $properties[$section];
                unset($properties[$section]);
            }
        }

        return [$properties, $changes];
    }

    /**
     * Constrain a query to activities whose old or new values contain a term.
     *
     * $path is the `properties->old` or `properties->attributes` JSON path the
     * filters have always used. It stays the filter's name so saved filter and
     * export presets keep working; only the column it is matched against grows.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function whereJsonPathLike(Builder $query, string $path, string $value): Builder
    {
        $needle = '%'.$value.'%';
        $changesPath = self::changesPath($path);

        if ($changesPath === null) {
            return $query->where($path, 'like', $needle);
        }

        return $query->where(function (Builder $nested) use ($path, $changesPath, $needle): void {
            $nested->where($changesPath, 'like', $needle)
                ->orWhere($path, 'like', $needle);
        });
    }

    /**
     * The `attribute_changes` JSON path matching a `properties->…` path, or null
     * when the installed version has no such column or the path is not one.
     */
    protected static function changesPath(string $path): ?string
    {
        if (! ActivitylogCompat::usesAttributeChanges()) {
            return null;
        }

        foreach (self::SECTIONS as $section) {
            if ($path === "properties->{$section}") {
                return "attribute_changes->{$section}";
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function storedChanges(mixed $activity): array
    {
        // Ask the model for the raw attributes first. Reading a column it does
        // not have throws under Model::preventAccessingMissingAttributes(),
        // which v4 installs and un-migrated v5 tables both hit.
        if ($activity instanceof Model && ! array_key_exists('attribute_changes', $activity->getAttributes())) {
            return [];
        }

        return self::toArray(data_get($activity, 'attribute_changes'));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function toArray(mixed $value): array
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }
}
