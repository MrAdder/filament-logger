<?php

namespace MrAdder\FilamentLogger\Loggers;

class ModelLogger extends AbstractModelLogger
{
    protected function getLogName(): string
    {
        return config('filament-logger.models.log_name');
    }
}
