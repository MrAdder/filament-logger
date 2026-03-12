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
        ->assertSuccessful();

    expect(Activity::query()->count())->toBe(1);
});
