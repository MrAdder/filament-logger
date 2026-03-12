<?php

namespace MrAdder\FilamentLogger\Loggers;

use Illuminate\Database\Eloquent\Model;

class ResourceLogger extends AbstractModelLogger
{
    public function __construct(
        protected ?string $resource = null,
    ) {}

    protected function getLogName(): string
    {
        return config('filament-logger.resources.log_name');
    }

    protected function getIgnoredAttributes(Model $model): array
    {
        return array_values(array_unique(array_merge(
            config('filament-logger.resources.ignore', []),
            data_get(config('filament-logger.resources.ignore_for_models', []), $model::class, []),
            $this->resource ? data_get(config('filament-logger.resources.ignore_for_resources', []), $this->resource, []) : [],
        )));
    }
}
