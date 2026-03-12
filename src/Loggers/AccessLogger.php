<?php

namespace MrAdder\FilamentLogger\Loggers;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use MrAdder\FilamentLogger\Support\LogDataSanitizer;
use Spatie\Activitylog\ActivityLogger;
use Spatie\Activitylog\ActivityLogStatus;

class AccessLogger
{
    /**
     * Log user login
     *
     * @param  Login  $event
     * @return void
     */
    public function handle(Login $event)
    {
        $description = Filament::getUserName($event->user).' logged in';
        $properties = LogDataSanitizer::sanitizeProperties([
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $properties = array_filter($properties, fn (mixed $value): bool => filled($value));

        app(ActivityLogger::class)
            ->useLog(config('filament-logger.access.log_name'))
            ->setLogStatus(app(ActivityLogStatus::class))
            ->withProperties($properties)
            ->event('Login')
            ->log($description);
    }
}
