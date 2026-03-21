<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;
use MrAdder\FilamentLogger\Support\ActivityReviewLink;

class TopEventsChartWidget extends ChartWidget
{
    public int $days = 30;

    public int $limit = 5;

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

    public function getHeading(): string | Htmlable | null
    {
        $heading = 'Top Events';
        $url = ActivityReviewLink::toSavedPreset('all');

        if (! $url) {
            return $heading;
        }

        return new HtmlString('<a href="'.e($url).'">'.e($heading).'</a>');
    }
}
