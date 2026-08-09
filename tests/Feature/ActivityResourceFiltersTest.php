<?php

use Filament\Tables\Filters\BaseFilter;
use MrAdder\FilamentLogger\Resources\ActivityResource;

/**
 * Filament renamed the filter and action form APIs between v3 (`form()`) and
 * v4 (`schema()`). The version-specific hooks exist to absorb that, but nothing
 * used to build the filters or header actions in a test, so a shared class
 * calling the v4 API directly broke Filament 3 silently. These tests invoke the
 * builders so that regression cannot come back unnoticed.
 */
function invokeProtected(object|string $target, string $method): mixed
{
    $reflection = new ReflectionMethod(is_string($target) ? $target : $target::class, $method);

    return $reflection->invoke(is_string($target) ? null : $target);
}

/**
 * The list page class differs per Filament version, so tests must go through
 * the same resolver the resource uses rather than naming a class directly.
 *
 * @return class-string<\MrAdder\FilamentLogger\Resources\ActivityResource\Pages\BaseListActivities>
 */
function listActivitiesPage(): string
{
    return invokeProtected(ActivityResource::class, 'getListActivitiesPage');
}

it('builds the activity table filters on the installed Filament version', function () {
    $filters = invokeProtected(ActivityResource::class, 'getTableFilters');

    expect($filters)->toBeArray()->not->toBeEmpty();

    foreach ($filters as $filter) {
        expect($filter)->toBeInstanceOf(BaseFilter::class);
    }
});

it('attaches a field to the search filter', function () {
    $filters = collect(invokeProtected(ActivityResource::class, 'getTableFilters'));

    $search = $filters->first(fn (BaseFilter $filter): bool => $filter->getName() === 'search');

    expect($search)->not->toBeNull();

    // getFormSchema() on v3, getChildSchema()/getSchema() on v4+. Whichever the
    // installed version exposes, the fields must have been attached.
    $fields = match (true) {
        method_exists($search, 'getFormSchema') => $search->getFormSchema(),
        default => $search->getDefaultChildComponents(),
    };

    expect($fields)->not->toBeEmpty();
});

it('builds the list page header actions on the installed Filament version', function () {
    config()->set('filament-logger.exports.enabled', true);
    config()->set('filament-logger.exports.ability', null);
    config()->set('filament-logger.exports.db_presets_enabled', true);
    config()->set('filament-logger.exports.presets', [
        'high_risk' => ['label' => 'High risk', 'columns' => ['id', 'description']],
    ]);

    $page = listActivitiesPage();

    $actions = invokeProtected(new $page, 'getHeaderActions');

    expect($actions)->toBeArray()->not->toBeEmpty();
});
