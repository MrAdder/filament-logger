<?php

use Illuminate\Database\Eloquent\Builder;
use MrAdder\FilamentLogger\Support\ActivityExportCriteria;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * These exercise the shared search implementation the resource filter, the
 * direct export, and the queued export all run through, rather than a copy of
 * the query written for the test.
 */
function searchActivities(string $term): array
{
    /** @var Builder<ActivityModel> $query */
    $query = ActivityModel::query();

    return ActivityExportCriteria::applySearch($query, $term)->pluck('id')->all();
}

function seedSearchableActivities(): array
{
    $user = TestUser::create(['name' => 'Dana Scully', 'email' => 'dana@example.test']);

    $email = ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Updated email address for user',
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'event' => 'Updated',
        'properties' => ['tags' => ['email', 'user']],
    ]);

    $order = ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Deleted order #42',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => 42,
        'event' => 'Deleted',
        'properties' => ['tags' => ['order', 'refundable-xyz']],
    ]);

    $byUser = ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Archived report',
        'event' => 'Updated',
        'causer_type' => $user::class,
        'causer_id' => $user->getKey(),
    ]);

    return ['email' => $email, 'order' => $order, 'byUser' => $byUser];
}

it('matches on the description', function () {
    ['email' => $email, 'order' => $order] = seedSearchableActivities();

    $results = searchActivities('email address');

    expect($results)->toContain($email->id)
        ->and($results)->not->toContain($order->id);
});

it('matches on the subject type', function () {
    ['order' => $order, 'email' => $email] = seedSearchableActivities();

    $results = searchActivities('Order');

    expect($results)->toContain($order->id)
        ->and($results)->not->toContain($email->id);
});

it('matches on the causer name', function () {
    ['byUser' => $byUser, 'order' => $order] = seedSearchableActivities();

    $results = searchActivities('Scully');

    expect($results)->toContain($byUser->id)
        ->and($results)->not->toContain($order->id);
});

it('matches inside the properties payload', function () {
    ['order' => $order, 'byUser' => $byUser] = seedSearchableActivities();

    // Appears only in the tags, so a match proves the JSON column was scanned.
    $results = searchActivities('refundable-xyz');

    expect($results)->toContain($order->id)
        ->and($results)->not->toContain($byUser->id);
});

it('skips the properties scan when it is disabled', function () {
    config()->set('filament-logger.search.include_properties', false);

    ['order' => $order] = seedSearchableActivities();

    expect(searchActivities('refundable-xyz'))->not->toContain($order->id);
});

it('returns everything for an empty term', function () {
    seedSearchableActivities();

    expect(searchActivities(''))->toHaveCount(3);
});

it('is reachable through the resource filter', function () {
    ['email' => $email, 'order' => $order] = seedSearchableActivities();

    $filters = (new ReflectionMethod(
        config('filament-logger.activity_resource'),
        'getTableFilters',
    ))->invoke(null);

    $search = collect($filters)->first(fn ($filter): bool => $filter->getName() === 'search');

    expect($search)->not->toBeNull();

    /** @var Builder<ActivityModel> $query */
    $query = ActivityModel::query();

    // Run the filter's own query callback, which is what the table applies.
    $results = $search->apply($query, ['query' => 'email address'])
        ->pluck('id')
        ->all();

    expect($results)->toContain($email->id)
        ->and($results)->not->toContain($order->id);
});
