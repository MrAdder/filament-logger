<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;
use MrAdder\FilamentLogger\Widgets\Concerns\HasActivityReviewDrillDownHeading;

class ActivityTrendChartWidget extends ChartWidget
{
    use HasActivityReviewDrillDownHeading;

    public int $days = 30;

    protected function getData(): array
    {
        $trend = app(ActivityAnalytics::class)->trend($this->days);

        return [
            'labels' => $trend['labels'],
            'datasets' => [[
                'label' => 'Activity',
                'data' => $trend['values'],
                'borderColor' => '#0f766e',
                'backgroundColor' => 'rgba(15, 118, 110, 0.15)',
                'tension' => 0.3,
                'fill' => true,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->activityReviewHeadingForPlaybook(__('filament-logger::filament-logger.widget.trend.heading'), 'all_activity');
    }
}
