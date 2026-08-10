<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * The supported extension points for how the activity resource is presented.
 *
 * Register callbacks from a service provider's boot() method. Everything here
 * is optional: with nothing registered the resource behaves exactly as it does
 * out of the box.
 *
 * Label hooks may return null to fall back to the built-in formatting, which
 * makes it easy to customise one model without handling every other case.
 * Schema hooks receive the built-in array and return the array to use, so they
 * can append, replace, reorder, or filter.
 *
 * These affect presentation only. To change the description that is *written*
 * to the log, use FilamentLogger::describeUsing() instead.
 */
final class ActivityDisplay
{
    /** @var (callable(?string, mixed, Model): ?string)|null */
    protected static $subjectLabelUsing = null;

    /** @var (callable(?Model, Model): ?string)|null */
    protected static $causerLabelUsing = null;

    /** @var (callable(array<int, mixed>): array<int, mixed>)|null */
    protected static $tableColumnsUsing = null;

    /** @var (callable(array<int, mixed>): array<int, mixed>)|null */
    protected static $infolistEntriesUsing = null;

    /** @var (callable(array<int, mixed>): array<int, mixed>)|null */
    protected static $filtersUsing = null;

    /** @var (callable(array<string, mixed>): array<string, mixed>)|null */
    protected static $tabsUsing = null;

    /** @var (callable(array<int, mixed>): array<int, mixed>)|null */
    protected static $widgetsUsing = null;

    /**
     * Customise the label shown for an activity's subject.
     *
     * Receives the subject type, the subject id, and the activity record.
     * Return null to use the built-in "Model # 1" formatting.
     *
     * @param  (callable(?string, mixed, Model): ?string)|null  $callback
     */
    public static function subjectLabelUsing(?callable $callback): void
    {
        self::$subjectLabelUsing = $callback;
    }

    /**
     * Customise the label shown for an activity's causer.
     *
     * Receives the causer model (null when the activity was anonymous) and the
     * activity record. Return null to use the causer's `name` attribute.
     *
     * This is also the supported way to support a user model that does not have
     * a `name` column.
     *
     * @param  (callable(?Model, Model): ?string)|null  $callback
     */
    public static function causerLabelUsing(?callable $callback): void
    {
        self::$causerLabelUsing = $callback;
    }

    /**
     * Customise the activity table columns.
     *
     * @param  (callable(array<int, mixed>): array<int, mixed>)|null  $callback
     */
    public static function tableColumnsUsing(?callable $callback): void
    {
        self::$tableColumnsUsing = $callback;
    }

    /**
     * Customise the entries shown on the activity detail page.
     *
     * @param  (callable(array<int, mixed>): array<int, mixed>)|null  $callback
     */
    public static function infolistEntriesUsing(?callable $callback): void
    {
        self::$infolistEntriesUsing = $callback;
    }

    /**
     * Customise the activity table filters.
     *
     * @param  (callable(array<int, mixed>): array<int, mixed>)|null  $callback
     */
    public static function filtersUsing(?callable $callback): void
    {
        self::$filtersUsing = $callback;
    }

    /**
     * Customise the review tabs on the activity list page.
     *
     * Tabs are keyed by their filter preset name.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $callback
     */
    public static function tabsUsing(?callable $callback): void
    {
        self::$tabsUsing = $callback;
    }

    /**
     * Customise the dashboard widgets on the activity list page.
     *
     * @param  (callable(array<int, mixed>): array<int, mixed>)|null  $callback
     */
    public static function widgetsUsing(?callable $callback): void
    {
        self::$widgetsUsing = $callback;
    }

    public static function resolveSubjectLabel(?string $subjectType, mixed $subjectId, Model $activity): ?string
    {
        return self::stringFrom(self::$subjectLabelUsing, [$subjectType, $subjectId, $activity]);
    }

    public static function resolveCauserLabel(?Model $causer, Model $activity): ?string
    {
        return self::stringFrom(self::$causerLabelUsing, [$causer, $activity]);
    }

    /**
     * Whether a causer hook is registered.
     *
     * Resolving the causer relation costs a query per row when it has not been
     * eager loaded, so callers check this before touching it.
     */
    public static function hasCauserLabelHook(): bool
    {
        return self::$causerLabelUsing !== null;
    }

    /**
     * @param  array<int, mixed>  $columns
     * @return array<int, mixed>
     */
    public static function resolveTableColumns(array $columns): array
    {
        return self::arrayFrom(self::$tableColumnsUsing, $columns);
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<int, mixed>
     */
    public static function resolveInfolistEntries(array $entries): array
    {
        return self::arrayFrom(self::$infolistEntriesUsing, $entries);
    }

    /**
     * @param  array<int, mixed>  $filters
     * @return array<int, mixed>
     */
    public static function resolveFilters(array $filters): array
    {
        return self::arrayFrom(self::$filtersUsing, $filters);
    }

    /**
     * @param  array<string, mixed>  $tabs
     * @return array<string, mixed>
     */
    public static function resolveTabs(array $tabs): array
    {
        return self::arrayFrom(self::$tabsUsing, $tabs);
    }

    /**
     * @param  array<int, mixed>  $widgets
     * @return array<int, mixed>
     */
    public static function resolveWidgets(array $widgets): array
    {
        return self::arrayFrom(self::$widgetsUsing, $widgets);
    }

    /**
     * Clear every registered hook. Intended for tests.
     */
    public static function flush(): void
    {
        self::$subjectLabelUsing = null;
        self::$causerLabelUsing = null;
        self::$tableColumnsUsing = null;
        self::$infolistEntriesUsing = null;
        self::$filtersUsing = null;
        self::$tabsUsing = null;
        self::$widgetsUsing = null;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    protected static function stringFrom(?callable $callback, array $arguments): ?string
    {
        if ($callback === null) {
            return null;
        }

        $value = $callback(...$arguments);

        return is_string($value) && filled($value) ? $value : null;
    }

    /**
     * A hook that returns something other than an array is ignored rather than
     * breaking the resource.
     *
     * @template TValue of array<array-key, mixed>
     *
     * @param  TValue  $value
     * @return TValue
     */
    protected static function arrayFrom(?callable $callback, array $value): array
    {
        if ($callback === null) {
            return $value;
        }

        $result = $callback($value);

        return is_array($result) ? $result : $value;
    }
}
