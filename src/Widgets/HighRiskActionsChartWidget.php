<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\ChartWidget;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;

class HighRiskActionsChartWidget extends ChartWidget
{
    public int $days = 30;

    public int $limit = 5;

    protected static ?string $heading = 'High-Risk Actions';

    protected function getData(): array
    {
        $data = app(ActivityAnalytics::class)->highRiskActions($this->days, $this->limit);

        return [
            'labels' => $data['labels'],
            'datasets' => [[
                'label' => 'High-Risk Events',
                'data' => $data['values'],
                'backgroundColor' => [
                    '#ef4444',
                    '#dc2626',
                    '#b91c1c',
                    '#991b1b',
                    '#7f1d1d',
                ],
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
