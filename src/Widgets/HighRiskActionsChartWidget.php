<?php

namespace MrAdder\FilamentLogger\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;
use MrAdder\FilamentLogger\Widgets\Concerns\HasActivityReviewDrillDownHeading;

class HighRiskActionsChartWidget extends ChartWidget
{
    use HasActivityReviewDrillDownHeading;

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

    public function getHeading(): string|Htmlable|null
    {
        return $this->activityReviewHeadingForPlaybook(__('filament-logger::filament-logger.widget.high_risk.heading'), 'high_risk_incidents');
    }
}
