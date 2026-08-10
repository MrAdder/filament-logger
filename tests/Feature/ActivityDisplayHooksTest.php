<?php

use Filament\Tables\Columns\TextColumn;
use MrAdder\FilamentLogger\Resources\ActivityResource;
use MrAdder\FilamentLogger\Support\ActivityDisplay;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity as ActivityModel;

function displayPageClass(): string
{
    return (new ReflectionMethod(ActivityResource::class, 'getListActivitiesPage'))->invoke(null);
}

function callProtected(object|string $target, string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionMethod(is_string($target) ? $target : $target::class, $method);

    return $reflection->invoke(is_string($target) ? null : $target, ...$arguments);
}

function activityWithSubjectAndCauser(): ActivityModel
{
    $record = TestRecord::create(['name' => 'Widget']);
    $user = TestUser::create(['name' => 'Dana', 'email' => 'dana@example.test']);

    return ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Widget Updated',
        'event' => 'Updated',
        'subject_type' => $record::class,
        'subject_id' => $record->getKey(),
        'causer_type' => $user::class,
        'causer_id' => $user->getKey(),
    ]);
}

afterEach(function () {
    ActivityDisplay::flush();
});

it('uses the built-in subject label with no hook registered', function () {
    $activity = activityWithSubjectAndCauser();

    $label = callProtected(ActivityResource::class, 'formatSubjectState', $activity->subject_type, $activity);

    expect($label)->toBe('Test Record # '.$activity->subject_id);
});

it('lets an application customise the subject label', function () {
    $activity = activityWithSubjectAndCauser();

    ActivityDisplay::subjectLabelUsing(
        fn (?string $type, mixed $id, $record): ?string => $type === TestRecord::class ? "Widget {$id}" : null,
    );

    $label = callProtected(ActivityResource::class, 'formatSubjectState', $activity->subject_type, $activity);

    expect($label)->toBe('Widget '.$activity->subject_id);
});

it('falls back to the built-in subject label when the hook returns null', function () {
    $activity = activityWithSubjectAndCauser();

    ActivityDisplay::subjectLabelUsing(fn (): ?string => null);

    $label = callProtected(ActivityResource::class, 'formatSubjectState', $activity->subject_type, $activity);

    expect($label)->toBe('Test Record # '.$activity->subject_id);
});

it('lets an application customise the causer label', function () {
    $activity = activityWithSubjectAndCauser();

    ActivityDisplay::causerLabelUsing(
        fn ($causer, $record): ?string => $causer === null ? 'System' : strtoupper($causer->name),
    );

    $label = callProtected(ActivityResource::class, 'formatCauserState', 'Dana', $activity);

    expect($label)->toBe('DANA');
});

it('gives the causer hook a null causer for anonymous activity', function () {
    $activity = ActivityModel::create([
        'log_name' => 'Access',
        'description' => 'Failed login',
        'event' => 'Failed Login',
    ]);

    ActivityDisplay::causerLabelUsing(fn ($causer): ?string => $causer === null ? 'System' : 'Someone');

    expect(callProtected(ActivityResource::class, 'formatCauserState', null, $activity))->toBe('System');
});

it('supports a user model without a name column through the causer hook', function () {
    $activity = activityWithSubjectAndCauser();

    ActivityDisplay::causerLabelUsing(fn ($causer): ?string => $causer?->email);

    expect(callProtected(ActivityResource::class, 'formatCauserState', null, $activity))
        ->toBe('dana@example.test');
});

it('lets an application add a table column', function () {
    ActivityDisplay::tableColumnsUsing(function (array $columns): array {
        $columns[] = TextColumn::make('batch_uuid')->label('Batch');

        return $columns;
    });

    $columns = callProtected(ActivityResource::class, 'getTableColumns');
    $names = array_map(fn ($column): string => $column->getName(), $columns);

    expect($names)->toContain('batch_uuid');
});

it('lets an application remove a table column', function () {
    ActivityDisplay::tableColumnsUsing(
        fn (array $columns): array => array_values(array_filter(
            $columns,
            fn ($column): bool => $column->getName() !== 'description',
        )),
    );

    $names = array_map(fn ($column): string => $column->getName(), callProtected(ActivityResource::class, 'getTableColumns'));

    expect($names)->not->toContain('description')
        ->and($names)->toContain('event');
});

it('lets an application replace the table filters', function () {
    ActivityDisplay::filtersUsing(fn (array $filters): array => []);

    expect(callProtected(ActivityResource::class, 'getTableFilters'))->toBe([]);
});

it('lets an application customise the infolist entries', function () {
    ActivityDisplay::infolistEntriesUsing(
        fn (array $entries): array => array_values(array_filter(
            $entries,
            fn ($entry): bool => $entry->getName() !== 'description',
        )),
    );

    $names = array_map(fn ($entry): string => $entry->getName(), callProtected(ActivityResource::class, 'getInfolistEntries'));

    expect($names)->not->toContain('description');
});

it('lets an application remove review tabs', function () {
    $page = new (displayPageClass());

    expect(callProtected($page, 'getTabs'))->not->toBeEmpty();

    ActivityDisplay::tabsUsing(fn (array $tabs): array => []);

    expect(callProtected($page, 'getTabs'))->toBe([]);
});

it('lets an application customise the dashboard widgets', function () {
    config()->set('filament-logger.dashboard.enabled', true);

    $page = new (displayPageClass());

    expect(callProtected($page, 'getHeaderWidgets'))->toHaveCount(5);

    ActivityDisplay::widgetsUsing(fn (array $widgets): array => array_slice($widgets, 0, 1));

    expect(callProtected($page, 'getHeaderWidgets'))->toHaveCount(1);
});

it('ignores a hook that does not return an array', function () {
    ActivityDisplay::tableColumnsUsing(fn (array $columns): mixed => 'not an array');

    expect(callProtected(ActivityResource::class, 'getTableColumns'))->not->toBeEmpty();
});

it('restores default behaviour after flush', function () {
    ActivityDisplay::filtersUsing(fn (array $filters): array => []);
    ActivityDisplay::flush();

    expect(callProtected(ActivityResource::class, 'getTableFilters'))->not->toBeEmpty();
});
