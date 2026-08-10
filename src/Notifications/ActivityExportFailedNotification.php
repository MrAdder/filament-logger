<?php

namespace MrAdder\FilamentLogger\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail notification for a queued export that could not be generated.
 *
 * The user was already told their export was queued, so a failure has to reach
 * them rather than only appearing in the application log.
 */
class ActivityExportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->error()
            ->subject(__('filament-logger::filament-logger.export.failed_title'))
            ->line(__('filament-logger::filament-logger.export.failed_body'));
    }
}
