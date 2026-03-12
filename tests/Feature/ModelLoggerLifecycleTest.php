<?php

use MrAdder\FilamentLogger\Loggers\ModelLogger;
use MrAdder\FilamentLogger\Loggers\ResourceLogger;
use MrAdder\FilamentLogger\Support\ReplicationContextStore;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use MrAdder\FilamentLogger\Tests\Fixtures\Resources\TestRecordResource;
use Spatie\Activitylog\Models\Activity;

it('stores old and new values while respecting ignored model fields', function () {
    config()->set('filament-logger.models.ignore', ['updated_at', 'remember_token', 'counter']);

    $record = TestRecord::query()->create([
        'name' => 'Before',
        'counter' => 1,
    ]);

    $record->update([
        'name' => 'After',
        'counter' => 2,
        'remember_token' => 'secret-token',
    ]);

    (new ModelLogger())->updated($record);

    $activity = Activity::query()->latest('id')->firstOrFail();
    $properties = $activity->properties->toArray();

    expect($activity->event)->toBe('Updated')
        ->and(data_get($properties, 'old.name'))->toBe('Before')
        ->and(data_get($properties, 'attributes.name'))->toBe('After')
        ->and(data_get($properties, 'old.counter'))->toBeNull()
        ->and(data_get($properties, 'attributes.counter'))->toBeNull()
        ->and(data_get($properties, 'attributes.remember_token'))->toBeNull();
});

it('supports per-resource ignored fields', function () {
    config()->set('filament-logger.resources.ignore', ['updated_at']);
    config()->set('filament-logger.resources.ignore_for_resources', [
        TestRecordResource::class => ['counter'],
    ]);

    $record = TestRecord::query()->create([
        'name' => 'Before',
        'counter' => 1,
    ]);

    $record->update([
        'name' => 'After',
        'counter' => 9,
    ]);

    (new ResourceLogger(TestRecordResource::class))->updated($record);

    $activity = Activity::query()->latest('id')->firstOrFail();
    $properties = $activity->properties->toArray();

    expect($activity->log_name)->toBe(config('filament-logger.resources.log_name'))
        ->and(data_get($properties, 'attributes.name'))->toBe('After')
        ->and(data_get($properties, 'attributes.counter'))->toBeNull();
});

it('logs restore and force delete without duplicate update or delete events', function () {
    TestRecord::flushEventListeners();
    TestRecord::observe(ModelLogger::class);

    $record = TestRecord::query()->create([
        'name' => 'Lifecycle',
    ]);

    Activity::query()->delete();

    $record->delete();
    $record->restore();
    $record->forceDelete();

    expect(Activity::query()->orderBy('id')->pluck('event')->all())
        ->toBe([
            'Deleted',
            'Restored',
            'Force Deleted',
        ]);
});

it('logs replicated records with source metadata', function () {
    TestRecord::flushEventListeners();
    TestRecord::observe(ModelLogger::class);

    $source = TestRecord::query()->create([
        'name' => 'Source',
    ]);

    Activity::query()->delete();

    $replica = $source->replicate();
    ReplicationContextStore::remember($replica, $source);
    $replica->save();

    $activity = Activity::query()->latest('id')->firstOrFail();
    $properties = $activity->properties->toArray();

    expect($activity->event)->toBe('Replicated')
        ->and(data_get($properties, 'replicated_from.id'))->toBe($source->getKey())
        ->and(data_get($properties, 'replicated_from.label'))->toContain('Test Record #');
});
