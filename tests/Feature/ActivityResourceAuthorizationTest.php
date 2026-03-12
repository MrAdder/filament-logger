<?php

use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\Resources\ActivityResource;

it('denies access when no activity policy is registered in strict mode', function () {
    config()->set('filament-logger.authorization.strict', true);

    expect(ActivityResource::canAccess())->toBeFalse();
});

it('requires an explicit view method for single record access in strict mode', function () {
    config()->set('filament-logger.authorization.strict', true);

    Gate::policy(ActivityResource::getModel(), ViewAnyOnlyActivityPolicy::class);

    $activityModel = ActivityResource::getModel();
    $record = new $activityModel();

    expect(ActivityResource::canViewAny())->toBeTrue()
        ->and(ActivityResource::canView($record))->toBeFalse();
});

it('allows access when strict authorization is disabled', function () {
    config()->set('filament-logger.authorization.strict', false);

    expect(ActivityResource::canAccess())->toBeTrue();
});

it('allows access when the activity policy defines view abilities', function () {
    config()->set('filament-logger.authorization.strict', true);

    Gate::policy(ActivityResource::getModel(), AllowAllActivityPolicy::class);

    $activityModel = ActivityResource::getModel();
    $record = new $activityModel();

    expect(ActivityResource::canAccess())->toBeTrue()
        ->and(ActivityResource::canView($record))->toBeTrue();
});

class ViewAnyOnlyActivityPolicy
{
    public function viewAny(?object $user = null): bool
    {
        return true;
    }
}

class AllowAllActivityPolicy
{
    public function viewAny(?object $user = null): bool
    {
        return true;
    }

    public function view(?object $user = null, mixed $record = null): bool
    {
        return true;
    }
}
