<?php

use Livewire\Livewire;

it('registers dashboard widgets as livewire components', function () {
    $isRegistered = static function (string $alias): bool {
        if (method_exists(Livewire::getFacadeRoot(), 'exists')) {
            return Livewire::exists($alias);
        }

        return Livewire::isDiscoverable($alias);
    };

    expect($isRegistered('mr-adder.filament-logger.widgets.activity-overview-widget'))->toBeTrue()
        ->and($isRegistered('mr-adder.filament-logger.widgets.activity-trend-chart-widget'))->toBeTrue()
        ->and($isRegistered('mr-adder.filament-logger.widgets.top-users-chart-widget'))->toBeTrue()
        ->and($isRegistered('mr-adder.filament-logger.widgets.top-events-chart-widget'))->toBeTrue()
        ->and($isRegistered('mr-adder.filament-logger.widgets.high-risk-actions-chart-widget'))->toBeTrue();
});
