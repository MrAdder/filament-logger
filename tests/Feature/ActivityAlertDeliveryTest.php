<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Jobs\SendActivityAlertWebhook;
use MrAdder\FilamentLogger\Notifications\SensitiveActivityAlertNotification;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;

const WEBHOOK_URL = 'https://hooks.example.test/services/T000/B000/XXXX';

function configureWebhookAlerts(array $overrides = []): void
{
    config()->set(array_merge([
        'filament-logger.alerts.enabled' => true,
        'filament-logger.alerts.cache_store' => 'array',
        'filament-logger.alerts.default_channels' => ['slack'],
        'filament-logger.alerts.slack.webhook_url' => WEBHOOK_URL,
        'filament-logger.alerts.rules' => [
            'destructive_activity' => [
                'enabled' => true,
                'channels' => ['slack'],
                'events' => ['Deleted'],
                'cooldown_minutes' => 10,
            ],
        ],
    ], $overrides));
}

function logDeletedRecord(string $description = 'Record deleted'): void
{
    $record = TestRecord::create(['name' => 'Doomed']);

    app(FilamentLogger::class)->log(
        event: 'Deleted',
        description: $description,
        options: ['logName' => 'Resource', 'subject' => $record, 'anonymous' => true],
    );
}

afterEach(function () {
    Cache::store('array')->flush();
});

it('treats a rejected webhook as a failed delivery', function () {
    configureWebhookAlerts();

    Http::fake([WEBHOOK_URL => Http::response('no_service', 404)]);

    logDeletedRecord();

    Http::assertSentCount(1);

    // A failed delivery must release the cooldown so the next activity retries
    // rather than being silently swallowed for ten minutes.
    logDeletedRecord('Second delete');

    Http::assertSentCount(2);
});

it('holds the cooldown after a successful webhook', function () {
    configureWebhookAlerts();

    Http::fake([WEBHOOK_URL => Http::response('ok', 200)]);

    logDeletedRecord();
    logDeletedRecord('Second delete');

    Http::assertSentCount(1);
});

it('dispatches webhooks to the queue when queueing is enabled', function () {
    configureWebhookAlerts(['filament-logger.alerts.queue' => true]);

    Bus::fake();
    Http::fake();

    logDeletedRecord();

    // Nothing may touch the network on the request path once queueing is on.
    Http::assertNothingSent();
    Bus::assertDispatched(SendActivityAlertWebhook::class, function (SendActivityAlertWebhook $job): bool {
        return $job->url === WEBHOOK_URL && isset($job->payload['text']);
    });
});

it('respects the configured queue connection and name', function () {
    configureWebhookAlerts([
        'filament-logger.alerts.queue' => true,
        'filament-logger.alerts.queue_connection' => 'redis',
        'filament-logger.alerts.queue_name' => 'audit-alerts',
    ]);

    Bus::fake();

    logDeletedRecord();

    Bus::assertDispatched(SendActivityAlertWebhook::class, function (SendActivityAlertWebhook $job): bool {
        return $job->connection === 'redis' && $job->queue === 'audit-alerts';
    });
});

it('sends mail alerts synchronously by default', function () {
    config()->set([
        'filament-logger.alerts.enabled' => true,
        'filament-logger.alerts.cache_store' => 'array',
        'filament-logger.alerts.default_channels' => ['mail'],
        'filament-logger.alerts.mail.to' => ['security@example.test'],
        'filament-logger.alerts.rules' => [
            'destructive_activity' => ['enabled' => true, 'channels' => ['mail'], 'events' => ['Deleted']],
        ],
    ]);

    Notification::fake();

    logDeletedRecord();

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 1);
});

it('alerts on a threshold overshoot instead of requiring an exact count', function () {
    config()->set([
        'filament-logger.alerts.enabled' => true,
        'filament-logger.alerts.cache_store' => 'array',
        'filament-logger.alerts.default_channels' => ['mail'],
        'filament-logger.alerts.mail.to' => ['security@example.test'],
        'filament-logger.alerts.rules' => [
            'spike' => [
                'enabled' => true,
                'type' => 'threshold',
                'channels' => ['mail'],
                'log_names' => ['Access'],
                'events' => ['Failed Login'],
                'threshold' => 3,
                'window_minutes' => 10,
            ],
        ],
    ]);

    Notification::fake();

    // Seed the window past the threshold before any alert can fire, which is
    // what a burst of concurrent failures looks like. An exact-count check
    // would never match again from here.
    foreach (range(1, 5) as $attempt) {
        app(FilamentLogger::class)->log(
            event: 'Failed Login',
            description: "Failed login {$attempt}",
            options: ['logName' => 'Access', 'anonymous' => true],
        );
    }

    Notification::assertSentOnDemandTimes(SensitiveActivityAlertNotification::class, 1);
});
