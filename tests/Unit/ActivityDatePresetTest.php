<?php

use Illuminate\Support\Carbon;
use MrAdder\FilamentLogger\Support\ActivityDatePreset;
use Spatie\Activitylog\Models\Activity as ActivityModel;

beforeEach(function () {
    Carbon::setTestNow('2026-08-10 14:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lists the configured presets', function () {
    expect(ActivityDatePreset::options())
        ->toHaveKey('today')
        ->toHaveKey('last_7_days')
        ->toHaveKey('this_month');
});

it('can have its presets replaced through config', function () {
    config()->set('filament-logger.activity_filters.date_presets', ['today' => 'Just today']);

    expect(ActivityDatePreset::options())->toBe(['today' => 'Just today']);
});

it('resolves today to the surrounding day', function () {
    $bounds = ActivityDatePreset::resolve('today');

    expect($bounds['start']->toDateTimeString())->toBe('2026-08-10 00:00:00')
        ->and($bounds['end']->toDateTimeString())->toBe('2026-08-10 23:59:59');
});

it('resolves the last 24 hours relative to now', function () {
    $bounds = ActivityDatePreset::resolve('last_24_hours');

    expect($bounds['start']->toDateTimeString())->toBe('2026-08-09 14:30:00')
        ->and($bounds['end']->toDateTimeString())->toBe('2026-08-10 14:30:00');
});

it('resolves rolling day windows inclusively', function () {
    $sevenDays = ActivityDatePreset::resolve('last_7_days');
    $thirtyDays = ActivityDatePreset::resolve('last_30_days');

    // Seven days inclusive of today, so six days back at the start of the day.
    expect($sevenDays['start']->toDateTimeString())->toBe('2026-08-04 00:00:00')
        ->and($sevenDays['end']->toDateTimeString())->toBe('2026-08-10 23:59:59')
        ->and($thirtyDays['start']->toDateTimeString())->toBe('2026-07-12 00:00:00');
});

it('resolves this month to the calendar month', function () {
    $bounds = ActivityDatePreset::resolve('this_month');

    expect($bounds['start']->toDateTimeString())->toBe('2026-08-01 00:00:00')
        ->and($bounds['end']->toDateTimeString())->toBe('2026-08-31 23:59:59');
});

it('returns null for an unknown or missing preset', function () {
    expect(ActivityDatePreset::resolve('not_a_preset'))->toBeNull()
        ->and(ActivityDatePreset::resolve(null))->toBeNull();
});

it('accepts an explicit reference time', function () {
    $bounds = ActivityDatePreset::resolve('today', Carbon::parse('2020-01-15 08:00:00'));

    expect($bounds['start']->toDateTimeString())->toBe('2020-01-15 00:00:00');
});

it('filters a query to the preset window', function () {
    ActivityModel::create(['log_name' => 'Resource', 'description' => 'Today', 'event' => 'Updated']);

    $old = ActivityModel::create(['log_name' => 'Resource', 'description' => 'Old', 'event' => 'Updated']);
    $old->forceFill(['created_at' => Carbon::parse('2026-01-01 10:00:00')])->save();

    $results = ActivityDatePreset::apply(ActivityModel::query(), 'today')->pluck('description')->all();

    expect($results)->toBe(['Today']);
});

it('leaves the query untouched for an unknown preset', function () {
    ActivityModel::create(['log_name' => 'Resource', 'description' => 'A', 'event' => 'Updated']);

    expect(ActivityDatePreset::apply(ActivityModel::query(), 'nonsense')->count())->toBe(1);
});

it('can filter on a different column', function () {
    $activity = ActivityModel::create(['log_name' => 'Resource', 'description' => 'A', 'event' => 'Updated']);
    $activity->forceFill(['updated_at' => Carbon::parse('2026-01-01 10:00:00')])->save();

    expect(ActivityDatePreset::apply(ActivityModel::query(), 'today', 'updated_at')->count())->toBe(0);
});
