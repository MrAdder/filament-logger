<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Notifications\SensitiveActivityAlertNotification;
use MrAdder\FilamentLogger\Support\ActivityAlertMessage;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use Spatie\Activitylog\Models\Activity as ActivityModel;

const GENERIC_WEBHOOK = 'https://example.test/hooks/audit';

function logActivity(string $event = 'Deleted', string $description = 'Record deleted'): void
{
    $record = TestRecord::create(['name' => 'Doomed']);

    app(FilamentLogger::class)->log(
        event: $event,
        description: $description,
        options: ['logName' => 'Resource', 'subject' => $record, 'anonymous' => true],
    );
}

function alertsConfig(array $rules, array $extra = []): void
{
    config()->set(array_merge([
        'filament-logger.alerts.enabled' => true,
        'filament-logger.alerts.cache_store' => 'array',
        'filament-logger.alerts.rules' => $rules,
    ], $extra));
}

it('renders a per-rule title template', function () {
    $activity = ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Record deleted',
        'event' => 'Deleted',
        'properties' => ['risk' => 'high'],
    ]);

    $message = ActivityAlertMessage::for(
        'destructive_activity',
        ['title' => '[:risk] :rule on :log_name'],
        $activity,
        'high',
    );

    expect($message->title)->toBe('[high] Destructive Activity on Resource');
});

it('renders a per-rule message template', function () {
    $activity = ActivityModel::create([
        'log_name' => 'Access',
        'description' => 'Failed login',
        'event' => 'Failed Login',
    ]);

    $message = ActivityAlertMessage::for(
        'spike',
        ['message' => ':count attempts (threshold :threshold) - :event'],
        $activity,
        'high',
        count: 7,
    );

    expect($message->body)->toBe('7 attempts (threshold -) - Failed Login');
});

it('falls back to the built-in wording when no template is given', function () {
    $activity = ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Record deleted',
        'event' => 'Deleted',
    ]);

    $message = ActivityAlertMessage::for('destructive_activity', [], $activity, 'high');

    expect($message->title)->toBe('Destructive Activity')
        ->and($message->body)->toContain('Record deleted')
        ->and($message->body)->toContain('Event: Deleted')
        ->and($message->body)->toContain('Risk: high');
});

it('still honours the legacy label key', function () {
    $activity = ActivityModel::create(['log_name' => 'Resource', 'description' => 'x', 'event' => 'Deleted']);

    $message = ActivityAlertMessage::for('rule', ['label' => 'Legacy title'], $activity, null);

    expect($message->title)->toBe('Legacy title');
});

it('delivers alerts through the generic webhook channel', function () {
    alertsConfig([
        'destructive_activity' => [
            'enabled' => true,
            'channels' => ['webhook'],
            'events' => ['Deleted'],
        ],
    ], [
        'filament-logger.alerts.webhook.url' => GENERIC_WEBHOOK,
        'filament-logger.alerts.webhook.headers' => ['X-Audit-Token' => 'secret-token'],
    ]);

    Http::fake([GENERIC_WEBHOOK => Http::response('', 200)]);

    logActivity();

    Http::assertSent(function ($request): bool {
        return $request->url() === GENERIC_WEBHOOK
            && $request->hasHeader('X-Audit-Token', 'secret-token')
            && $request['title'] === 'Destructive Activity'
            && $request['activity']['event'] === 'Deleted'
            && $request['activity']['risk'] === 'high';
    });
});

it('lets a rule point a channel at its own endpoint', function () {
    alertsConfig([
        'destructive_activity' => [
            'enabled' => true,
            'channels' => ['webhook'],
            'events' => ['Deleted'],
            'webhook_url' => 'https://example.test/hooks/override',
        ],
    ], [
        'filament-logger.alerts.webhook.url' => GENERIC_WEBHOOK,
    ]);

    Http::fake();

    logActivity();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.test/hooks/override');
    Http::assertNotSent(fn ($request): bool => $request->url() === GENERIC_WEBHOOK);
});

it('leaves slack and discord payload shapes unchanged', function () {
    alertsConfig([
        'destructive_activity' => [
            'enabled' => true,
            'channels' => ['slack', 'discord'],
            'events' => ['Deleted'],
        ],
    ], [
        'filament-logger.alerts.slack.webhook_url' => 'https://hooks.slack.test/a',
        'filament-logger.alerts.discord.webhook_url' => 'https://discord.test/b',
    ]);

    Http::fake();

    logActivity();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.slack.test/a'
        && is_string($request['text']));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.test/b'
        && is_string($request['content']));
});

it('uses the rendered template in the alert mail', function () {
    alertsConfig([
        'destructive_activity' => [
            'enabled' => true,
            'channels' => ['mail'],
            'events' => ['Deleted'],
            'title' => 'Custom subject for :log_name',
            'message' => 'Line one for :event',
        ],
    ], [
        'filament-logger.alerts.mail.to' => ['security@example.test'],
    ]);

    Notification::fake();

    logActivity();

    Notification::assertSentOnDemand(
        SensitiveActivityAlertNotification::class,
        function (SensitiveActivityAlertNotification $notification): bool {
            $mail = $notification->toMail(new stdClass);

            return $mail->subject === 'Custom subject for Resource'
                && in_array('Line one for Deleted', $mail->introLines, true);
        },
    );
});
