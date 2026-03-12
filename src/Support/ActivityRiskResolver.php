<?php

namespace MrAdder\FilamentLogger\Support;

use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class ActivityRiskResolver
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function enrich(string $event, array $properties = [], array $context = [], ?string $explicitRisk = null): array
    {
        $reasons = array_values(array_unique(array_merge(
            (array) data_get($properties, 'risk_reasons', []),
            $this->detectReasons($event, $properties, $context),
        )));

        $risk = $explicitRisk ?? data_get($properties, 'risk');

        if (($risk === null) && ($reasons !== [] || in_array($event, $this->highRiskEvents(), true))) {
            $risk = 'high';
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
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    protected function detectReasons(string $event, array $properties, array $context): array
    {
        $reasons = [];

        if (in_array($event, ['Deleted', 'Force Deleted'], true)) {
            $reasons[] = 'destructive';
        }

        if (in_array($event, ['Failed Login', 'Lockout'], true)) {
            $reasons[] = 'auth_failure';
        }

        $changedKeys = $context['changed_keys'] ?? $this->extractChangedKeys($properties);

        if (array_intersect($changedKeys, $this->roleChangeKeys()) !== []) {
            $reasons[] = 'role_change';
        }

        return array_values(array_unique($reasons));
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
     * @return array<int, string>
     */
    protected function highRiskEvents(): array
    {
        return config('filament-logger.risk.high.events', [
            'Deleted',
            'Force Deleted',
            'Failed Login',
            'Lockout',
        ]);
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
