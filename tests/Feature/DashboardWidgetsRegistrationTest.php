<?php

use Livewire\Livewire;
use MrAdder\FilamentLogger\Widgets\ActivityOverviewWidget;
use MrAdder\FilamentLogger\Widgets\ActivityTrendChartWidget;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopEventsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopUsersChartWidget;

it('registers dashboard widgets as livewire components', function () {
    $resolver = static function (string $alias): ?string {
        $livewire = Livewire::getFacadeRoot();
        $resolved = null;

        if (method_exists($livewire, 'new')) {
            try {
                $resolved = get_class(Livewire::new($alias));
            } catch (Throwable) {
                $resolved = null;
            }
        } elseif (
            (method_exists($livewire, 'exists') && Livewire::exists($alias))
            || (method_exists($livewire, 'isDiscoverable') && Livewire::isDiscoverable($alias))
        ) {
            $resolved = '__registered__';
        }

        return $resolved;
    };

    expect($resolver('mr-adder.filament-logger.widgets.activity-overview-widget'))->toBeIn([
        ActivityOverviewWidget::class,
        '__registered__',
    ])
        ->and($resolver('mr-adder.filament-logger.widgets.activity-trend-chart-widget'))->toBeIn([
            ActivityTrendChartWidget::class,
            '__registered__',
        ])
        ->and($resolver('mr-adder.filament-logger.widgets.top-users-chart-widget'))->toBeIn([
            TopUsersChartWidget::class,
            '__registered__',
        ])
        ->and($resolver('mr-adder.filament-logger.widgets.top-events-chart-widget'))->toBeIn([
            TopEventsChartWidget::class,
            '__registered__',
        ])
        ->and($resolver('mr-adder.filament-logger.widgets.high-risk-actions-chart-widget'))->toBeIn([
            HighRiskActionsChartWidget::class,
            '__registered__',
        ]);
});
