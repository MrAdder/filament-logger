<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Support\Str;

class ActivityReviewPlaybookManager
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return config('filament-logger.activity_playbooks', [
            'all_activity' => [
                'label' => 'All Activity',
                'icon' => 'heroicon-o-bars-3-bottom-left',
                'preset' => 'all',
            ],
            'high_risk_incidents' => [
                'label' => 'High Risk Incidents',
                'icon' => 'heroicon-o-shield-exclamation',
                'preset' => 'high_risk',
                'date_preset' => 'last_30_days',
            ],
            'auth_anomalies' => [
                'label' => 'Auth Anomalies',
                'icon' => 'heroicon-o-finger-print',
                'preset' => 'auth_anomalies',
                'date_preset' => 'last_30_days',
            ],
            'failed_logins' => [
                'label' => 'Failed Logins',
                'icon' => 'heroicon-o-exclamation-triangle',
                'preset' => 'failed_logins',
                'date_preset' => 'last_7_days',
            ],
            'destructive_actions' => [
                'label' => 'Destructive Actions',
                'icon' => 'heroicon-o-fire',
                'preset' => 'destructive_recent',
                'date_preset' => 'last_7_days',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolve(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function toUrlParameters(string $key): array
    {
        $playbook = self::resolve($key);

        if (! $playbook) {
            return [];
        }

        $preset = (string) data_get($playbook, 'preset', 'all');
        $datePreset = data_get($playbook, 'date_preset');
        $parameters = [
            'activeTab' => $preset,
        ];

        if (filled($datePreset)) {
            $parameters['tableFilters'] = [
                'created_at' => [
                    'preset' => (string) $datePreset,
                ],
            ];
        }

        return $parameters;
    }

    public static function label(string $key): string
    {
        return (string) data_get(self::resolve($key), 'label', Str::headline($key));
    }
}
