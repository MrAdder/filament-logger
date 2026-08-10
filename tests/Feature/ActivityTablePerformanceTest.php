<?php

use Illuminate\Support\Facades\DB;
use MrAdder\FilamentLogger\Resources\ActivityResource;
use MrAdder\FilamentLogger\Support\ActivityDisplay;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * The table formats every visible row, so anything that touches a relation in a
 * formatter costs one query per row unless it is guarded or eager loaded.
 */
function seedActivityRowsWithCauser(int $count = 10): TestUser
{
    $user = TestUser::create(['name' => 'Dana', 'email' => 'dana@example.test']);

    foreach (range(1, $count) as $index) {
        ActivityModel::create([
            'log_name' => 'Resource',
            'description' => "Activity {$index}",
            'event' => 'Updated',
            'causer_type' => $user::class,
            'causer_id' => $user->getKey(),
        ]);
    }

    return $user;
}

function queriesWhileFormattingCauser(iterable $rows): int
{
    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $method = new ReflectionMethod(ActivityResource::class, 'formatCauserState');

    foreach ($rows as $row) {
        $method->invoke(null, 'Dana', $row);
    }

    return $queries;
}

afterEach(function () {
    ActivityDisplay::flush();
});

it('runs no queries formatting the causer column without a hook', function () {
    seedActivityRowsWithCauser();

    // Deliberately not eager loaded, which is the worst case.
    $rows = ActivityModel::query()->get();

    expect(queriesWhileFormattingCauser($rows))->toBe(0);
});

it('runs no extra queries formatting the causer column with a hook and eager loading', function () {
    seedActivityRowsWithCauser();

    ActivityDisplay::causerLabelUsing(fn ($causer): ?string => $causer?->email);

    $rows = ActivityResource::getEloquentQuery()->get();

    expect(queriesWhileFormattingCauser($rows))->toBe(0);
});

it('eager loads the causer on the resource query', function () {
    seedActivityRowsWithCauser(3);

    $rows = ActivityResource::getEloquentQuery()->get();

    expect($rows)->not->toBeEmpty()
        ->and($rows->first()->relationLoaded('causer'))->toBeTrue();
});

it('still applies a causer hook when the relation is loaded', function () {
    seedActivityRowsWithCauser(1);

    ActivityDisplay::causerLabelUsing(fn ($causer): ?string => $causer?->email);

    $row = ActivityResource::getEloquentQuery()->first();
    $label = (new ReflectionMethod(ActivityResource::class, 'formatCauserState'))->invoke(null, 'Dana', $row);

    expect($label)->toBe('dana@example.test');
});
