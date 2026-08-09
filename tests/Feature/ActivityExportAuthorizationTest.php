<?php

use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\Resources\ActivityResource\Pages\ListActivities;

beforeEach(function () {
    config()->set('filament-logger.exports.enabled', true);
    config()->set('filament-logger.exports.ability', 'exportActivity');
});

it('denies export when no gate grants the ability', function () {
    expect(ListActivities::canExport())->toBeFalse();
});

it('allows export when the gate grants the ability', function () {
    Gate::define('exportActivity', fn ($user = null): bool => true);

    expect(ListActivities::canExport())->toBeTrue();
});

it('allows export for every viewer when the ability is disabled', function () {
    config()->set('filament-logger.exports.ability', null);

    expect(ListActivities::canExport())->toBeTrue();
});

it('denies preset management when no gate grants the ability', function () {
    expect(ListActivities::canManageExportPresets())->toBeFalse();
});

it('allows preset management when the gate grants the ability', function () {
    Gate::define('manageExportPresets', fn ($user = null): bool => true);

    expect(ListActivities::canManageExportPresets())->toBeTrue();
});

it('aborts a direct csv export call when the ability is missing', function () {
    $page = new ListActivities;

    expect(fn () => $page->exportCsv())
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('aborts a direct json export call when the ability is missing', function () {
    $page = new ListActivities;

    expect(fn () => $page->exportJson())
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('aborts a direct export call when exports are disabled entirely', function () {
    Gate::define('exportActivity', fn ($user = null): bool => true);
    config()->set('filament-logger.exports.enabled', false);

    $page = new ListActivities;

    expect(fn () => $page->exportCsv())
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});
