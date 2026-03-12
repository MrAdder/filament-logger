<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;

class TopUsersChartWidget extends ChartWidget
{
    public int $days = 30;

    public int $limit = 5;

    protected function getData(): array
    {
        $data = app(ActivityAnalytics::class)->topUsers($this->days, $this->limit);

        return [
            'labels' => $data['labels'],
            'datasets' => [[
                'label' => 'Events',
                'data' => $data['values'],
                'backgroundColor' => [
                    '#0ea5e9',
                    '#0284c7',
                    '#0369a1',
                    '#0891b2',
                    '#155e75',
                ],
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): string | Htmlable | null
    {
        return 'Top Users';
    }
}
