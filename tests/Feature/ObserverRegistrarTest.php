<?php

use MrAdder\FilamentLogger\Loggers\ModelLogger;
use MrAdder\FilamentLogger\Support\ObserverRegistrar;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\ObservedTestRecord;
use Spatie\Activitylog\Models\Activity as ActivityModel;

// No manual reset needed: registrations are bound to the application, which
// Testbench rebuilds for every test.

it('registers a model and observer pair once', function () {
    expect(ObserverRegistrar::hasRegistered(ObservedTestRecord::class, ModelLogger::class))->toBeFalse();

    ObserverRegistrar::register(ObservedTestRecord::class, ModelLogger::class);

    expect(ObserverRegistrar::hasRegistered(ObservedTestRecord::class, ModelLogger::class))->toBeTrue();
});

it('does not attach a closure observer twice', function () {
    config()->set('filament-logger.models.enabled', true);

    $logger = new ModelLogger;

    // A second boot in the same process — Octane, a queue worker, or a test
    // that boots the provider again — must not double-register.
    ObserverRegistrar::register(ObservedTestRecord::class, $logger);
    ObserverRegistrar::register(ObservedTestRecord::class, $logger);

    ObservedTestRecord::create(['name' => 'Widget']);

    expect(ActivityModel::where('event', 'Created')->count())->toBe(1);
});

it('treats different observers for the same model as separate registrations', function () {
    ObserverRegistrar::register(ObservedTestRecord::class, ModelLogger::class);

    expect(ObserverRegistrar::hasRegistered(ObservedTestRecord::class, ModelLogger::class))->toBeTrue()
        ->and(ObserverRegistrar::hasRegistered(ObservedTestRecord::class, stdClass::class))->toBeFalse();
});
