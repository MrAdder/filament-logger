<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Model;
use WeakMap;

class PreviousAttributesStore
{
    protected static ?WeakMap $attributes = null;

    /**
     * Fallback store for framework versions that dispatch a fresh model
     * instance to later lifecycle events.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $attributesByModelKey = [];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function remember(Model $model, array $attributes): void
    {
        self::attributes()[$model] = $attributes;

        $modelKey = self::getModelStoreKey($model);

        if ($modelKey !== null) {
            self::$attributesByModelKey[$modelKey] = $attributes;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(Model $model): array
    {
        $attributes = self::attributes()[$model] ?? null;

        if (is_array($attributes) && $attributes !== []) {
            return $attributes;
        }

        $modelKey = self::getModelStoreKey($model);

        if ($modelKey === null) {
            return [];
        }

        return self::$attributesByModelKey[$modelKey] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pull(Model $model): array
    {
        $attributes = self::get($model);
        self::forget($model);

        return $attributes;
    }

    public static function forget(Model $model): void
    {
        unset(self::attributes()[$model]);

        $modelKey = self::getModelStoreKey($model);

        if ($modelKey !== null) {
            self::forgetByModelKey($modelKey);
        }
    }

    protected static function attributes(): WeakMap
    {
        return self::$attributes ??= new WeakMap();
    }

    protected static function getModelStoreKey(Model $model): ?string
    {
        $key = $model->getKey();

        if ($key === null) {
            return null;
        }

        return implode(':', [
            $model->getConnectionName() ?? 'default',
            $model::class,
            (string) $key,
        ]);
    }

    protected static function forgetByModelKey(string $modelKey): void
    {
        unset(self::$attributesByModelKey[$modelKey]);

        foreach (self::attributes() as $storedModel => $attributes) {
            if (self::getModelStoreKey($storedModel) !== $modelKey) {
                continue;
            }

            unset(self::attributes()[$storedModel]);
        }
    }
}
