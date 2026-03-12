<?php

namespace MrAdder\FilamentLogger\Support;

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

        $properties = LogDataSanitizer::sanitizeProperties($activity->properties ?? []);
        $old = self::normalizeSection($properties['old'] ?? []);
        $attributes = self::normalizeSection($properties['attributes'] ?? []);
        $metadata = self::normalizeSection(collect($properties)->except(['old', 'attributes'])->all());
        $keys = collect(array_keys($old))
            ->merge(array_keys($attributes))
            ->unique()
            ->sort()
            ->values();

        return [
            'rows' => $keys
                ->map(fn (string $key): array => [
                    'field' => $key,
                    'old' => self::formatValue(data_get($old, $key), array_key_exists($key, $old)),
                    'new' => self::formatValue(data_get($attributes, $key), array_key_exists($key, $attributes)),
                ])
                ->all(),
            'metadata' => collect($metadata)
                ->map(fn (mixed $value, string $key): array => [
                    'field' => $key,
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
     * @return array{display: string, summary: string, expandable: bool, empty: bool}
     */
    protected static function formatValue(mixed $value, bool $hasValue): array
    {
        if (! $hasValue) {
            return [
                'display' => '-',
                'summary' => '-',
                'expandable' => false,
                'empty' => true,
            ];
        }

        if ($value === null) {
            return [
                'display' => 'null',
                'summary' => 'null',
                'expandable' => false,
                'empty' => false,
            ];
        }

        $display = self::stringify($value);
        $summary = Str::of(str_replace(["\r\n", "\r", "\n"], ' ', $display))
            ->squish()
            ->limit((int) config('filament-logger.diff.collapse_after', 120))
            ->toString();

        return [
            'display' => $display,
            'summary' => $summary,
            'expandable' => str_contains($display, PHP_EOL) || (Str::length($display) > (int) config('filament-logger.diff.collapse_after', 120)),
            'empty' => false,
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
