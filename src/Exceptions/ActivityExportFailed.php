<?php

namespace MrAdder\FilamentLogger\Exceptions;

class ActivityExportFailed extends FilamentLoggerException
{
    public static function streamUnavailable(): self
    {
        return new self('Unable to open a temporary stream for the activity export.');
    }
}
