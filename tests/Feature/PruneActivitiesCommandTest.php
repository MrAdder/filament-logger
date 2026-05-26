<?php

use Spatie\Activitylog\Models\Activity;

function createPruneActivity(string $logName, string $description, string $event, int $daysAgo): void
{
    Activity::query()->create([
        'log_name' => $logName,
        'description' => $description,
        'event' => $event,
        'created_at' => now()->subDays($daysAgo),
        'updated_at' => now()->subDays($daysAgo),
    ]);
}

function pruneActivitiesCommand(\MrAdder\FilamentLogger\Tests\TestCase $test, array $commandOptions = [])
{
    return $test->artisan('filament-logger:prune', $commandOptions);
}

function expectPruneSummary($command, string $scopeSummary, string $breakdownSummary, string $resultSummary)
{
    return $command
        ->expectsOutputToContain($scopeSummary)
        ->expectsOutputToContain($breakdownSummary)
        ->expectsOutputToContain($resultSummary);
}

it('prunes only matching old activity records', function () {
    config()->set('filament-logger.pruning.days', 30);
    config()->set('filament-logger.pruning.only', ['Access']);

    createPruneActivity('Access', 'Old access record', 'Login', 40);
    createPruneActivity('Notification', 'Old notification record', 'Notification Sent', 40);
    createPruneActivity('Access', 'Recent access record', 'Login', 5);

    expectPruneSummary(
        pruneActivitiesCommand($this),
        'Pruning scope: older than 30 day(s); matching log names: Access; excluded log names: none.',
        'Matching records by log name: Access (1).',
        'Pruned 1 activity record(s).',
    )
        ->assertSuccessful();

    expect(Activity::query()->orderBy('id')->pluck('description')->all())
        ->toBe([
            'Old notification record',
            'Recent access record',
        ]);
});

it('supports dry-run pruning', function () {
    config()->set('filament-logger.pruning.days', 30);

    createPruneActivity('Access', 'Old access record', 'Login', 40);

    expectPruneSummary(
        pruneActivitiesCommand($this, ['--dry-run' => true]),
        'Pruning scope: older than 30 day(s); matching log names: all log names; excluded log names: none.',
        'Matching records by log name: Access (1).',
        'Dry run: 1 activity record(s) would be pruned.',
    )
        ->assertSuccessful();

    expect(Activity::query()->count())->toBe(1);
});

it('reports when nothing matches pruning rules', function () {
    config()->set('filament-logger.pruning.days', 30);
    config()->set('filament-logger.pruning.only', ['Access']);
    config()->set('filament-logger.pruning.except', ['Notification']);

    createPruneActivity('Notification', 'Old notification record', 'Notification Sent', 40);

    expectPruneSummary(
        pruneActivitiesCommand($this),
        'Pruning scope: older than 30 day(s); matching log names: Access; excluded log names: Notification.',
        'Matching records by log name: none.',
        'No activity records matched the pruning rules.',
    )
        ->assertSuccessful();

    expect(Activity::query()->count())->toBe(1);
});

it('reports real prune summaries with excluded log names', function () {
    config()->set('filament-logger.pruning.days', 30);
    config()->set('filament-logger.pruning.except', ['Notification']);

    createPruneActivity('Access', 'Old access record', 'Login', 40);
    createPruneActivity('Notification', 'Old notification record', 'Notification Sent', 40);

    expectPruneSummary(
        pruneActivitiesCommand($this),
        'Pruning scope: older than 30 day(s); matching log names: all log names; excluded log names: Notification.',
        'Matching records by log name: Access (1).',
        'Pruned 1 activity record(s).',
    )
        ->assertSuccessful();

    expect(Activity::query()->orderBy('id')->pluck('description')->all())
        ->toBe([
            'Old notification record',
        ]);
});
