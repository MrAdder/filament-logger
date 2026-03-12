<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\ChartWidget;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;

class ActivityTrendChartWidget extends ChartWidget
{
    public int $days = 30;

    protected static ?string $heading = 'Activity Trend';

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
}
