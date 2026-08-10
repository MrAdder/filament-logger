<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Model;

class ObserverRegistrar
{
    /**
     * Prefix for the container bindings used to remember what has been wired up.
     */
    protected const FLAG = 'filament-logger.observers.';

    public static function register(string $model, object|string $observer): void
    {
        $flag = static::flag($model, $observer);

        if (app()->bound($flag)) {
            return;
        }

        // The marker lives on the container rather than in a static property.
        // Eloquent listeners are held by the event dispatcher, which is rebuilt
        // with the application, so a process-wide flag would wrongly suppress
        // registration for the next application instance — as happens between
        // tests, or anywhere the container is rebuilt.
        app()->instance($flag, true);

        if (is_string($observer)) {
            $model::observe($observer);

            return;
        }

        foreach ((new $model)->getObservableEvents() as $event) {
            if (! method_exists($observer, $event) || ! method_exists($model, $event)) {
                continue;
            }

            $model::$event(function (Model $record) use ($observer, $event): void {
                $observer->{$event}($record);
            });
        }
    }

    public static function hasRegistered(string $model, object|string $observer): bool
    {
        return app()->bound(static::flag($model, $observer));
    }

    protected static function flag(string $model, object|string $observer): string
    {
        return static::FLAG.$model.'|'.(is_string($observer) ? $observer : $observer::class);
    }
}
