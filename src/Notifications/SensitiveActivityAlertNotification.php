<?php

namespace MrAdder\FilamentLogger\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use MrAdder\FilamentLogger\Support\ActivityAlertMessage;
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
        protected ?ActivityAlertMessage $message = null,
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
        $mail = (new MailMessage)->subject($this->title);

        foreach ($this->bodyLines() as $line) {
            $mail->line($line);
        }

        return $mail;
    }

    /**
     * Rendered rule template when one is present, otherwise the built-in lines.
     *
     * @return array<int, string>
     */
    protected function bodyLines(): array
    {
        if ($this->message instanceof ActivityAlertMessage) {
            return $this->message->lines();
        }

        return array_values(array_filter([
            (string) data_get($this->activity, 'description'),
            'Event: '.(data_get($this->activity, 'event') ?? '-'),
            'Log: '.(data_get($this->activity, 'log_name') ?? '-'),
            'Risk: '.(data_get($this->activity, 'properties.risk') ?? '-'),
            'Logged at: '.(optional(data_get($this->activity, 'created_at'))?->toDateTimeString() ?? '-'),
        ]));
    }
}
