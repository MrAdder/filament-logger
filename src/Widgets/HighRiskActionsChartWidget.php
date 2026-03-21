<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;
use MrAdder\FilamentLogger\Support\ActivityReviewLink;

class HighRiskActionsChartWidget extends ChartWidget
{
    public int $days = 30;

    public int $limit = 5;

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

    public function getHeading(): string | Htmlable | null
    {
        $heading = 'High-Risk Actions';
        $url = ActivityReviewLink::toSavedPreset('high_risk');

        if (! $url) {
            return $heading;
        }

        return new HtmlString('<a href="'.e($url).'">'.e($heading).'</a>');
    }
}
