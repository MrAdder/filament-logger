<?php

use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\Resources\ActivityResource;
use MrAdder\FilamentLogger\Resources\ActivityResource\Pages\BaseListActivities;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The list page class differs per Filament version, so resolve it the same way
 * the resource does instead of naming one directly.
 *
 * @return class-string<BaseListActivities>
 */
function exportPageClass(): string
{
    return (new ReflectionMethod(ActivityResource::class, 'getListActivitiesPage'))->invoke(null);
}

beforeEach(function () {
    config()->set('filament-logger.exports.enabled', true);
    config()->set('filament-logger.exports.ability', 'exportActivity');
});

it('denies export when no gate grants the ability', function () {
    expect(exportPageClass()::canExport())->toBeFalse();
});

it('allows export when the gate grants the ability', function () {
    Gate::define('exportActivity', fn ($user = null): bool => true);

    expect(exportPageClass()::canExport())->toBeTrue();
});

it('allows export for every viewer when the ability is disabled', function () {
    config()->set('filament-logger.exports.ability', null);

    expect(exportPageClass()::canExport())->toBeTrue();
});

it('denies preset management when no gate grants the ability', function () {
    expect(exportPageClass()::canManageExportPresets())->toBeFalse();
});

it('allows preset management when the gate grants the ability', function () {
    Gate::define('manageExportPresets', fn ($user = null): bool => true);

    expect(exportPageClass()::canManageExportPresets())->toBeTrue();
});

it('aborts a direct csv export call when the ability is missing', function () {
    $class = exportPageClass();
    $page = new $class;

    expect(fn () => $page->exportCsv())
        ->toThrow(HttpException::class);
});

it('aborts a direct json export call when the ability is missing', function () {
    $class = exportPageClass();
    $page = new $class;

    expect(fn () => $page->exportJson())
        ->toThrow(HttpException::class);
});

it('aborts a direct export call when exports are disabled entirely', function () {
    Gate::define('exportActivity', fn ($user = null): bool => true);
    config()->set('filament-logger.exports.enabled', false);

    $class = exportPageClass();
    $page = new $class;

    expect(fn () => $page->exportCsv())
        ->toThrow(HttpException::class);
});
