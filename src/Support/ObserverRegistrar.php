<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Model;

class ObserverRegistrar
{
    public static function register(string $model, object|string $observer): void
    {
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
}
