<?php

use Illuminate\Support\Carbon;
use MrAdder\FilamentLogger\Support\ActivityExportCriteria;
use Spatie\Activitylog\Models\Activity as ActivityModel;

function applyCriteria(array $filters): array
{
    return ActivityExportCriteria::fromArray($filters)
        ->apply(ActivityModel::query())
        ->pluck('description')
        ->all();
}

function seedCriteriaActivities(): void
{
    ActivityModel::create([
        'log_name' => 'Access',
        'description' => 'Failed login',
        'event' => 'Failed Login',
        'properties' => ['risk' => 'high'],
    ]);

    ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Order updated',
        'subject_type' => 'App\\Models\\Order',
        'event' => 'Updated',
        'properties' => ['risk' => 'low', 'old' => ['status' => 'paid'], 'attributes' => ['status' => 'refunded']],
    ]);
}

it('maps filament table filter state to criteria', function () {
    $criteria = ActivityExportCriteria::fromTableFilters([
        'search' => ['query' => 'invoice'],
        'log_name' => ['value' => 'Access'],
        'subject_type' => ['value' => 'App\\Models\\Order'],
        'risk' => ['risk' => 'high'],
        'created_at' => ['logged_at' => '2026-08-10', 'preset' => 'last_7_days'],
        'properties->old' => ['old' => 'paid'],
        'properties->attributes' => ['new' => 'refunded'],
    ])->toArray();

    expect($criteria)->toBe([
        'search' => 'invoice',
        'log_name' => 'Access',
        'subject_type' => 'App\\Models\\Order',
        'risk' => 'high',
        'logged_at' => '2026-08-10',
        'old' => 'paid',
        'new' => 'refunded',
        'date_preset' => 'last_7_days',
    ]);
});

it('drops empty filter values', function () {
    $criteria = ActivityExportCriteria::fromTableFilters([
        'search' => ['query' => ''],
        'log_name' => ['value' => null],
        'risk' => ['risk' => 'high'],
    ]);

    expect($criteria->toArray())->toBe(['risk' => 'high'])
        ->and($criteria->isEmpty())->toBeFalse();
});

it('reports an empty criteria set', function () {
    expect(ActivityExportCriteria::fromTableFilters([])->isEmpty())->toBeTrue()
        ->and(ActivityExportCriteria::fromArray([])->isEmpty())->toBeTrue();
});

it('leaves the query untouched with no criteria', function () {
    seedCriteriaActivities();

    expect(applyCriteria([]))->toHaveCount(2);
});

it('filters by log name', function () {
    seedCriteriaActivities();

    expect(applyCriteria(['log_name' => 'Access']))->toBe(['Failed login']);
});

it('filters by subject type', function () {
    seedCriteriaActivities();

    expect(applyCriteria(['subject_type' => 'App\\Models\\Order']))->toBe(['Order updated']);
});

it('filters by risk', function () {
    seedCriteriaActivities();

    expect(applyCriteria(['risk' => 'high']))->toBe(['Failed login']);
});

it('filters by the old and new property values', function () {
    seedCriteriaActivities();

    expect(applyCriteria(['old' => 'paid']))->toBe(['Order updated'])
        ->and(applyCriteria(['new' => 'refunded']))->toBe(['Order updated']);
});

it('filters by a logged date', function () {
    Carbon::setTestNow('2026-08-10 12:00:00');

    seedCriteriaActivities();

    expect(applyCriteria(['logged_at' => '2026-08-10']))->toHaveCount(2)
        ->and(applyCriteria(['logged_at' => '2020-01-01']))->toBeEmpty();

    Carbon::setTestNow();
});

it('filters by a date preset', function () {
    Carbon::setTestNow('2026-08-10 12:00:00');

    seedCriteriaActivities();

    expect(applyCriteria(['date_preset' => 'today']))->toHaveCount(2);

    Carbon::setTestNow();
});

it('applies a saved export preset', function () {
    config()->set('filament-logger.exports.presets', [
        'auth_only' => ['label' => 'Auth', 'filters' => ['log_names' => ['Access']]],
    ]);

    seedCriteriaActivities();

    expect(applyCriteria(['preset' => 'auth_only']))->toBe(['Failed login']);
});

it('ignores an unknown export preset', function () {
    seedCriteriaActivities();

    expect(applyCriteria(['preset' => 'does_not_exist']))->toHaveCount(2);
});

it('combines several criteria', function () {
    seedCriteriaActivities();

    expect(applyCriteria(['log_name' => 'Resource', 'risk' => 'low']))->toBe(['Order updated']);
});

it('ignores an unrecognised criteria key', function () {
    seedCriteriaActivities();

    expect(applyCriteria(['not_a_filter' => 'value']))->toHaveCount(2);
});
