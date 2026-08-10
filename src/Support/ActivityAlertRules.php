<?php

namespace MrAdder\FilamentLogger\Support;

/**
 * Programmatic registration of alert rules.
 *
 * Config remains the primary way to define rules. This exists for rules that
 * cannot be expressed in a config file — for example ones built from the
 * database, or from a package that ships its own audit rules.
 *
 * Registered rules are merged over the config ones, so registering under an
 * existing key overrides that rule.
 */
final class ActivityAlertRules
{
    /** @var array<string, array<string, mixed>> */
    protected static array $registered = [];

    /** @var (callable(array<string, mixed>): array<string, mixed>)|null */
    protected static $resolveUsing = null;

    /**
     * Register a single rule.
     *
     * @param  array<string, mixed>  $rule
     */
    public static function register(string $name, array $rule): void
    {
        self::$registered[$name] = $rule;
    }

    /**
     * Register several rules at once, keyed by rule name.
     *
     * @param  array<array-key, mixed>  $rules
     */
    public static function registerMany(array $rules): void
    {
        foreach ($rules as $name => $rule) {
            if (is_string($name) && is_array($rule)) {
                self::register($name, $rule);
            }
        }
    }

    /**
     * Take full control of the final rule set.
     *
     * Receives the merged config and registered rules, and returns the rules to
     * use. Runs last, so it can remove rules as well as add them.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $callback
     */
    public static function resolveUsing(?callable $callback): void
    {
        self::$resolveUsing = $callback;
    }

    /**
     * Every rule the dispatcher should consider.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $configured = config('filament-logger.alerts.rules', []);

        $rules = array_merge(
            is_array($configured) ? $configured : [],
            self::$registered,
        );

        return self::applyResolver(self::$resolveUsing, $rules);
    }

    /**
     * The callback comes from application code, so a return value that is not
     * an array is ignored rather than breaking alerting entirely.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected static function applyResolver(?callable $callback, array $rules): array
    {
        if ($callback === null) {
            return $rules;
        }

        $resolved = $callback($rules);

        return is_array($resolved) ? $resolved : $rules;
    }

    /**
     * A single rule by name, or null when it does not exist.
     *
     * @return array<string, mixed>|null
     */
    public static function get(string $name): ?array
    {
        /** @var mixed $rule */
        $rule = self::all()[$name] ?? null;

        return is_array($rule) ? $rule : null;
    }

    /**
     * Clear every registered rule and callback. Intended for tests.
     */
    public static function flush(): void
    {
        self::$registered = [];
        self::$resolveUsing = null;
    }
}
