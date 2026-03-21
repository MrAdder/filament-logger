<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityChangesFormatter
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, metadata: array<int, array<string, mixed>>}
     */
    public static function for(?Activity $activity): array
    {
        if (! $activity instanceof Activity) {
            return [
                'rows' => [],
                'metadata' => [],
            ];
        }

        $properties = ActivityViewerPrivacy::sanitizeProperties($activity->properties ?? [], $activity);
        $old = self::flattenSection(self::normalizeSection($properties['old'] ?? []));
        $attributes = self::flattenSection(self::normalizeSection($properties['attributes'] ?? []));
        $metadata = self::flattenSection(self::normalizeSection(collect($properties)->except(['old', 'attributes'])->all()));
        $keys = collect(array_keys($old))
            ->merge(array_keys($attributes))
            ->unique()
            ->sort()
            ->values();

        return [
            'rows' => $keys
                ->map(fn (string $key): array => [
                    'field' => $key,
                    'group' => self::groupFromPath($key),
                    'is_nested' => str_contains($key, '.'),
                    'old' => self::formatValue($old[$key] ?? null, array_key_exists($key, $old)),
                    'new' => self::formatValue($attributes[$key] ?? null, array_key_exists($key, $attributes)),
                ])
                ->all(),
            'metadata' => collect($metadata)
                ->map(fn (mixed $value, string $key): array => [
                    'field' => $key,
                    'group' => self::groupFromPath($key),
                    'is_nested' => str_contains($key, '.'),
                    'value' => self::formatValue($value, true),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function normalizeSection(mixed $section): array
    {
        if ($section instanceof Collection) {
            $section = $section->toArray();
        }

        return is_array($section) ? $section : [];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    protected static function flattenSection(array $section, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($section as $key => $value) {
            self::flattenEntry($flattened, $prefix, (string) $key, $value);
        }

        return $flattened;
    }

    /**
     * @param  array<string, mixed>  $flattened
     */
    protected static function flattenEntry(array &$flattened, string $prefix, string $key, mixed $value): void
    {
        $path = $prefix === '' ? $key : $prefix.'.'.$key;
        $value = self::normalizeFlattenValue($value);

        if (! is_array($value) || $value === [] || self::shouldKeepListAsValue($value)) {
            $flattened[$path] = $value;

            return;
        }

        self::appendNestedValues($flattened, $path, $value);
    }

    protected static function normalizeFlattenValue(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    protected static function shouldKeepListAsValue(array $value): bool
    {
        if (! Arr::isList($value)) {
            return false;
        }

        return ! collect($value)->contains(fn (mixed $item): bool => is_array($item) || $item instanceof Collection);
    }

    /**
     * @param  array<string, mixed>  $flattened
     * @param  array<int|string, mixed>  $value
     */
    protected static function appendNestedValues(array &$flattened, string $path, array $value): void
    {
        $nested = self::flattenSection($value, $path);

        if ($nested === []) {
            $flattened[$path] = $value;

            return;
        }

        foreach ($nested as $nestedPath => $nestedValue) {
            $flattened[$nestedPath] = $nestedValue;
        }
    }

    protected static function groupFromPath(string $path): string
    {
        return Str::before($path, '.');
    }

    /**
     * @return array{display: string, summary: string, expandable: bool, empty: bool, line_count: int, char_count: int}
     */
    protected static function formatValue(mixed $value, bool $hasValue): array
    {
        if (! $hasValue) {
            return [
                'display' => '-',
                'summary' => '-',
                'expandable' => false,
                'empty' => true,
                'line_count' => 1,
                'char_count' => 1,
            ];
        }

        if ($value === null) {
            return [
                'display' => 'null',
                'summary' => 'null',
                'expandable' => false,
                'empty' => false,
                'line_count' => 1,
                'char_count' => 4,
            ];
        }

        $display = self::stringify($value);
        $lineCount = substr_count($display, PHP_EOL) + 1;
        $charCount = mb_strlen($display);
        $collapseAfter = (int) config('filament-logger.diff.collapse_after', 120);
        $summaryPreview = Str::of(str_replace(["\r\n", "\r", "\n"], ' ', $display))
            ->squish()
            ->limit($collapseAfter)
            ->toString();
        $expandable = str_contains($display, PHP_EOL) || ($charCount > $collapseAfter);

        $summary = $summaryPreview;

        if ($expandable) {
            $summary .= ' · '.$lineCount.' lines';
        }

        return [
            'display' => $display,
            'summary' => $summary,
            'expandable' => $expandable,
            'empty' => false,
            'line_count' => $lineCount,
            'char_count' => $charCount,
        ];
    }

    protected static function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            $stringValue = (string) $value;
            $prettyPrinted = $stringValue;

            if (config('filament-logger.diff.pretty_print_json', true)) {
                $decoded = json_decode($stringValue, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $prettyPrinted = self::encodeJson($decoded);
                }
            }

            return $prettyPrinted;
        }

        return self::encodeJson($value);
    }

    protected static function encodeJson(mixed $value): string
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '[unprintable value]';
    }
}
