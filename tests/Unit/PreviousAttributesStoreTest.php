<?php

use MrAdder\FilamentLogger\Support\PreviousAttributesStore;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;

it('can retrieve remembered attributes for a fresh instance of the same model', function () {
    $record = TestRecord::query()->create([
        'name' => 'Before',
    ]);

    PreviousAttributesStore::remember($record, ['name' => 'Before']);

    $freshRecord = $record->fresh();

    expect($freshRecord)->not->toBeNull()
        ->and(PreviousAttributesStore::get($freshRecord))->toBe(['name' => 'Before'])
        ->and(PreviousAttributesStore::pull($freshRecord))->toBe(['name' => 'Before'])
        ->and(PreviousAttributesStore::get($record))->toBe([]);
});
