<?php

namespace MrAdder\FilamentLogger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Spatie\Activitylog\Contracts\Activity|null log(string $event, ?string $description = null, array $properties = [], ?string $logName = null, \Illuminate\Database\Eloquent\Model|int|string|null $causer = null, ?\Illuminate\Database\Eloquent\Model $subject = null, bool $anonymous = false, ?\DateTimeInterface $createdAt = null, ?string $risk = null, array $tags = [], array $riskReasons = [])
 */
class FilamentLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \MrAdder\FilamentLogger\FilamentLogger::class;
    }
}
