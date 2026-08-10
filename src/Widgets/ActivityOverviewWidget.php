<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;
use MrAdder\FilamentLogger\Support\ActivityReviewLink;

class ActivityOverviewWidget extends StatsOverviewWidget
{
    public int $days = 30;

    /**
     * StatsOverviewWidget narrows this to ?string, unlike the chart widgets.
     */
    public function getHeading(): ?string
    {
        return static::widgetLabel('overview.heading');
    }

    protected function getStats(): array
    {
        $overview = app(ActivityAnalytics::class)->overview($this->days);

        return [
            $this->withDrillDown(
                Stat::make(static::widgetLabel('overview.total'), (string) $overview['total'])
                    ->description(static::widgetLabel('overview.total_description', ['days' => $this->days]))
                    ->color('primary'),
                'all_activity',
            ),
            $this->withDrillDown(
                Stat::make(static::widgetLabel('overview.high_risk'), (string) $overview['high_risk'])
                    ->description(static::widgetLabel('overview.high_risk_description'))
                    ->color('danger'),
                'high_risk_incidents',
            ),
            $this->withDrillDown(
                Stat::make(static::widgetLabel('overview.failed_logins'), (string) $overview['failed_logins'])
                    ->description(static::widgetLabel('overview.failed_logins_description'))
                    ->color('warning'),
                'failed_logins',
            ),
            $this->withDrillDown(
                Stat::make(static::widgetLabel('overview.unique_actors'), (string) $overview['unique_actors'])
                    ->description(static::widgetLabel('overview.unique_actors_description'))
                    ->color('success'),
                'all_activity',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    protected static function widgetLabel(string $key, array $replace = []): string
    {
        return __("filament-logger::filament-logger.widget.{$key}", $replace);
    }

    protected function withDrillDown(Stat $stat, string $playbook): Stat
    {
        $url = ActivityReviewLink::toPlaybook($playbook);

        if (! $url) {
            return $stat;
        }

        return $stat->url($url);
    }
}
