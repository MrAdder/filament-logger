<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Notifications\SensitiveActivityAlertNotification;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;

afterEach(function () {
    Carbon::setTestNow();
    Cache::store('array')->flush();
});

it('sends alerts for matching sensitive activity rules', function () {
    config()->set('filament-logger.alerts.enabled', true);
    config()->set('filament-logger.alerts.cache_store', 'array');
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
        options: [
            'logName' => 'Resource',
            'causer' => $user,
            'subject' => $record,
        ],
    );

    Notification::assertSentOnDemand(SensitiveActivityAlertNotification::class);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.test/logger');
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/logger');
});

it('alerts once when a failed login spike reaches the configured threshold', function () {
    config()->set('filament-logger.alerts.enabled', true);
    config()->set('filament-logger.alerts.cache_store', 'array');
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
            options: [
                'logName' => 'Access',
                'anonymous' => true,
                'createdAt' => $loggedAt->copy()->addSeconds($attempt),
            ],
        );
    }

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 1);
});

it('suppresses duplicate alerts inside the cooldown window', function () {
    Carbon::setTestNow('2026-03-12 12:00:00');

    config()->set('filament-logger.alerts.enabled', true);
    config()->set('filament-logger.alerts.cache_store', 'array');
    config()->set('filament-logger.alerts.mail.to', ['security@example.com']);
    config()->set('filament-logger.alerts.rules', [
        'destructive_activity' => [
            'enabled' => true,
            'channels' => ['mail'],
            'events' => ['Deleted'],
            'cooldown_minutes' => 10,
        ],
    ]);

    Notification::fake();

    $user = TestUser::query()->create(['name' => 'Morgan']);

    foreach (range(1, 2) as $attempt) {
        $record = TestRecord::query()->create(['name' => "Flagged {$attempt}"]);

        app(FilamentLogger::class)->log(
            event: 'Deleted',
            description: 'Record deleted during review',
            options: [
                'logName' => 'Resource',
                'causer' => $user,
                'subject' => $record,
            ],
        );
    }

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 1);
});

it('allows alerts again after the cooldown expires', function () {
    Carbon::setTestNow('2026-03-12 12:00:00');

    config()->set('filament-logger.alerts.enabled', true);
    config()->set('filament-logger.alerts.cache_store', 'array');
    config()->set('filament-logger.alerts.mail.to', ['security@example.com']);
    config()->set('filament-logger.alerts.rules', [
        'destructive_activity' => [
            'enabled' => true,
            'channels' => ['mail'],
            'events' => ['Deleted'],
            'cooldown_minutes' => 5,
        ],
    ]);

    Notification::fake();

    $user = TestUser::query()->create(['name' => 'Morgan']);

    $firstRecord = TestRecord::query()->create(['name' => 'First']);

    app(FilamentLogger::class)->log(
        event: 'Deleted',
        description: 'First delete alert',
        options: [
            'logName' => 'Resource',
            'causer' => $user,
            'subject' => $firstRecord,
        ],
    );

    Carbon::setTestNow(now()->addMinutes(6));

    $secondRecord = TestRecord::query()->create(['name' => 'Second']);

    app(FilamentLogger::class)->log(
        event: 'Deleted',
        description: 'Second delete alert',
        options: [
            'logName' => 'Resource',
            'causer' => $user,
            'subject' => $secondRecord,
        ],
    );

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 2);
});

it('suppresses repeated threshold alerts during the cooldown window', function () {
    Carbon::setTestNow('2026-03-12 12:00:00');

    config()->set('filament-logger.alerts.enabled', true);
    config()->set('filament-logger.alerts.cache_store', 'array');
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
            'window_minutes' => 1,
            'cooldown_minutes' => 10,
        ],
    ]);

    Notification::fake();

    foreach (range(1, 5) as $attempt) {
        app(FilamentLogger::class)->log(
            event: 'Failed Login',
            description: "First spike attempt {$attempt}",
            options: [
                'logName' => 'Access',
                'anonymous' => true,
                'createdAt' => now()->copy()->addSeconds($attempt),
            ],
        );
    }

    Carbon::setTestNow(now()->addMinutes(2));

    foreach (range(1, 5) as $attempt) {
        app(FilamentLogger::class)->log(
            event: 'Failed Login',
            description: "Second spike attempt {$attempt}",
            options: [
                'logName' => 'Access',
                'anonymous' => true,
                'createdAt' => now()->copy()->addSeconds($attempt),
            ],
        );
    }

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 1);

    Carbon::setTestNow(now()->addMinutes(11));

    foreach (range(1, 5) as $attempt) {
        app(FilamentLogger::class)->log(
            event: 'Failed Login',
            description: "Third spike attempt {$attempt}",
            options: [
                'logName' => 'Access',
                'anonymous' => true,
                'createdAt' => now()->copy()->addSeconds($attempt),
            ],
        );
    }

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 2);
});
