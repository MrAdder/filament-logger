<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Model;
use WeakMap;

class PreviousAttributesStore
{
    /**
     * Cap on the fallback store. It is keyed by model identity rather than by
     * object, so nothing but an explicit forget() removes an entry — without a
     * bound, a long-running worker or an Octane process that updates models
     * whose changes are never logged would grow it forever.
     */
    public const MAX_TRACKED_MODELS = 1000;

    /** @var WeakMap<Model, array<string, mixed>>|null */
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

        if ($modelKey === null) {
            return;
        }

        // Re-inserting moves the key to the end, so the oldest entry stays first.
        unset(self::$attributesByModelKey[$modelKey]);
        self::$attributesByModelKey[$modelKey] = $attributes;

        while (count(self::$attributesByModelKey) > self::MAX_TRACKED_MODELS) {
            array_shift(self::$attributesByModelKey);
        }
    }

    /**
     * Drop everything currently tracked. Registered as an Octane request/tick
     * listener so state never survives a request in a long-running worker.
     */
    public static function flush(): void
    {
        self::$attributes = null;
        self::$attributesByModelKey = [];
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

    /**
     * @return WeakMap<Model, array<string, mixed>>
     */
    protected static function attributes(): WeakMap
    {
        return self::$attributes ??= new WeakMap;
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

        foreach (self::attributes() as $storedModel => $_) {
            if (self::getModelStoreKey($storedModel) !== $modelKey) {
                continue;
            }

            unset(self::attributes()[$storedModel]);
        }
    }
}
