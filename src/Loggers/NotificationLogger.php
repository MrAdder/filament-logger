<?php

namespace MrAdder\FilamentLogger\Loggers;

use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Str;
use MrAdder\FilamentLogger\FilamentLogger as FilamentLoggerManager;
use MrAdder\FilamentLogger\Support\LogDataSanitizer;

class NotificationLogger
{
    /**
     * Log the notification
     *
     * @return void
     */
    public function handle(NotificationSent|NotificationFailed $event)
    {
        $notification = class_basename($event->notification);

        if ($event instanceof NotificationSent) {
            $description = $notification.' Notification sent';
        } else {
            $description = $notification.' Notification failed';
        }

        $recipient = LogDataSanitizer::sanitizeNotificationRecipient(
            $this->getRecipient($event->notifiable, $event->channel)
        );

        if ($recipient) {
            $description .= ' to '.$recipient;
        }

        app(FilamentLoggerManager::class)->log(
            event: (string) Str::of(class_basename($event))->headline(),
            description: $description,
            options: [
                'logName' => config('filament-logger.notifications.log_name'),
                'anonymous' => true,
            ],
        );
    }

    public function getRecipient(mixed $notifiable, string $channel): ?string
    {
        $notificationRoute = $notifiable->routeNotificationFor($channel);

        return is_string($notificationRoute) ? $notificationRoute : null;
    }
}
