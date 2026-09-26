<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use MrAdder\FilamentLogger\Facades\FilamentLogger;
use MrAdder\FilamentLogger\Loggers\ModelLogger;
use MrAdder\FilamentLogger\Support\ActivityChanges;
use MrAdder\FilamentLogger\Support\ActivityChangesFormatter;
use MrAdder\FilamentLogger\Support\ActivityExportCriteria;
use MrAdder\FilamentLogger\Support\ActivityExporter;
use MrAdder\FilamentLogger\Support\ActivitylogCompat;
use MrAdder\FilamentLogger\Support\ActivityRiskResolver;
use MrAdder\FilamentLogger\Support\ObserverRegistrar;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/*
 * spatie/laravel-activitylog v4 keeps the old and new values of a change in
 * `properties`; v5 keeps them in an `attribute_changes` column. These cover the
 * package reading, querying and writing them on either version.
 *
 * Tests that need the v5 column are skipped on v4, and run in the v5 CI job.
 */

const CHANGES_STATUS = ['old' => ['status' => 'paid'], 'attributes' => ['status' => 'refunded']];

/**
 * An unsaved activity whose changes sit in the column v5 uses. v4 does not cast
 * the attribute, which is all the readers under test need.
 *
 * @param  array<string, mixed>  $changes
 * @param  array<string, mixed>  $properties
 */
function changesActivity(array $changes, array $properties = [], string $event = 'Updated'): ActivityModel
{
    return new ActivityModel([
        'event' => $event,
        'properties' => $properties,
        'attribute_changes' => $changes,
    ]);
}

function requiresAttributeChangesColumn(): Closure
{
    return fn (): bool => ! ActivitylogCompat::usesAttributeChanges();
}

function captureChangesExport(callable $export): string
{
    ob_start();
    $export()->sendContent();

    return (string) ob_get_clean();
}

// ------------------------------------------------------------------- reading

it('shapes attribute_changes like v4 properties', function () {
    $properties = ActivityChanges::properties(changesActivity(CHANGES_STATUS, ['risk' => 'low']));

    expect($properties)->toBe(['risk' => 'low'] + CHANGES_STATUS);
});

it('prefers attribute_changes over properties and falls back for an empty section', function () {
    $activity = changesActivity(
        ['old' => ['status' => 'paid']],
        ['old' => ['status' => 'stale'], 'attributes' => ['status' => 'legacy']],
    );

    $properties = ActivityChanges::properties($activity);

    expect($properties['old'])->toBe(['status' => 'paid'])
        ->and($properties['attributes'])->toBe(['status' => 'legacy']);
});

it('reads changes recorded in properties', function () {
    $activity = new ActivityModel(['event' => 'Updated', 'properties' => CHANGES_STATUS]);

    expect(ActivityChanges::properties($activity))->toBe(CHANGES_STATUS);
});

it('lists the changed keys from both sections', function () {
    $activity = changesActivity([
        'old' => ['role' => 'editor', 'name' => 'A'],
        'attributes' => ['role' => 'admin', 'email' => 'a@example.test'],
    ]);

    expect(ActivityChanges::changedKeys($activity))->toBe(['role', 'name', 'email']);
});

it('does not touch a column the table does not have', function () {
    ActivityModel::create(['description' => 'Loaded from the table', 'event' => 'Updated', 'properties' => CHANGES_STATUS]);

    $loaded = ActivityModel::query()->select(['id', 'properties'])->firstOrFail();

    Model::preventAccessingMissingAttributes();

    try {
        expect(ActivityChanges::properties($loaded))->toBe(CHANGES_STATUS);
    } finally {
        Model::preventAccessingMissingAttributes(false);
    }
});

it('splits changes out of a properties array', function () {
    [$properties, $changes] = ActivityChanges::split(['risk' => 'low'] + CHANGES_STATUS);

    expect($properties)->toBe(['risk' => 'low'])
        ->and($changes)->toBe(CHANGES_STATUS);
});

it('shows changes held in attribute_changes in the diff view', function () {
    $rows = collect(ActivityChangesFormatter::for(changesActivity(CHANGES_STATUS))['rows'])->keyBy('field');

    expect($rows['status']['old']['display'])->toBe('paid')
        ->and($rows['status']['new']['display'])->toBe('refunded');
});

it('redacts sensitive values held in attribute_changes', function () {
    $activity = changesActivity([
        'old' => ['password' => 'old-secret'],
        'attributes' => ['password' => 'new-secret'],
    ]);

    $row = collect(ActivityChangesFormatter::for($activity)['rows'])->keyBy('field')['password'];

    expect($row['old']['display'])->toBe('[REDACTED]')
        ->and($row['new']['display'])->toBe('[REDACTED]');
});

it('detects a role change held in attribute_changes', function () {
    $resolver = app(ActivityRiskResolver::class);

    $promoted = changesActivity(['old' => ['role' => 'editor'], 'attributes' => ['role' => 'admin']]);

    expect($resolver->resolveForActivity($promoted))->toBe('high')
        ->and($resolver->resolveForActivity(changesActivity(CHANGES_STATUS)))->not->toBe('high');
});

// ------------------------------------------------------------------- writing

