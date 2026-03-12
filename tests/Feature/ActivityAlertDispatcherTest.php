<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Notifications\SensitiveActivityAlertNotification;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;

it('sends alerts for matching sensitive activity rules', function () {
    config()->set('filament-logger.alerts.enabled', true);
    config()->set('filament-logger.alerts.mail.to', ['security@example.com']);
    config()->set('filament-logger.alerts.slack.webhook_url', 'https://hooks.slack.test/logger');
    config()->set('filament-logger.alerts.discord.webhook_url', 'https://discord.test/logger');
    config()->set('filament-logger.alerts.rules', [
        'destructive_activity' => [
            'enabled' => true,
            'channels' => ['mail', 'slack', 'discord'],
            'events' => ['Deleted'],
        ],
    ]);

    Notification::fake();
    Http::fake();

    $user = TestUser::query()->create(['name' => 'Morgan']);
    $record = TestRecord::query()->create(['name' => 'Flagged']);

    app(FilamentLogger::class)->log(
        event: 'Deleted',
        description: 'Record deleted during review',
        logName: 'Resource',
        causer: $user,
        subject: $record,
    );

    Notification::assertSentOnDemand(SensitiveActivityAlertNotification::class);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.test/logger');
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/logger');
});

it('alerts once when a failed login spike reaches the configured threshold', function () {
    config()->set('filament-logger.alerts.enabled', true);
    config()->set('filament-logger.alerts.mail.to', ['security@example.com']);
    config()->set('filament-logger.alerts.default_channels', ['mail']);
    config()->set('filament-logger.alerts.rules', [
        'failed_login_spike' => [
            'enabled' => true,
            'type' => 'threshold',
            'channels' => ['mail'],
            'log_names' => ['Access'],
            'events' => ['Failed Login'],
            'threshold' => 5,
            'window_minutes' => 10,
        ],
    ]);

    Notification::fake();

    $loggedAt = now()->subMinutes(2);

    foreach (range(1, 6) as $attempt) {
        app(FilamentLogger::class)->log(
            event: 'Failed Login',
            description: "Failed login attempt {$attempt}",
            logName: 'Access',
            anonymous: true,
            createdAt: $loggedAt->copy()->addSeconds($attempt),
        );
    }

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 1);
});
