<?php

use Illuminate\Database\Eloquent\Model;
use MrAdder\FilamentLogger\Resources\ActivityResource;
use MrAdder\FilamentLogger\Resources\ActivityResource\Support\ActivityResourceFormSchema;
use MrAdder\FilamentLogger\Resources\ExportPresetResource;
use MrAdder\FilamentLogger\Support\ActivityViewerPrivacy;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * Covers the resource's own accessors and the deprecated form schema helper.
 *
 * The version-specific form()/table()/infolist() entry points are deliberately
 * not built here: they need a live Filament schema host, which differs between
 * Filament 3 and 4+, and ExportPresetResourceTest already asserts the schemas
 * those methods wrap.
 */
it('eager loads the causer on the resource query', function () {
    expect(ActivityResource::getEloquentQuery()->getEagerLoads())->toHaveKey('causer');
});

it('exposes the activity model and navigation metadata', function () {
    expect(ActivityResource::getModel())->toBe(ActivityModel::class)
        ->and(ActivityResource::getNavigationLabel())->toBe('Activity Log')
        ->and(ActivityResource::getNavigationIcon())->toBe('heroicon-o-clipboard-document-list')
        ->and(ActivityResource::getLabel())->toBe('Activity log')
        ->and(ActivityResource::getPluralLabel())->toBe('Activity logs');
});

it('reads the navigation group and sort from config', function () {
    config()->set('filament-logger.resources.navigation_group', 'Audit');
    config()->set('filament-logger.navigation_sort', 42);

    expect(ActivityResource::getNavigationGroup())->toBe('Audit')
        ->and(ActivityResource::getNavigationSort())->toBe(42);
});

it('reads the cluster from config', function () {
    expect(ActivityResource::getCluster())->toBeNull();

    config()->set('filament-logger.resources.cluster', 'App\\Filament\\Clusters\\Audit');

    expect(ActivityResource::getCluster())->toBe('App\\Filament\\Clusters\\Audit');
});

it('registers an index and view page', function () {
    expect(array_keys(ActivityResource::getPages()))->toBe(['index', 'view']);
});

it('exposes export preset navigation metadata', function () {
    expect(ExportPresetResource::getNavigationLabel())->toBe('Export Presets')
        ->and(array_keys(ExportPresetResource::getPages()))->toBe(['index', 'create', 'edit']);
});

// -------------------------------------------- deprecated form schema helper

it('reports whether a record has properties to show', function () {
    $withProperties = ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Changed',
        'event' => 'Updated',
        'properties' => ['ticket' => 'SEC-1'],
    ]);

    $without = ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Changed',
        'event' => 'Updated',
    ]);

    expect(ActivityResourceFormSchema::hasPropertySection($withProperties))->toBeTrue()
        ->and(ActivityResourceFormSchema::hasPropertySection($without))->toBeFalse()
        ->and(ActivityResourceFormSchema::hasPropertySection(null))->toBeFalse();
});

it('builds a property section for each populated group', function () {
    $activity = ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Changed',
        'event' => 'Updated',
        'properties' => [
            'ticket' => 'SEC-1',
            'old' => ['status' => 'paid'],
            'attributes' => ['status' => 'refunded'],
        ],
    ]);

    $names = array_map(
        fn ($component): string => $component->getName(),
        ActivityResourceFormSchema::propertySection($activity),
    );

    expect($names)->toBe(['properties', 'old', 'attributes']);
});

it('returns no property section for a non activity record', function () {
    expect(ActivityResourceFormSchema::propertySection(null))->toBe([])
        ->and(ActivityResourceFormSchema::propertySection(new class extends Model {}))->toBe([]);
});

it('omits groups that are empty', function () {
    $activity = ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Changed',
        'event' => 'Updated',
        'properties' => ['old' => ['status' => 'paid']],
    ]);

    $names = array_map(
        fn ($component): string => $component->getName(),
        ActivityResourceFormSchema::propertySection($activity),
    );

    expect($names)->toBe(['old']);
});

it('redacts sensitive values in the property section', function () {
    $activity = ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Changed',
        'event' => 'Updated',
        'properties' => ['api_token' => 'super-secret'],
    ]);

    $sanitised = ActivityViewerPrivacy::sanitizeProperties(
        $activity->properties->except(['attributes', 'old']),
        $activity,
    );

    expect($sanitised['api_token'])->toBe('[REDACTED]')
        ->and(ActivityResourceFormSchema::propertySection($activity))->toHaveCount(1);
});
