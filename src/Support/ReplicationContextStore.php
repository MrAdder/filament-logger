<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use WeakMap;

class ReplicationContextStore
{
    protected static ?WeakMap $contexts = null;

    public static function remember(Model $replica, Model $source): void
    {
        self::contexts()[$replica] = [
            'type' => $source->getMorphClass(),
            'id' => $source->getKey(),
            'label' => Str::of(class_basename($source))->headline().' # '.$source->getKey(),
        ];
    }

    /**
     * @return array{type: string, id: mixed, label: string}|null
     */
    public static function get(Model $replica): ?array
    {
        return self::contexts()[$replica] ?? null;
    }

    protected static function contexts(): WeakMap
    {
        return self::$contexts ??= new WeakMap();
    }
}
