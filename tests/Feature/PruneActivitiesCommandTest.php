<?php

use Spatie\Activitylog\Models\Activity;

it('prunes only matching old activity records', function () {
    config()->set('filament-logger.pruning.days', 30);
    config()->set('filament-logger.pruning.only', ['Access']);

    Activity::query()->create([
        'log_name' => 'Access',
        'description' => 'Old access record',
        'event' => 'Login',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    Activity::query()->create([
        'log_name' => 'Notification',
        'description' => 'Old notification record',
        'event' => 'Notification Sent',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    Activity::query()->create([
        'log_name' => 'Access',
        'description' => 'Recent access record',
        'event' => 'Login',
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    $this->artisan('filament-logger:prune')
        ->expectsOutputToContain('Pruning scope: older than 30 day(s); matching log names: Access; excluded log names: none.')
        ->expectsOutputToContain('Matching records by log name: Access (1).')
        ->expectsOutputToContain('Pruned 1 activity record(s).')
        ->assertSuccessful();

    expect(Activity::query()->orderBy('id')->pluck('description')->all())
        ->toBe([
            'Old notification record',
            'Recent access record',
        ]);
});

it('supports dry-run pruning', function () {
    config()->set('filament-logger.pruning.days', 30);

    Activity::query()->create([
        'log_name' => 'Access',
        'description' => 'Old access record',
        'event' => 'Login',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    $this->artisan('filament-logger:prune', ['--dry-run' => true])
        ->expectsOutputToContain('Pruning scope: older than 30 day(s); matching log names: all log names; excluded log names: none.')
        ->expectsOutputToContain('Matching records by log name: Access (1).')
        ->expectsOutputToContain('Dry run: 1 activity record(s) would be pruned.')
        ->assertSuccessful();

    expect(Activity::query()->count())->toBe(1);
});

it('reports when nothing matches pruning rules', function () {
    config()->set('filament-logger.pruning.days', 30);
    config()->set('filament-logger.pruning.only', ['Access']);
    config()->set('filament-logger.pruning.except', ['Notification']);

    Activity::query()->create([
        'log_name' => 'Notification',
        'description' => 'Old notification record',
        'event' => 'Notification Sent',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    $this->artisan('filament-logger:prune')
        ->expectsOutputToContain('Pruning scope: older than 30 day(s); matching log names: Access; excluded log names: Notification.')
        ->expectsOutputToContain('Matching records by log name: none.')
        ->expectsOutputToContain('No activity records matched the pruning rules.')
        ->assertSuccessful();

    expect(Activity::query()->count())->toBe(1);
});

it('reports real prune summaries with excluded log names', function () {
    config()->set('filament-logger.pruning.days', 30);
    config()->set('filament-logger.pruning.except', ['Notification']);

    Activity::query()->create([
        'log_name' => 'Access',
        'description' => 'Old access record',
        'event' => 'Login',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    Activity::query()->create([
        'log_name' => 'Notification',
        'description' => 'Old notification record',
        'event' => 'Notification Sent',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    $this->artisan('filament-logger:prune')
        ->expectsOutputToContain('Pruning scope: older than 30 day(s); matching log names: all log names; excluded log names: Notification.')
        ->expectsOutputToContain('Matching records by log name: Access (1).')
        ->expectsOutputToContain('Pruned 1 activity record(s).')
        ->assertSuccessful();

    expect(Activity::query()->orderBy('id')->pluck('description')->all())
        ->toBe([
            'Old notification record',
        ]);
});
