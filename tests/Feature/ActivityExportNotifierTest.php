<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use MrAdder\FilamentLogger\Notifications\ActivityExportFailedNotification;
use MrAdder\FilamentLogger\Notifications\ActivityExportReadyNotification;
use MrAdder\FilamentLogger\Support\ActivityExportNotifier;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;

function notifier(): ActivityExportNotifier
{
    return app(ActivityExportNotifier::class);
}

function auditor(): TestUser
{
    return TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);
}

beforeEach(function () {
    config()->set([
        'filament-logger.exports.enabled' => true,
        'filament-logger.exports.ability' => null,
        'filament-logger.exports.queue.notify' => 'mail',
        'filament-logger.exports.queue.disk' => 'local',
    ]);
});

it('builds a signed download url', function () {
    $url = notifier()->downloadUrl(7, 'filament-logger/exports/7/report.csv');

    expect($url)->toContain('/filament-logger/exports/7/')
        ->and($url)->toContain('signature=')
        ->and($url)->toContain('expires=');
});

it('uses the shared segment when there is no user', function () {
    expect(notifier()->downloadUrl(null, 'filament-logger/exports/shared/report.csv'))
        ->toContain('/exports/shared/');
});

it('returns no url when routes are disabled', function () {
    config()->set('filament-logger.exports.queue.routes', false);

    expect(notifier()->downloadUrl(7, 'filament-logger/exports/7/report.csv'))->toBeNull();
});

it('honours a custom link lifetime', function () {
    config()->set('filament-logger.exports.queue.link_minutes', 5);

    $url = notifier()->downloadUrl(7, 'filament-logger/exports/7/report.csv');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect((int) $query['expires'])->toBeLessThanOrEqual(now()->addMinutes(5)->getTimestamp())
        ->and((int) $query['expires'])->toBeGreaterThan(now()->getTimestamp());
});

it('mails the requesting user when the export is ready', function () {
    Notification::fake();

    $user = auditor();

    notifier()->exportReady($user->getKey(), $user::class, 'local', 'filament-logger/exports/1/a.csv', 42);

    Notification::assertSentTo($user, ActivityExportReadyNotification::class);
});

it('logs instead of notifying when the channel is disabled', function () {
    config()->set('filament-logger.exports.queue.notify', null);

    Notification::fake();
    Log::spy();

    $user = auditor();

    notifier()->exportReady($user->getKey(), $user::class, 'local', 'filament-logger/exports/1/a.csv', 42);

    Notification::assertNothingSent();
    Log::shouldHaveReceived('info')->once();
});

it('logs when the requesting user can no longer be resolved', function () {
    Notification::fake();
    Log::spy();

    notifier()->exportReady(9999, TestUser::class, 'local', 'filament-logger/exports/9999/a.csv', 1);

    Notification::assertNothingSent();
    Log::shouldHaveReceived('info')->once();
});

it('skips notifying a user model that cannot receive notifications', function () {
    Notification::fake();
    Log::spy();

    // TestRecord has no Notifiable trait, standing in for a host app whose user
    // model does not either.
    $record = TestRecord::create(['name' => 'Not notifiable']);

    notifier()->exportReady($record->getKey(), $record::class, 'local', 'filament-logger/exports/1/a.csv', 1);

    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')->once();
});

it('mails a failure to the requesting user', function () {
    Notification::fake();

    $user = auditor();

    notifier()->exportFailed($user->getKey(), $user::class, 'disk full');

    Notification::assertSentTo($user, ActivityExportFailedNotification::class);
});

it('stays silent on failure when notifications are disabled', function () {
    config()->set('filament-logger.exports.queue.notify', null);

    Notification::fake();

    $user = auditor();

    notifier()->exportFailed($user->getKey(), $user::class, 'disk full');

    Notification::assertNothingSent();
});

it('renders the ready mail with a download action', function () {
    $mail = (new ActivityExportReadyNotification('https://example.test/download', 1234))
        ->toMail(new stdClass);

    expect($mail->subject)->toContain('export is ready')
        ->and($mail->actionUrl)->toBe('https://example.test/download')
        ->and(implode(' ', $mail->introLines))->toContain('1,234');
});

it('renders the ready mail without an action when there is no url', function () {
    $notification = new ActivityExportReadyNotification(null, 5);

    expect($notification->toMail(new stdClass)->actionUrl)->toBeNull()
        ->and($notification->via(new stdClass))->toBe(['mail']);
});

it('renders the failure mail as an error', function () {
    $notification = new ActivityExportFailedNotification;
    $mail = $notification->toMail(new stdClass);

    expect($mail->level)->toBe('error')
        ->and($notification->via(new stdClass))->toBe(['mail']);
});
