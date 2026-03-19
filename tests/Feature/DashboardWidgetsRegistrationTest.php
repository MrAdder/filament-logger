<?php

use Livewire\Livewire;

it('registers dashboard widgets as livewire components', function () {
    expect(Livewire::exists('mr-adder.filament-logger.widgets.activity-overview-widget'))->toBeTrue()
        ->and(Livewire::exists('mr-adder.filament-logger.widgets.activity-trend-chart-widget'))->toBeTrue()
        ->and(Livewire::exists('mr-adder.filament-logger.widgets.top-users-chart-widget'))->toBeTrue()
        ->and(Livewire::exists('mr-adder.filament-logger.widgets.top-events-chart-widget'))->toBeTrue()
        ->and(Livewire::exists('mr-adder.filament-logger.widgets.high-risk-actions-chart-widget'))->toBeTrue();
});
