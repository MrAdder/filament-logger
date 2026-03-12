<?php

namespace MrAdder\FilamentLogger\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class SensitiveActivityAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected ActivityContract $activity,
        protected string $title,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->title)
            ->line((string) data_get($this->activity, 'description'))
            ->line('Event: '.(data_get($this->activity, 'event') ?? '-'))
            ->line('Log: '.(data_get($this->activity, 'log_name') ?? '-'))
            ->line('Risk: '.(data_get($this->activity, 'properties.risk') ?? '-'))
            ->line('Logged at: '.optional(data_get($this->activity, 'created_at'))?->toDateTimeString());
    }
}
