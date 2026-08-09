<?php

use MrAdder\FilamentLogger\Support\PreviousAttributesStore;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;

function trackedModelCount(): int
{
    $property = new ReflectionProperty(PreviousAttributesStore::class, 'attributesByModelKey');
    $property->setAccessible(true);

    return count($property->getValue());
}

beforeEach(function () {
    PreviousAttributesStore::flush();
});

afterEach(function () {
    PreviousAttributesStore::flush();
});

it('releases remembered attributes when an update produces no loggable changes', function () {
    config()->set('filament-logger.models.enabled', true);
    config()->set('filament-logger.models.ignore', ['counter', 'updated_at']);

    $logger = new MrAdder\FilamentLogger\Loggers\ModelLogger;
    $record = TestRecord::create(['name' => 'Tracked', 'counter' => 0]);

    $record->counter = 5;
    $logger->updating($record);

    expect(trackedModelCount())->toBe(1);

    $record->save();
    $logger->updated($record);

    // Every change was ignored, so nothing was logged — the entry still has to
    // go, or a long-running worker accumulates one per skipped update forever.
    expect(trackedModelCount())->toBe(0);
});

it('releases remembered attributes after a logged update', function () {
    $logger = new MrAdder\FilamentLogger\Loggers\ModelLogger;
    $record = TestRecord::create(['name' => 'Tracked']);

    $record->name = 'Renamed';
    $logger->updating($record);
    $record->save();
    $logger->updated($record);

    expect(trackedModelCount())->toBe(0);
});

it('bounds the fallback store so it cannot grow without limit', function () {
    $logger = new MrAdder\FilamentLogger\Loggers\ModelLogger;

    // Simulate updates that never reach updated(), e.g. a rolled back
    // transaction, which is the path that used to leak.
    foreach (range(1, PreviousAttributesStore::MAX_TRACKED_MODELS + 50) as $index) {
        $record = TestRecord::create(['name' => "Record {$index}"]);
        $record->name = "Renamed {$index}";
        $logger->updating($record);
    }

    expect(trackedModelCount())->toBe(PreviousAttributesStore::MAX_TRACKED_MODELS);
});

it('clears all state on flush', function () {
    $record = TestRecord::create(['name' => 'Tracked']);

    PreviousAttributesStore::remember($record, ['name' => 'Old']);
    expect(trackedModelCount())->toBe(1);

    PreviousAttributesStore::flush();

    expect(trackedModelCount())->toBe(0)
        ->and(PreviousAttributesStore::get($record))->toBe([]);
});
