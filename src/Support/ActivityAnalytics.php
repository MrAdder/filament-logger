<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use stdClass;

/**
 * The numbers behind the dashboard widgets.
 *
 * Every figure is counted by the database and read back as plain rows, one per
 * day, event or causer. Counting in PHP meant loading one model per activity
 * in the window, so every widget grew slower and hungrier with the audit trail.
 */
class ActivityAnalytics
{
    /**
     * @return array{total:int, high_risk:int, failed_logins:int, unique_actors:int}
     */
    public function overview(int $days): array
    {
        $query = $this->aggregateQuery($this->baseQuery($days));
        $grammar = $query->getGrammar();

        $counts = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN '.$grammar->wrap('properties->risk').' = ? THEN 1 ELSE 0 END) as high_risk', ['high'])
            ->selectRaw('SUM(CASE WHEN '.$grammar->wrap('event').' = ? THEN 1 ELSE 0 END) as failed_logins', ['Failed Login'])
            ->first();

        // causer_id is only checked for NULL: it is an integer column in the
        // default schema, and PostgreSQL rejects comparing one with ''.
        $actors = $this->aggregateQuery($this->baseQuery($days))
            ->whereNotNull('causer_type')
            ->where('causer_type', '<>', '')
            ->whereNotNull('causer_id')
            ->select(['causer_type', 'causer_id'])
            ->distinct();

        return [
            'total' => (int) ($counts->total ?? 0),
            'high_risk' => (int) ($counts->high_risk ?? 0),
            'failed_logins' => (int) ($counts->failed_logins ?? 0),
            // Counted over a distinct subquery: SQLite and PostgreSQL reject
            // COUNT(DISTINCT causer_type, causer_id).
            'unique_actors' => $actors->newQuery()->fromSub($actors, 'actors')->count(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function trend(int $days): array
    {
        $query = $this->aggregateQuery($this->baseQuery($days));
        $day = $this->dayExpression($query);

        $counts = $query
            ->selectRaw("{$day} as activity_date, COUNT(*) as aggregate")
            ->groupByRaw($day)
            ->pluck('aggregate', 'activity_date');

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
        return $this->topValues($this->baseQuery($days), 'event', $limit);
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function highRiskActions(int $days, int $limit): array
    {
        return $this->topValues(
            $this->baseQuery($days)->where('properties->risk', 'high'),
            'event',
            $limit,
        );
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function topUsers(int $days, int $limit): array
    {
        $rows = $this->topGroups(
            $this->baseQuery($days)
                ->whereNotNull('causer_type')
                ->whereNotNull('causer_id'),
            ['causer_type', 'causer_id'],
            $limit,
        );

        return [
            'labels' => $rows
                ->map(fn (stdClass $row): string => $this->resolveModelLabel((string) $row->causer_type, (string) $row->causer_id))
                ->all(),
            'values' => $rows->map(fn (stdClass $row): int => (int) $row->aggregate)->all(),
        ];
    }

    /**
     * @return Builder<Activity>
     */
    protected function baseQuery(int $days)
    {
        return ActivitylogCompat::activityModel()::query()
            ->where('created_at', '>=', now()->subDays(max($days - 1, 0))->startOfDay());
    }

    /**
     * The most frequent values of a column, skipping NULL and empty strings.
     *
     * @param  Builder<Activity>  $query
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    protected function topValues(Builder $query, string $column, int $limit): array
    {
        $rows = $this->topGroups(
            $query->whereNotNull($column)->where($column, '<>', ''),
            [$column],
            $limit,
        );

        return [
            'labels' => $rows->map(fn (stdClass $row): string => (string) $row->{$column})->all(),
            'values' => $rows->map(fn (stdClass $row): int => (int) $row->aggregate)->all(),
        ];
    }

    /**
     * Count the rows per group and keep the $limit largest groups.
     *
     * Ties go to the group recorded first, which keeps the cut-off stable
     * between page loads and matches the first-seen order the in-memory
     * ranking used.
     *
     * @param  Builder<Activity>  $query
     * @param  array<int, string>  $columns
     * @return Collection<int, stdClass>
     */
    protected function topGroups(Builder $query, array $columns, int $limit): Collection
    {
        $key = $query->getModel()->getKeyName();
        $base = $this->aggregateQuery($query);

        return $base
            ->select($columns)
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy($columns)
            ->orderByRaw('COUNT(*) DESC')
            ->orderByRaw('MIN('.$base->getGrammar()->wrap($key).') ASC')
            ->limit($limit)
            ->get();
    }

    /**
     * The underlying query builder, so rows come back as plain objects instead
     * of hydrated models.
     *
     * Any ordering is dropped: a global scope on a custom activity model may add
     * one, and PostgreSQL and MySQL's ONLY_FULL_GROUP_BY reject ordering by a
     * column that is not grouped.
     *
     * @param  Builder<Activity>  $query
     */
    protected function aggregateQuery(Builder $query): QueryBuilder
    {
        return $query->toBase()->reorder();
    }

    /**
     * The calendar day of `created_at` as a `Y-m-d` string, the value Carbon's
     * toDateString() gives for the stored timestamp.
     *
     * DATE() works on SQLite, MySQL, MariaDB and PostgreSQL. SQL Server has no
     * DATE() function and needs the cast instead.
     */
    protected function dayExpression(QueryBuilder $query): string
    {
        $column = $query->getGrammar()->wrap('created_at');

        return $query->getGrammar() instanceof SqlServerGrammar
            ? "CAST({$column} AS DATE)"
            : "DATE({$column})";
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