it('records model changes where the installed activitylog stores them', function () {
    TestRecord::flushEventListeners();
    ObserverRegistrar::register(TestRecord::class, ModelLogger::class);

    $record = TestRecord::query()->create(['name' => 'Before']);

    ActivityModel::query()->delete();

    $record->update(['name' => 'After']);

    $activity = ActivityModel::query()->latest('id')->firstOrFail();
    $properties = $activity->properties?->toArray() ?? [];

    if (ActivitylogCompat::usesAttributeChanges()) {
        expect(data_get($activity->attribute_changes, 'old.name'))->toBe('Before')
            ->and(data_get($activity->attribute_changes, 'attributes.name'))->toBe('After')
            ->and($properties)->not->toHaveKeys(['old', 'attributes']);
    } else {
        expect(data_get($properties, 'old.name'))->toBe('Before')
            ->and(data_get($properties, 'attributes.name'))->toBe('After');
    }

    expect(data_get(ActivityChanges::properties($activity), 'attributes.name'))->toBe('After');
});

it('records a custom event diff where the installed activitylog stores it', function () {
    FilamentLogger::log(
        event: 'Refund Issued',
        description: 'Refund issued',
        options: ['anonymous' => true, 'properties' => CHANGES_STATUS + ['amount' => 4200]],
    );

    $activity = ActivityModel::query()->latest('id')->firstOrFail();
    $properties = $activity->properties?->toArray() ?? [];

    if (ActivitylogCompat::usesAttributeChanges()) {
        expect($activity->attribute_changes->toArray())->toBe(CHANGES_STATUS)
            ->and($properties)->not->toHaveKeys(['old', 'attributes'])
            ->and($properties['amount'])->toBe(4200);
    } else {
        expect($properties)->toMatchArray(CHANGES_STATUS + ['amount' => 4200]);
    }
});

it('still flags a role change as high risk once the changes are stored separately', function () {
    FilamentLogger::log(
        event: 'Role Updated',
        description: 'Role changed',
        options: [
            'anonymous' => true,
            'properties' => ['old' => ['role' => 'editor'], 'attributes' => ['role' => 'admin']],
        ],
    );

    $properties = ActivityChanges::properties(ActivityModel::query()->latest('id')->firstOrFail());

    expect($properties['risk'])->toBe('high')
        ->and($properties['risk_reasons'])->toContain('role_change');
});

// ------------------------------------------------------------------ querying

it('matches old and new values held in attribute_changes or in properties', function () {
    ActivityModel::create(['description' => 'In column', 'event' => 'Updated', 'attribute_changes' => CHANGES_STATUS]);
    ActivityModel::create(['description' => 'In properties', 'event' => 'Updated', 'properties' => CHANGES_STATUS]);
    ActivityModel::create([
        'description' => 'Unrelated',
        'event' => 'Updated',
        'attribute_changes' => ['old' => ['status' => 'draft'], 'attributes' => ['status' => 'sent']],
    ]);

    $match = fn (string $path, string $value): array => ActivityChanges::whereJsonPathLike(ActivityModel::query(), $path, $value)
        ->orderBy('id')
        ->pluck('description')
        ->all();

    expect($match('properties->old', 'paid'))->toBe(['In column', 'In properties'])
        ->and($match('properties->attributes', 'refunded'))->toBe(['In column', 'In properties'])
        ->and($match('properties->old', 'sent'))->toBeEmpty();
})->skip(requiresAttributeChangesColumn(), 'attribute_changes only exists on spatie/laravel-activitylog v5');

it('leaves unrelated json paths on properties alone', function () {
    /** @var Builder<ActivityModel> $query */
    $query = ActivityModel::query();

    expect(ActivityChanges::whereJsonPathLike($query, 'properties->risk', 'high')->toSql())
        ->not->toContain('attribute_changes');
});

it('filters an export by old and new values held in attribute_changes', function () {
    ActivityModel::create(['description' => 'Order updated', 'event' => 'Updated', 'attribute_changes' => CHANGES_STATUS]);
    ActivityModel::create(['description' => 'Other', 'event' => 'Updated', 'properties' => ['risk' => 'low']]);

    $apply = fn (array $filters): array => ActivityExportCriteria::fromArray($filters)
        ->apply(ActivityModel::query())
        ->pluck('description')
        ->all();

    expect($apply(['old' => 'paid']))->toBe(['Order updated'])
        ->and($apply(['new' => 'refunded']))->toBe(['Order updated']);
})->skip(requiresAttributeChangesColumn(), 'attribute_changes only exists on spatie/laravel-activitylog v5');

it('searches values held in attribute_changes', function () {
    $hit = ActivityModel::create([
        'description' => 'Order updated',
        'event' => 'Updated',
        'attribute_changes' => ['attributes' => ['status' => 'refundable-xyz']],
    ]);
    ActivityModel::create(['description' => 'Other', 'event' => 'Updated']);

    /** @var Builder<ActivityModel> $query */
    $query = ActivityModel::query();

    expect(ActivityExportCriteria::applySearch($query, 'refundable-xyz')->pluck('id')->all())->toBe([$hit->id]);

    config()->set('filament-logger.search.include_properties', false);

    /** @var Builder<ActivityModel> $skipped */
    $skipped = ActivityModel::query();

    expect(ActivityExportCriteria::applySearch($skipped, 'refundable-xyz')->pluck('id')->all())->toBeEmpty();
})->skip(requiresAttributeChangesColumn(), 'attribute_changes only exists on spatie/laravel-activitylog v5');

// ----------------------------------------------------------------- exporting

it('keeps old and new values in exports', function () {
    ActivityModel::create(['description' => 'Order updated', 'event' => 'Updated', 'attribute_changes' => CHANGES_STATUS]);

    $body = captureChangesExport(fn () => app(ActivityExporter::class)->toJson(ActivityModel::query()));

    expect($body)->toContain('refunded')
        ->and($body)->toContain('paid');
})->skip(requiresAttributeChangesColumn(), 'attribute_changes only exists on spatie/laravel-activitylog v5');
