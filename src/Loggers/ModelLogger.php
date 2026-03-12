<?php

namespace MrAdder\FilamentLogger\Loggers;

use Illuminate\Database\Eloquent\Model;

class ModelLogger extends AbstractModelLogger
{
    protected function getLogName(): string
    {
        return config('filament-logger.models.log_name');
    }

    protected function getIgnoredAttributes(Model $model): array
    {
        return array_values(array_unique(array_merge(
            config('filament-logger.models.ignore', []),
            data_get(config('filament-logger.models.ignore_for', []), $model::class, []),
        )));
    }
}
