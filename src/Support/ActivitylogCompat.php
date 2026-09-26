<?php

namespace MrAdder\FilamentLogger\Support;

use Spatie\Activitylog\ActivitylogServiceProvider;

/**
 * The one place that knows how spatie/laravel-activitylog v4 and v5 differ.
 *
 * v5 moved the logger classes into a `Support` namespace, dropped
 * ActivitylogServiceProvider::determineActivityModel(), and moved tracked model
 * changes out of `properties` into an `attribute_changes` column. Everything
 * else in the package asks this class instead of naming a Spatie class that only
 * exists in one major version.
 *
 * The version-specific class names are strings on purpose. A `use` or `::class`
 * reference to a class that does not exist is a static analysis error on the
 * other version.
 */
final class ActivitylogCompat
{
    private const V5_CONFIG = 'Spatie\\Activitylog\\Support\\Config';

    private const V5_LOGGER = 'Spatie\\Activitylog\\Support\\ActivityLogger';

    private const V5_LOG_STATUS = 'Spatie\\Activitylog\\Support\\ActivityLogStatus';

    private const V4_LOGGER = 'Spatie\\Activitylog\\ActivityLogger';

    private const V4_LOG_STATUS = 'Spatie\\Activitylog\\ActivityLogStatus';

    /**
     * Whether tracked model changes live in their own `attribute_changes`
     * column (v5) rather than inside `properties` (v4).
     */
    public static function usesAttributeChanges(): bool
    {
        return class_exists(self::V5_CONFIG);
    }

    /**
     * The configured activity model class.
     *
     * A plain string, as spatie/laravel-activitylog v4's determineActivityModel()
     * returned. Callers differ on whether they treat the model as Spatie's
     * Activity or as any Eloquent model, and Eloquent's generics are invariant,
     * so a narrower type would fit some of them and break the others.
     */
    public static function activityModel(): string
    {
        return self::usesAttributeChanges()
            ? call_user_func([self::V5_CONFIG, 'activityModel'])
            : call_user_func([ActivitylogServiceProvider::class, 'determineActivityModel']);
    }

    /**
     * @return class-string
     */
    public static function loggerClass(): string
    {
        return self::usesAttributeChanges() ? self::V5_LOGGER : self::V4_LOGGER;
    }

    /**
     * @return class-string
     */
    public static function logStatusClass(): string
    {
        return self::usesAttributeChanges() ? self::V5_LOG_STATUS : self::V4_LOG_STATUS;
    }
}
