<?php

namespace MrAdder\FilamentLogger\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail notification for a completed queued export. Used when
 * `exports.queue.notify` is set to 'mail'.
 */
class ActivityExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?string $url,
        protected int $rows,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('filament-logger::filament-logger.export.ready_title'))
            ->line(__('filament-logger::filament-logger.export.ready_body', [
                'rows' => number_format($this->rows),
            ]));

        if ($this->url !== null) {
            $mail->action(__('filament-logger::filament-logger.export.download'), $this->url);
        }

        return $mail;
    }
}
