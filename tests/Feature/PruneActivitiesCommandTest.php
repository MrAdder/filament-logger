<?php

use MrAdder\FilamentLogger\Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

const PRUNE_ACCESS_RECORD_DESCRIPTION = 'Old access record';
const PRUNE_NOTIFICATION_RECORD_DESCRIPTION = 'Old notification record';
const PRUNE_ACCESS_BREAKDOWN_SUMMARY = 'Matching records by log name: Access (1).';

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

function setPruneConfig(int $days = 365, array $only = [], array $except = []): void
{
    config()->set('filament-logger.pruning.days', $days);
    config()->set('filament-logger.pruning.only', $only);
    config()->set('filament-logger.pruning.except', $except);
}

function pruneActivitiesCommand(TestCase $test, array $commandOptions = [])
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

function seedOldAccessRecord(string $description, int $daysAgo = 40): void
{
    createPruneActivity('Access', $description, 'Login', $daysAgo);
}

function seedOldNotificationRecord(string $description, int $daysAgo = 40): void
{
    createPruneActivity('Notification', $description, 'Notification Sent', $daysAgo);
}

function assertActivityDescriptions(array $descriptions): void
{
    expect(Activity::query()->orderBy('id')->pluck('description')->all())
        ->toBe($descriptions);
}

function assertActivityCount(int $count): void
{
    expect(Activity::query()->count())->toBe($count);
}

it('prunes only matching old activity records', function () {
    setPruneConfig(30, ['Access']);

    seedOldAccessRecord(PRUNE_ACCESS_RECORD_DESCRIPTION);
    seedOldNotificationRecord(PRUNE_NOTIFICATION_RECORD_DESCRIPTION);
    createPruneActivity('Access', 'Recent access record', 'Login', 5);

    expectPruneSummary(
        pruneActivitiesCommand($this),
        'Pruning scope: older than 30 day(s); matching log names: Access; excluded log names: none.',
        PRUNE_ACCESS_BREAKDOWN_SUMMARY,
        'Pruned 1 activity record(s).',
    )
        ->assertSuccessful();

    assertActivityDescriptions([
        PRUNE_NOTIFICATION_RECORD_DESCRIPTION,
        'Recent access record',
    ]);
});

it('supports dry-run pruning', function () {
    setPruneConfig(30);

    seedOldAccessRecord(PRUNE_ACCESS_RECORD_DESCRIPTION);

    expectPruneSummary(
        pruneActivitiesCommand($this, ['--dry-run' => true]),
        'Pruning scope: older than 30 day(s); matching log names: all log names; excluded log names: none.',
        PRUNE_ACCESS_BREAKDOWN_SUMMARY,
        'Dry run: 1 activity record(s) would be pruned.',
    )
        ->assertSuccessful();

    assertActivityCount(1);
});

it('reports when nothing matches pruning rules', function () {
    setPruneConfig(30, ['Access'], ['Notification']);

    seedOldNotificationRecord(PRUNE_NOTIFICATION_RECORD_DESCRIPTION);

    expectPruneSummary(
        pruneActivitiesCommand($this),
        'Pruning scope: older than 30 day(s); matching log names: Access; excluded log names: Notification.',
        'Matching records by log name: none.',
        'No activity records matched the pruning rules.',
    )
        ->assertSuccessful();

    assertActivityCount(1);
});

it('reports real prune summaries with excluded log names', function () {
    setPruneConfig(30, [], ['Notification']);

    seedOldAccessRecord(PRUNE_ACCESS_RECORD_DESCRIPTION);
    seedOldNotificationRecord(PRUNE_NOTIFICATION_RECORD_DESCRIPTION);

    expectPruneSummary(
        pruneActivitiesCommand($this),
        'Pruning scope: older than 30 day(s); matching log names: all log names; excluded log names: Notification.',
        PRUNE_ACCESS_BREAKDOWN_SUMMARY,
        'Pruned 1 activity record(s).',
    )
        ->assertSuccessful();

    assertActivityDescriptions([
        PRUNE_NOTIFICATION_RECORD_DESCRIPTION,
    ]);
});
