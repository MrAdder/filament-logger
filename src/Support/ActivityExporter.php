<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ActivityExporter
{
    /**
     * Metadata keys that are safe to echo back in the response header. Anything
     * else (notably the raw request filters) stays in the file body, where it
     * is not subject to the server's header size limit.
     *
     * @var array<int, string>
     */
    protected const HEADER_METADATA_KEYS = [
        'exported_at',
        'exported_by',
        'exported_by_name',
        'preset',
        'source',
        'columns',
    ];

    /**
     * Most servers reject header blocks larger than 8 KB. Stay well under it.
     */
    protected const MAX_HEADER_BYTES = 4096;

    /**
     * Values starting with one of these characters are interpreted as a formula
     * by Excel, LibreOffice, and Google Sheets. Audit descriptions carry
     * attacker-influenced text (model labels, failed-login identifiers), so
     * every cell is neutralised before it is written.
     *
     * @var array<int, string>
     */
    protected const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>|null  $columns
     * @param  array<string, mixed>|null  $metadata
     */
    public function toCsv(Builder $query, ?array $columns = null, ?array $metadata = null): StreamedResponse
    {
        $columns = $this->resolveColumns($columns);
        $metadata = $this->resolveMetadata($metadata, $columns);
        $embed = $this->shouldEmbedMetadata($metadata);

        $response = response()->streamDownload(function () use ($query, $columns, $metadata, $embed): void {
            $handle = fopen('php://output', 'wb');

            if (! is_resource($handle)) {
                return;
            }

            if ($embed) {
                fwrite($handle, '#METADATA:'.$this->encode($metadata)."\n");
            }

            fputcsv($handle, $columns, ',', '"', '');

            $this->streamRows($query, function (array $row) use ($handle, $columns): void {
                fputcsv($handle, array_map(
                    fn (string $column): mixed => $this->escapeCsvValue($row[$column] ?? null),
                    $columns,
                ), ',', '"', '');
            });

            fclose($handle);
        }, $this->fileName('csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);

        return $this->withMetadataHeader($response, $metadata);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>|null  $columns
     * @param  array<string, mixed>|null  $metadata
     */
    public function toJson(Builder $query, ?array $columns = null, ?array $metadata = null): StreamedResponse
    {
        $columns = $this->resolveColumns($columns);
        $metadata = $this->resolveMetadata($metadata, $columns);
        $embed = $this->shouldEmbedMetadata($metadata);

        $response = response()->streamDownload(function () use ($query, $columns, $metadata, $embed): void {
            // With metadata the payload is an object wrapping the rows; without
            // it the payload stays a bare array for backwards compatibility.
            echo $embed
                ? '{"metadata":'.$this->encode($metadata).',"rows":['
                : '[';

            $first = true;

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

            echo $embed ? ']}' : ']';
        }, $this->fileName('json'), [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);

        return $this->withMetadataHeader($response, $metadata);
    }

    /**
     * @param  array<int, string>|null  $columns
     * @return array<int, string>
     */
    protected function resolveColumns(?array $columns): array
    {
        return $columns ?? config('filament-logger.exports.columns', $this->defaultColumns());
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    protected function resolveMetadata(?array $metadata, array $columns): array
    {
        $metadata ??= [];
        $metadata['exported_at'] ??= now()->toIso8601String();
        $metadata['columns'] ??= $columns;

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function shouldEmbedMetadata(array $metadata): bool
    {
        return (bool) ($metadata['embed'] ?? config('filament-logger.exports.embed_metadata', false));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function withMetadataHeader(StreamedResponse $response, array $metadata): StreamedResponse
    {
        $header = $this->encode(array_intersect_key(
            $metadata,
            array_flip(static::HEADER_METADATA_KEYS),
        ));

        if ($header === null || strlen($header) > static::MAX_HEADER_BYTES) {
            return $response;
        }

        $response->headers->set('X-Activity-Export-Metadata', $header);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function encode(array $value): ?string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Prefix formula-triggering values with a single quote so spreadsheet
     * software treats them as text.
     */
    protected function escapeCsvValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], static::FORMULA_PREFIXES, true)
            ? "'".$value
            : $value;
    }

    protected function fileName(string $extension): string
    {
        return 'activity-export-'.now()->format('Ymd-His').'.'.$extension;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  callable(array<string, mixed>): void  $callback
     */
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
