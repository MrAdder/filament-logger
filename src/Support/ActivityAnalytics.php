<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Support\Collection;
use Spatie\Activitylog\ActivitylogServiceProvider;

class ActivityAnalytics
{
    /**
     * @return array{total:int, high_risk:int, failed_logins:int, unique_actors:int}
     */
    public function overview(int $days): array
    {
        $activities = $this->baseQuery($days)
            ->get(['id', 'causer_type', 'causer_id', 'event', 'properties']);

        return [
            'total' => $activities->count(),
            'high_risk' => $activities->where('properties.risk', 'high')->count(),
            'failed_logins' => $activities->where('event', 'Failed Login')->count(),
            'unique_actors' => $activities
                ->filter(fn ($activity): bool => filled($activity->causer_type) && filled($activity->causer_id))
                ->map(fn ($activity): string => $activity->causer_type.':'.$activity->causer_id)
                ->unique()
                ->count(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function trend(int $days): array
    {
        $activities = $this->baseQuery($days)->get(['created_at']);
        $counts = $activities
            ->groupBy(fn ($activity): string => $activity->created_at->toDateString())
            ->map->count();

        $labels = [];
        $values = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = now()->subDays($offset)->toDateString();
            $labels[] = $date;
            $values[] = (int) ($counts[$date] ?? 0);
        }

        return compact('labels', 'values');
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function topEvents(int $days, int $limit): array
    {
        return $this->countBy($this->baseQuery($days)->get(['event']), 'event', $limit);
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function highRiskActions(int $days, int $limit): array
    {
        return $this->countBy(
            $this->baseQuery($days)
                ->where('properties->risk', 'high')
                ->get(['event']),
            'event',
            $limit,
        );
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function topUsers(int $days, int $limit): array
    {
        $rows = $this->baseQuery($days)
            ->whereNotNull('causer_type')
            ->whereNotNull('causer_id')
            ->get(['causer_type', 'causer_id'])
            ->groupBy(fn ($activity): string => $activity->causer_type.':'.$activity->causer_id)
            ->map(function (Collection $activities, string $key): array {
                [$type, $id] = explode(':', $key, 2);

                return [
                    'label' => $this->resolveModelLabel($type, $id),
                    'count' => $activities->count(),
                ];
            })
            ->sortByDesc('count')
            ->take($limit)
            ->values();

        return [
            'labels' => $rows->pluck('label')->all(),
            'values' => $rows->pluck('count')->map(fn (mixed $count): int => (int) $count)->all(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\Spatie\Activitylog\Models\Activity>
     */
    protected function baseQuery(int $days)
    {
        return ActivitylogServiceProvider::determineActivityModel()::query()
            ->where('created_at', '>=', now()->subDays(max($days - 1, 0))->startOfDay());
    }

    /**
     * @param  Collection<int, \Spatie\Activitylog\Models\Activity>  $items
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    protected function countBy(Collection $items, string $key, int $limit): array
    {
        $rows = $items
            ->filter(fn ($item): bool => filled($item->{$key}))
            ->countBy($key)
            ->sortDesc()
            ->take($limit);

        return [
            'labels' => $rows->keys()->all(),
            'values' => $rows->map(fn (mixed $count): int => (int) $count)->values()->all(),
        ];
    }

    protected function resolveModelLabel(string $type, string $id): string
    {
        if (! class_exists($type)) {
            return class_basename($type).' #'.$id;
        }

        $record = $type::query()->find($id);

        if ($record === null) {
            return class_basename($type).' #'.$id;
        }

        return $record->name ?? class_basename($type).' #'.$id;
    }
}
