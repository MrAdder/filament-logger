<?php

use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use MrAdder\FilamentLogger\Loggers\NotificationLogger;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity as ActivityModel;

class InvoicePaidNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return new MailMessage;
    }
}

function notifiableUser(): TestUser
{
    return TestUser::create(['name' => 'Dana', 'email' => 'dana@example.test']);
}

function latestActivity(): ?ActivityModel
{
    return ActivityModel::latest('id')->first();
}

beforeEach(function () {
    config()->set('filament-logger.notifications.log_name', 'Notification');
});

it('logs a sent notification', function () {
    $user = notifiableUser();

    (new NotificationLogger)->handle(
        new NotificationSent($user, new InvoicePaidNotification, 'mail'),
    );

    $activity = latestActivity();

    expect($activity->log_name)->toBe('Notification')
        ->and($activity->event)->toBe('Notification Sent')
        ->and($activity->description)->toContain('InvoicePaidNotification Notification sent');
});

it('logs a failed notification', function () {
    $user = notifiableUser();

    (new NotificationLogger)->handle(
        new NotificationFailed($user, new InvoicePaidNotification, 'mail', []),
    );

    expect(latestActivity()->description)->toContain('InvoicePaidNotification Notification failed');
});

it('omits the recipient unless logging it is enabled', function () {
    config()->set('filament-logger.notifications.log_recipient', false);

    (new NotificationLogger)->handle(
        new NotificationSent(notifiableUser(), new InvoicePaidNotification, 'mail'),
    );

    expect(latestActivity()->description)->not->toContain('dana@example.test')
        ->and(latestActivity()->description)->not->toContain(' to ');
});

it('masks the recipient when logging it is enabled', function () {
    config()->set('filament-logger.notifications.log_recipient', true);
    config()->set('filament-logger.notifications.mask_recipient', true);

    (new NotificationLogger)->handle(
        new NotificationSent(notifiableUser(), new InvoicePaidNotification, 'mail'),
    );

    $description = latestActivity()->description;

    expect($description)->toContain('@example.test')
        ->and($description)->not->toContain('dana@example.test');
});

it('records the raw recipient when masking is disabled', function () {
    config()->set('filament-logger.notifications.log_recipient', true);
    config()->set('filament-logger.notifications.mask_recipient', false);

    (new NotificationLogger)->handle(
        new NotificationSent(notifiableUser(), new InvoicePaidNotification, 'mail'),
    );

    expect(latestActivity()->description)->toContain('dana@example.test');
});

it('records notifications anonymously', function () {
    (new NotificationLogger)->handle(
        new NotificationSent(notifiableUser(), new InvoicePaidNotification, 'mail'),
    );

    expect(latestActivity()->causer_id)->toBeNull()
        ->and(latestActivity()->causer_type)->toBeNull();
});

it('is wired to the notification events when enabled', function () {
    config()->set('filament-logger.notifications.enabled', true);

    expect(Event::hasListeners(NotificationSent::class))->toBeTrue()
        ->and(Event::hasListeners(NotificationFailed::class))->toBeTrue();
});
