<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Notifications\SensitiveActivityAlertNotification;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;

function configureAlerts(array $rules, array $config = []): void
{
    config()->set('filament-logger.alerts.enabled', true);
    config()->set('filament-logger.alerts.cache_store', 'array');
    config()->set('filament-logger.alerts.mail.to', ['security@example.com']);
    config()->set('filament-logger.alerts.rules', $rules);

    foreach ($config as $key => $value) {
        config()->set($key, $value);
    }
}

function createAlertActor(): TestUser
{
    return TestUser::query()->create(['name' => 'Morgan']);
}

function logDeletedActivity(TestUser $user, string $recordName, string $description): void
{
    $record = TestRecord::query()->create(['name' => $recordName]);

    app(FilamentLogger::class)->log(
        event: 'Deleted',
        description: $description,
        options: [
            'logName' => 'Resource',
            'causer' => $user,
            'subject' => $record,
        ],
    );
}

function logFailedLoginAttempts(string $descriptionPrefix, int $attempts, Carbon $loggedAt): void
{
    foreach (range(1, $attempts) as $attempt) {
        app(FilamentLogger::class)->log(
            event: 'Failed Login',
            description: "{$descriptionPrefix} {$attempt}",
            options: [
                'logName' => 'Access',
                'anonymous' => true,
                'createdAt' => $loggedAt->copy()->addSeconds($attempt),
            ],
        );
    }
}

afterEach(function () {
    Carbon::setTestNow();
    Cache::store('array')->flush();
});

it('sends alerts for matching sensitive activity rules', function () {
    configureAlerts([
        'destructive_activity' => [
            'enabled' => true,
            'channels' => ['mail', 'slack', 'discord'],
            'events' => ['Deleted'],
        ],
    ], [
        'filament-logger.alerts.slack.webhook_url' => 'https://hooks.slack.test/logger',
        'filament-logger.alerts.discord.webhook_url' => 'https://discord.test/logger',
    ]);

    Notification::fake();
    Http::fake();

    logDeletedActivity(createAlertActor(), 'Flagged', 'Record deleted during review');

    Notification::assertSentOnDemand(SensitiveActivityAlertNotification::class);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.test/logger');
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/logger');
});

it('alerts once when a failed login spike reaches the configured threshold', function () {
    configureAlerts([
        'failed_login_spike' => [
            'enabled' => true,
            'type' => 'threshold',
            'channels' => ['mail'],
            'log_names' => ['Access'],
            'events' => ['Failed Login'],
            'threshold' => 5,
            'window_minutes' => 10,
        ],
    ], [
        'filament-logger.alerts.default_channels' => ['mail'],
    ]);

    Notification::fake();

    logFailedLoginAttempts('Failed login attempt', 6, now()->subMinutes(2));

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 1);
});

it('suppresses duplicate alerts inside the cooldown window', function () {
    Carbon::setTestNow('2026-03-12 12:00:00');

    configureAlerts([
        'destructive_activity' => [
            'enabled' => true,
            'channels' => ['mail'],
            'events' => ['Deleted'],
            'cooldown_minutes' => 10,
        ],
    ]);

    Notification::fake();

    $user = createAlertActor();

    foreach (range(1, 2) as $attempt) {
        logDeletedActivity($user, "Flagged {$attempt}", 'Record deleted during review');
    }

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 1);
});

it('allows alerts again after the cooldown expires', function () {
    Carbon::setTestNow('2026-03-12 12:00:00');

    configureAlerts([
        'destructive_activity' => [
            'enabled' => true,
            'channels' => ['mail'],
            'events' => ['Deleted'],
            'cooldown_minutes' => 5,
        ],
    ]);

    Notification::fake();

    $user = createAlertActor();

    logDeletedActivity($user, 'First', 'First delete alert');

    Carbon::setTestNow(now()->addMinutes(6));

    logDeletedActivity($user, 'Second', 'Second delete alert');

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 2);
});

it('suppresses repeated threshold alerts during the cooldown window', function () {
    Carbon::setTestNow('2026-03-12 12:00:00');

    configureAlerts([
        'failed_login_spike' => [
            'enabled' => true,
            'type' => 'threshold',
            'channels' => ['mail'],
            'log_names' => ['Access'],
            'events' => ['Failed Login'],
            'threshold' => 5,
            'window_minutes' => 1,
            'cooldown_minutes' => 10,
        ],
    ], [
        'filament-logger.alerts.default_channels' => ['mail'],
    ]);

    Notification::fake();

    logFailedLoginAttempts('First spike attempt', 5, now());

    Carbon::setTestNow(now()->addMinutes(2));

    logFailedLoginAttempts('Second spike attempt', 5, now());

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 1);

    Carbon::setTestNow(now()->addMinutes(11));

    logFailedLoginAttempts('Third spike attempt', 5, now());

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 2);
});
