<?php

namespace MrAdder\FilamentLogger\Support;

use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class ActivityRiskResolver
{
    /**
     * Severity of the reasons this class has always detected. Config-defined
     * heuristics carry their own level; these stay fixed so existing installs
     * keep classifying the same activity the same way.
     *
     * @var array<string, string>
     */
    protected const BUILT_IN_REASON_LEVELS = [
        'destructive' => 'high',
        'auth_failure' => 'high',
        'role_change' => 'high',
    ];

    /**
     * Ordered most severe first.
     *
     * @var array<int, string>
     */
    protected const LEVELS = ['high', 'medium', 'low'];

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function enrich(string $event, array $properties = [], array $context = [], ?string $explicitRisk = null): array
    {
        $detected = $this->detectReasons($event, $properties, $context);

        $reasons = array_values(array_unique(array_merge(
            (array) data_get($properties, 'risk_reasons', []),
            array_keys($detected),
        )));

        $risk = $explicitRisk ?? data_get($properties, 'risk');

        if ($risk === null) {
            $risk = $this->resolveLevel($event, $reasons, $detected);
        }

        if ($risk !== null) {
            $properties['risk'] = $risk;
        }

        if ($reasons !== []) {
            $properties['risk_reasons'] = $reasons;
        }

        return $properties;
    }

    public function resolveForActivity(ActivityContract $activity): ?string
    {
        $risk = data_get($activity, 'properties.risk');

        if (filled($risk)) {
            return $risk;
        }

        $properties = data_get($activity, 'properties', []);

        if (is_object($properties) && method_exists($properties, 'toArray')) {
            $properties = $properties->toArray();
        }

        return $this->enrich(
            event: (string) data_get($activity, 'event'),
            properties: is_array($properties) ? $properties : [],
        )['risk'] ?? null;
    }

    /**
     * The severity a single reason carries, or null if it is unknown.
     */
    public function levelForReason(string $reason): ?string
    {
        if (isset(static::BUILT_IN_REASON_LEVELS[$reason])) {
            return static::BUILT_IN_REASON_LEVELS[$reason];
        }

        $level = data_get($this->heuristics(), $reason.'.level');

        return in_array($level, static::LEVELS, true) ? $level : null;
    }

    /**
     * Detected reasons mapped to their severity.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    protected function detectReasons(string $event, array $properties, array $context): array
    {
        $reasons = [];

        if (in_array($event, ['Deleted', ActivityEvents::FORCE_DELETED], true)) {
            $reasons['destructive'] = 'high';
        }

        if (in_array($event, [ActivityEvents::FAILED_LOGIN, 'Lockout'], true)) {
            $reasons['auth_failure'] = 'high';
        }

        $changedKeys = $context['changed_keys'] ?? $this->extractChangedKeys($properties);

        if (array_intersect($changedKeys, $this->roleChangeKeys()) !== []) {
            $reasons['role_change'] = 'high';
        }

        foreach ($this->heuristics() as $reason => $heuristic) {
            if (! is_string($reason) || ! is_array($heuristic)) {
                continue;
            }

            $level = data_get($heuristic, 'level', 'high');
            $level = in_array($level, static::LEVELS, true) ? $level : 'high';

            $matchesKeys = array_intersect($changedKeys, (array) data_get($heuristic, 'change_keys', [])) !== [];
            $matchesEvent = in_array($event, (array) data_get($heuristic, 'events', []), true);

            if ($matchesKeys || $matchesEvent) {
                $reasons[$reason] = $level;
            }
        }

        return $reasons;
    }

    /**
     * @param  array<int, string>  $reasons
     * @param  array<string, string>  $detected
     */
    protected function resolveLevel(string $event, array $reasons, array $detected): ?string
    {
        return $this->mostSevere($this->candidateLevels($event, $reasons, $detected));
    }

    /**
     * Levels in play for this activity.
     *
     * An event configured as high or medium risk settles the matter on its own;
     * only otherwise do the detected reasons contribute.
     *
     * @param  array<int, string>  $reasons
     * @param  array<string, string>  $detected
     * @return array<int, string>
     */
    protected function candidateLevels(string $event, array $reasons, array $detected): array
    {
        if (in_array($event, $this->highRiskEvents(), true)) {
            return ['high'];
        }

        if (in_array($event, $this->mediumRiskEvents(), true)) {
            return ['medium'];
        }

        return array_map(
            fn (string $reason): string => $detected[$reason] ?? $this->levelForReason($reason) ?? 'high',
            $reasons,
        );
    }

    /**
     * @param  array<int, string>  $levels
     */
    protected function mostSevere(array $levels): ?string
    {
        foreach (static::LEVELS as $level) {
            if (in_array($level, $levels, true)) {
                return $level;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<int, string>
     */
    protected function extractChangedKeys(array $properties): array
    {
        return array_values(array_unique(array_merge(
            array_keys((array) data_get($properties, 'old', [])),
            array_keys((array) data_get($properties, 'attributes', [])),
        )));
    }

    /**
     * User-supplied config, so the shape is not guaranteed.
     *
     * @return array<mixed, mixed>
     */
    protected function heuristics(): array
    {
        $heuristics = config('filament-logger.risk.heuristics', []);

        return is_array($heuristics) ? $heuristics : [];
    }

    /**
     * @return array<int, string>
     */
    protected function highRiskEvents(): array
    {
        return config('filament-logger.risk.high.events', [
            'Deleted',
            ActivityEvents::FORCE_DELETED,
            ActivityEvents::FAILED_LOGIN,
            'Lockout',
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function mediumRiskEvents(): array
    {
        return config('filament-logger.risk.medium.events', []);
    }

    /**
     * @return array<int, string>
     */
    protected function roleChangeKeys(): array
    {
        return config('filament-logger.risk.high.change_keys', [
            'role',
            'role_id',
            'roles',
            'permission',
            'permissions',
        ]);
    }
}
