<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;

class ActivityOverviewWidget extends StatsOverviewWidget
{
    public int $days = 30;

    protected ?string $heading = 'Activity Overview';

    protected function getStats(): array
    {
        $overview = app(ActivityAnalytics::class)->overview($this->days);

        return [
            Stat::make('Total Activity', (string) $overview['total'])
                ->description("Last {$this->days} days")
                ->color('primary'),
            Stat::make('High Risk', (string) $overview['high_risk'])
                ->description('High-risk actions detected')
                ->color('danger'),
            Stat::make('Failed Logins', (string) $overview['failed_logins'])
                ->description('Authentication failures')
                ->color('warning'),
            Stat::make('Unique Actors', (string) $overview['unique_actors'])
                ->description('Distinct causers recorded')
                ->color('success'),
        ];
    }
}
