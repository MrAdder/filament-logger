<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;
use MrAdder\FilamentLogger\Support\ActivityReviewLink;

class ActivityOverviewWidget extends StatsOverviewWidget
{
    public int $days = 30;

    protected ?string $heading = 'Activity Overview';

    protected function getStats(): array
    {
        $overview = app(ActivityAnalytics::class)->overview($this->days);

        return [
            $this->withDrillDown(
                Stat::make('Total Activity', (string) $overview['total'])
                    ->description("Last {$this->days} days")
                    ->color('primary'),
                'all',
            ),
            $this->withDrillDown(
                Stat::make('High Risk', (string) $overview['high_risk'])
                    ->description('High-risk actions detected')
                    ->color('danger'),
                'high_risk',
            ),
            $this->withDrillDown(
                Stat::make('Failed Logins', (string) $overview['failed_logins'])
                    ->description('Authentication failures')
                    ->color('warning'),
                'failed_logins',
            ),
            $this->withDrillDown(
                Stat::make('Unique Actors', (string) $overview['unique_actors'])
                    ->description('Distinct causers recorded')
                    ->color('success'),
                'all',
            ),
        ];
    }

    protected function withDrillDown(Stat $stat, string $preset): Stat
    {
        $url = ActivityReviewLink::toSavedPreset($preset);

        if (! $url) {
            return $stat;
        }

        return $stat->url($url);
    }
}
