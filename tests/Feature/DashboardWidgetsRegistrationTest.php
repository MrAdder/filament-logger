<?php

use MrAdder\FilamentLogger\Widgets\ActivityOverviewWidget;
use MrAdder\FilamentLogger\Widgets\ActivityTrendChartWidget;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopEventsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopUsersChartWidget;

it('registers dashboard widgets as livewire components', function () {
    $resolver = static fn (string $alias): ?string => app('livewire.finder')->resolveClassComponentClassName($alias);

    expect($resolver('mr-adder.filament-logger.widgets.activity-overview-widget'))->toBe(ActivityOverviewWidget::class)
        ->and($resolver('mr-adder.filament-logger.widgets.activity-trend-chart-widget'))->toBe(ActivityTrendChartWidget::class)
        ->and($resolver('mr-adder.filament-logger.widgets.top-users-chart-widget'))->toBe(TopUsersChartWidget::class)
        ->and($resolver('mr-adder.filament-logger.widgets.top-events-chart-widget'))->toBe(TopEventsChartWidget::class)
        ->and($resolver('mr-adder.filament-logger.widgets.high-risk-actions-chart-widget'))->toBe(HighRiskActionsChartWidget::class);
});
