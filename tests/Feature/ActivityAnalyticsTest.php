<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;
use MrAdder\FilamentLogger\Support\ActivitylogCompat;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * These numbers are what the dashboard shows an auditor, so they are worth
 * asserting directly rather than only through the widgets that render them.
 */
function analytics(): ActivityAnalytics
{
    return app(ActivityAnalytics::class);
}

function logActivityAt(string $date, array $attributes = []): ActivityModel
{
    $activity = ActivityModel::create(array_merge([
        'log_name' => 'Resource',
        'description' => 'Something happened',
        'event' => 'Updated',
    ], $attributes));

    // created_at is set by timestamps, so overwrite it explicitly.
    $activity->forceFill(['created_at' => Carbon::parse($date)])->save();

    return $activity;
}

beforeEach(function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('counts totals, high risk, failed logins and unique actors', function () {
    $alice = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.test']);
    $bob = TestUser::create(['name' => 'Bob', 'email' => 'bob@example.test']);

    logActivityAt('2026-08-10 09:00:00', ['causer_type' => $alice::class, 'causer_id' => $alice->getKey()]);
    logActivityAt('2026-08-10 10:00:00', ['causer_type' => $alice::class, 'causer_id' => $alice->getKey()]);
    logActivityAt('2026-08-09 10:00:00', ['causer_type' => $bob::class, 'causer_id' => $bob->getKey()]);
    logActivityAt('2026-08-09 11:00:00', ['event' => 'Failed Login', 'log_name' => 'Access']);
    logActivityAt('2026-08-08 11:00:00', ['event' => 'Deleted', 'properties' => ['risk' => 'high']]);

    expect(analytics()->overview(30))->toBe([
        'total' => 5,
        'high_risk' => 1,
        'failed_logins' => 1,
        'unique_actors' => 2,
    ]);
});

it('ignores activity outside the lookback window', function () {
    logActivityAt('2026-08-10 09:00:00');
    logActivityAt('2026-06-01 09:00:00');

    expect(analytics()->overview(7)['total'])->toBe(1);
});

it('counts anonymous activity but not as an actor', function () {
    logActivityAt('2026-08-10 09:00:00');

    $overview = analytics()->overview(30);

    expect($overview['total'])->toBe(1)
        ->and($overview['unique_actors'])->toBe(0);
});

it('builds a trend with one entry per day, oldest first', function () {
    logActivityAt('2026-08-10 09:00:00');
    logActivityAt('2026-08-10 10:00:00');
    logActivityAt('2026-08-08 10:00:00');

    $trend = analytics()->trend(3);

    expect($trend['labels'])->toBe(['2026-08-08', '2026-08-09', '2026-08-10'])
        ->and($trend['values'])->toBe([1, 0, 2]);
});

it('returns zeroed days when there is no activity', function () {
    $trend = analytics()->trend(2);

    expect($trend['labels'])->toHaveCount(2)
        ->and($trend['values'])->toBe([0, 0]);
});

it('ranks the top events and respects the limit', function () {
    foreach (range(1, 3) as $ignored) {
        logActivityAt('2026-08-10 09:00:00', ['event' => 'Updated']);
    }

    logActivityAt('2026-08-10 09:00:00', ['event' => 'Deleted']);
    logActivityAt('2026-08-10 09:00:00', ['event' => 'Deleted']);
    logActivityAt('2026-08-10 09:00:00', ['event' => 'Created']);

    $top = analytics()->topEvents(30, 2);

    expect($top['labels'])->toBe(['Updated', 'Deleted'])
        ->and($top['values'])->toBe([3, 2]);
});

it('ranks only high risk activity in the high risk breakdown', function () {
    logActivityAt('2026-08-10 09:00:00', ['event' => 'Deleted', 'properties' => ['risk' => 'high']]);
    logActivityAt('2026-08-10 09:00:00', ['event' => 'Deleted', 'properties' => ['risk' => 'high']]);
    logActivityAt('2026-08-10 09:00:00', ['event' => 'Updated', 'properties' => ['risk' => 'low']]);
    logActivityAt('2026-08-10 09:00:00', ['event' => 'Viewed']);

    $high = analytics()->highRiskActions(30, 5);

    expect($high['labels'])->toBe(['Deleted'])
        ->and($high['values'])->toBe([2]);
});

it('ranks top users by their causer name', function () {
    $alice = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.test']);
    $bob = TestUser::create(['name' => 'Bob', 'email' => 'bob@example.test']);

    foreach (range(1, 3) as $ignored) {
        logActivityAt('2026-08-10 09:00:00', ['causer_type' => $alice::class, 'causer_id' => $alice->getKey()]);
    }

    logActivityAt('2026-08-10 09:00:00', ['causer_type' => $bob::class, 'causer_id' => $bob->getKey()]);

    $top = analytics()->topUsers(30, 5);

    expect($top['labels'])->toBe(['Alice', 'Bob'])
        ->and($top['values'])->toBe([3, 1]);
});

it('falls back to a class and id label for a deleted causer', function () {
    $alice = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.test']);
    $id = $alice->getKey();

    logActivityAt('2026-08-10 09:00:00', ['causer_type' => $alice::class, 'causer_id' => $id]);

    $alice->delete();

    expect(analytics()->topUsers(30, 5)['labels'])->toBe(["TestUser #{$id}"]);
});

it('falls back to a class and id label for an unknown causer class', function () {
    logActivityAt('2026-08-10 09:00:00', [
        'causer_type' => 'App\\Models\\GoneAway',
        'causer_id' => 7,
    ]);

    expect(analytics()->topUsers(30, 5)['labels'])->toBe(['GoneAway #7']);
});

it('excludes anonymous activity from top users', function () {
    logActivityAt('2026-08-10 09:00:00');

    expect(analytics()->topUsers(30, 5))->toBe(['labels' => [], 'values' => []]);
});

/**
 * One three day window, 2026-08-08 to 2026-08-10, with every edge the
 * aggregation has to get right: a row exactly on the window start and one just
 * before it, blank and NULL events, a causer with an empty type, a JSON array
 * in `properties`, and a tie at the top-N cut-off.
 */
function seedAnalyticsFixture(): void
{
    $alice = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.test']);
    $bob = TestUser::create(['name' => 'Bob', 'email' => 'bob@example.test']);
    $carol = TestUser::create(['name' => 'Carol', 'email' => 'carol@example.test']);

    $causedBy = fn (TestUser $user): array => ['causer_type' => $user::class, 'causer_id' => $user->getKey()];
    $highRisk = ['properties' => ['risk' => 'high']];

    logActivityAt('2026-08-08 00:00:00', ['event' => 'Updated']);
    logActivityAt('2026-08-08 09:00:00', ['event' => 'Updated'] + $causedBy($alice));
    logActivityAt('2026-08-09 09:00:00', ['event' => 'Deleted'] + $highRisk + $causedBy($alice));
    logActivityAt('2026-08-09 10:00:00', ['event' => 'Deleted'] + $highRisk + $causedBy($bob));
    logActivityAt('2026-08-09 11:00:00', ['event' => 'Failed Login', 'log_name' => 'Access'] + $highRisk + $causedBy($bob));
    logActivityAt('2026-08-10 08:00:00', ['event' => ''] + $highRisk + $causedBy($alice));
    logActivityAt('2026-08-10 09:00:00', ['event' => null, 'properties' => [['risk' => 'high']]]);
    logActivityAt('2026-08-10 10:00:00', ['event' => 'Viewed', 'causer_type' => '', 'causer_id' => 7, 'properties' => ['risk' => 'low']]);
    logActivityAt('2026-08-10 11:00:00', ['event' => 'Updated'] + $causedBy($alice));

    logActivityAt('2026-08-07 23:59:59', ['event' => 'Deleted'] + $highRisk + $causedBy($carol));
    logActivityAt('2026-07-01 09:00:00', ['event' => 'Created'] + $causedBy($carol));
}

function countRetrieved(string $modelClass, Closure $callback): int
{
    $retrieved = 0;

    Event::listen("eloquent.retrieved: {$modelClass}", function () use (&$retrieved): void {
        $retrieved++;
    });

    $callback();

    return $retrieved;
}

it('counts the overview of a window with blanks, a JSON array and edge rows', function () {
    seedAnalyticsFixture();

    // The blank event still counts as high risk; the JSON array does not carry
    // the flag; the empty causer type is not an actor.
    expect(analytics()->overview(3))->toBe([
        'total' => 9,
        'high_risk' => 4,
        'failed_logins' => 1,
        'unique_actors' => 2,
    ]);
});

it('counts the trend of a window including the row on its start', function () {
    seedAnalyticsFixture();

    expect(analytics()->trend(3))->toBe([
        'labels' => ['2026-08-08', '2026-08-09', '2026-08-10'],
        'values' => [2, 3, 4],
    ]);
});

it('ranks events without blank or NULL events and breaks ties by first appearance', function () {
    seedAnalyticsFixture();

    expect(analytics()->topEvents(3, 5))->toBe([
        'labels' => ['Updated', 'Deleted', 'Failed Login', 'Viewed'],
        'values' => [3, 2, 1, 1],
    ])->and(analytics()->topEvents(3, 3))->toBe([
        'labels' => ['Updated', 'Deleted', 'Failed Login'],
        'values' => [3, 2, 1],
    ]);
});

it('ranks high risk events without the blank event or the JSON array', function () {
    seedAnalyticsFixture();

    expect(analytics()->highRiskActions(3, 5))->toBe([
        'labels' => ['Deleted', 'Failed Login'],
        'values' => [2, 1],
    ]);
});

it('ranks top users, skipping only NULL causers', function () {
    seedAnalyticsFixture();

    // Unlike the overview, top users has only ever skipped NULL causers, so the
    // empty causer type ranks under the fallback label.
    expect(analytics()->topUsers(3, 5))->toBe([
        'labels' => ['Alice', 'Bob', ' #7'],
        'values' => [4, 2, 1],
    ]);
});

it('looks up causer names only for the users it returns', function () {
    seedAnalyticsFixture();

    $lookups = countRetrieved(TestUser::class, function (): void {
        expect(analytics()->topUsers(3, 1))->toBe(['labels' => ['Alice'], 'values' => [4]]);
    });

    expect($lookups)->toBe(1);
});

it('aggregates in the database without hydrating activity models', function (string $method, array $arguments) {
    seedAnalyticsFixture();

    $hydrated = countRetrieved(ActivitylogCompat::activityModel(), function () use ($method, $arguments): void {
        analytics()->{$method}(...$arguments);
    });

    expect($hydrated)->toBe(0);
})->with([
    'overview' => ['overview', [3]],
    'trend' => ['trend', [3]],
    'top events' => ['topEvents', [3, 5]],
    'high risk actions' => ['highRiskActions', [3, 5]],
    'top users' => ['topUsers', [3, 5]],
]);
