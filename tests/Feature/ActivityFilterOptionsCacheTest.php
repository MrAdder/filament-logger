<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use MrAdder\FilamentLogger\Resources\ActivityResource\Support\ActivityResourceTableOptions;
use Spatie\Activitylog\Models\Activity as ActivityModel;

function countDistinctQueries(callable $callback): int
{
    $queries = 0;

    DB::listen(function ($query) use (&$queries): void {
        if (str_contains(strtolower($query->sql), 'distinct')) {
            $queries++;
        }
    });

    $callback();

    return $queries;
}

beforeEach(function () {
    config()->set('filament-logger.performance.cache_store', 'array');
    Cache::store('array')->flush();
    ActivityResourceTableOptions::flushCache();

    ActivityModel::create(['log_name' => 'Access', 'description' => 'Login']);
    ActivityModel::create(['log_name' => 'Resource', 'description' => 'Updated']);
});

afterEach(function () {
    Cache::store('array')->flush();
});

it('scans the activity table only once for repeated renders', function () {
    $queries = countDistinctQueries(function (): void {
        ActivityResourceTableOptions::logNames();
        ActivityResourceTableOptions::logNames();
        ActivityResourceTableOptions::logNames();
    });

    expect($queries)->toBe(1);
});

it('still returns the recorded log names', function () {
    expect(ActivityResourceTableOptions::logNames())
        ->toHaveKey('Access')
        ->toHaveKey('Resource');
});

it('re-scans once the cache is flushed', function () {
    ActivityResourceTableOptions::logNames();

    // A log name that is not also produced by config, so it can only appear
    // via the table scan.
    ActivityModel::create(['log_name' => 'Billing', 'description' => 'Invoice issued']);

    expect(ActivityResourceTableOptions::logNames())->not->toHaveKey('Billing');

    ActivityResourceTableOptions::flushCache();

    expect(ActivityResourceTableOptions::logNames())->toHaveKey('Billing');
});

it('scans on every call when caching is disabled', function () {
    config()->set('filament-logger.performance.filter_options_cache_ttl', 0);

    $queries = countDistinctQueries(function (): void {
        ActivityResourceTableOptions::logNames();
        ActivityResourceTableOptions::logNames();
    });

    expect($queries)->toBe(2);
});

it('bounds how many distinct values it pulls', function () {
    config()->set('filament-logger.performance.filter_options_limit', 1);
    ActivityResourceTableOptions::flushCache();

    $names = ActivityResourceTableOptions::logNames();

    // Configured log names are always present; only the values discovered by
    // scanning the table are limited.
    expect($names)->toBeArray();

    $queries = countDistinctQueries(fn () => ActivityResourceTableOptions::logNames());

    expect($queries)->toBe(0);
});
