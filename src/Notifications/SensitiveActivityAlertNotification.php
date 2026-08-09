<?php

namespace MrAdder\FilamentLogger\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

/**
 * Queued when `filament-logger.alerts.queue` is enabled. The dispatcher calls
 * notifyNow() otherwise, so this stays synchronous by default.
 */
class SensitiveActivityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ActivityContract $activity,
        protected string $title,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function viaConnections(): array
    {
        return ['mail' => config('filament-logger.alerts.queue_connection')];
    }

    /**
     * @return array<string, string|null>
     */
    public function viaQueues(): array
    {
        return ['mail' => config('filament-logger.alerts.queue_name')];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line((string) data_get($this->activity, 'description'))
            ->line('Event: '.(data_get($this->activity, 'event') ?? '-'))
            ->line('Log: '.(data_get($this->activity, 'log_name') ?? '-'))
            ->line('Risk: '.(data_get($this->activity, 'properties.risk') ?? '-'))
            ->line('Logged at: '.optional(data_get($this->activity, 'created_at'))?->toDateTimeString());
    }
}
