<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\ChartWidget;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;

class TopEventsChartWidget extends ChartWidget
{
    public int $days = 30;

    public int $limit = 5;

    protected static ?string $heading = 'Top Events';

    protected function getData(): array
    {
        $data = app(ActivityAnalytics::class)->topEvents($this->days, $this->limit);

        return [
            'labels' => $data['labels'],
            'datasets' => [[
                'label' => 'Events',
                'data' => $data['values'],
                'backgroundColor' => [
                    '#14b8a6',
                    '#0d9488',
                    '#0f766e',
                    '#115e59',
                    '#134e4a',
                ],
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
