<?php

namespace MrAdder\FilamentLogger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Spatie\Activitylog\Contracts\Activity|null log(string $event, ?string $description = null, array $options = [])
 */
class FilamentLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \MrAdder\FilamentLogger\FilamentLogger::class;
    }
}
