<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityExporter
{
    /**
     * @param  array<int, string>|null  $columns
     */
    public function toCsv(Builder $query, ?array $columns = null): StreamedResponse
    {
        $columns ??= config('filament-logger.exports.columns', $this->defaultColumns());

        return response()->streamDownload(function () use ($query, $columns): void {
            $handle = fopen('php://output', 'wb');

            if (! is_resource($handle)) {
                return;
            }

            fputcsv($handle, $columns);

            $this->streamRows($query, function (array $row) use ($handle, $columns): void {
                fputcsv($handle, array_map(fn (string $column): mixed => $row[$column] ?? null, $columns));
            });

            fclose($handle);
        }, 'activity-export-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, string>|null  $columns
     */
    public function toJson(Builder $query, ?array $columns = null): StreamedResponse
    {
        $columns ??= config('filament-logger.exports.columns', $this->defaultColumns());

        return response()->streamDownload(function () use ($query, $columns): void {
            $first = true;
            echo '[';

            $this->streamRows($query, function (array $row) use (&$first, $columns): void {
                if (! $first) {
                    echo ',';
                }

                $first = false;

                echo json_encode(
                    array_intersect_key($row, array_flip($columns)),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            });

            echo ']';
        }, 'activity-export-'.now()->format('Ymd-His').'.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    protected function streamRows(Builder $query, callable $callback): void
    {
        $chunkSize = (int) config('filament-logger.exports.chunk_size', 500);

        (clone $query)
            ->with(['causer', 'subject'])
            ->chunk($chunkSize, function ($activities) use ($callback): void {
                foreach ($activities as $activity) {
                    $callback($this->formatRow($activity));
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRow(mixed $activity): array
    {
        $properties = ActivityViewerPrivacy::sanitizeProperties($activity->properties?->toArray() ?? [], $activity);

        return [
            'id' => $activity->getKey(),
            'log_name' => $activity->log_name,
            'event' => $activity->event,
            'description' => $activity->description,
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'causer_type' => $activity->causer_type,
            'causer_id' => $activity->causer_id,
            'causer_name' => $activity->causer?->name,
            'risk' => data_get($properties, 'risk'),
            'tags' => json_encode(data_get($properties, 'tags', []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'properties' => json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => optional($activity->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function defaultColumns(): array
    {
        return [
            'id',
            'log_name',
            'event',
            'description',
            'subject_type',
            'subject_id',
            'causer_type',
            'causer_id',
            'causer_name',
            'risk',
            'tags',
            'properties',
            'created_at',
        ];
    }
}
