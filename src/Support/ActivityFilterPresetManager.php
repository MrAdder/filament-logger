<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class ActivityFilterPresetManager
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function saved(): array
    {
        return config('filament-logger.activity_filters.saved', [
            'all' => [
                'label' => 'All Activity',
                'icon' => 'heroicon-o-bars-3-bottom-left',
            ],
            'high_risk' => [
                'label' => 'High Risk',
                'icon' => 'heroicon-o-shield-exclamation',
                'risk' => ['high'],
            ],
            'destructive' => [
                'label' => 'Deletes',
                'icon' => 'heroicon-o-trash',
                'events' => ['Deleted', 'Force Deleted'],
            ],
            'auth_issues' => [
                'label' => 'Auth Issues',
                'icon' => 'heroicon-o-lock-closed',
                'log_names' => [config('filament-logger.access.log_name')],
                'events' => ['Failed Login', 'Lockout'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public static function apply(Builder $query, array $rule): Builder
    {
        if ($logNames = data_get($rule, 'log_names')) {
            $query->whereIn('log_name', $logNames);
        }

        if ($events = data_get($rule, 'events')) {
            $query->whereIn('event', $events);
        }

        if ($subjectTypes = data_get($rule, 'subject_types')) {
            $query->whereIn('subject_type', $subjectTypes);
        }

        if ($risk = data_get($rule, 'risk')) {
            $query->whereIn('properties->risk', (array) $risk);
        }

        if ($descriptionContains = data_get($rule, 'description_contains')) {
            $query->where(function (Builder $query) use ($descriptionContains): void {
                foreach (array_values((array) $descriptionContains) as $index => $needle) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}('description', 'like', '%'.$needle.'%');
                }
            });
        }

        ActivityDatePreset::apply($query, data_get($rule, 'date_preset'));

        return $query;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public static function matches(ActivityContract $activity, array $rule): bool
    {
        if (($logNames = data_get($rule, 'log_names'))
            && ! in_array(data_get($activity, 'log_name'), (array) $logNames, true)) {
            return false;
        }

        if (($events = data_get($rule, 'events'))
            && ! in_array(data_get($activity, 'event'), (array) $events, true)) {
            return false;
        }

        if (($subjectTypes = data_get($rule, 'subject_types'))
            && ! in_array(data_get($activity, 'subject_type'), (array) $subjectTypes, true)) {
            return false;
        }

        if (($risk = data_get($rule, 'risk'))
            && ! in_array(data_get($activity, 'properties.risk'), (array) $risk, true)) {
            return false;
        }

        if (($reasons = data_get($rule, 'risk_reasons'))
            && array_intersect((array) data_get($activity, 'properties.risk_reasons', []), (array) $reasons) === []) {
            return false;
        }

        if (($tags = data_get($rule, 'tags'))
            && array_intersect((array) data_get($activity, 'properties.tags', []), (array) $tags) === []) {
            return false;
        }

        if ($descriptionContains = data_get($rule, 'description_contains')) {
            $description = mb_strtolower((string) data_get($activity, 'description', ''));

            if (! collect((array) $descriptionContains)
                ->contains(fn (string $needle): bool => str_contains($description, mb_strtolower($needle)))) {
                return false;
            }
        }

        return true;
    }
}
