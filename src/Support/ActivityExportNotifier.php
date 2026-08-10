<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\URL;
use MrAdder\FilamentLogger\Notifications\ActivityExportFailedNotification;
use MrAdder\FilamentLogger\Notifications\ActivityExportReadyNotification;
use Throwable;

/**
 * Tells the requesting user that a queued export finished.
 *
 * The channel is configurable because the Filament database notification is
 * the nicest in-panel experience but requires the host application to have
 * Laravel's `notifications` table, which not every app has.
 */
class ActivityExportNotifier
{
    public function exportReady(
        mixed $userId,
        ?string $userClass,
        string $disk,
        string $path,
        int $rows,
    ): void {
        $url = $this->downloadUrl($userId, $path);

        $channel = $this->channel();

        if ($channel === null) {
            Log::info('Activity export written.', ['disk' => $disk, 'path' => $path, 'rows' => $rows]);

            return;
        }

        $user = $this->resolveUser($userId, $userClass);

        if ($user === null) {
            Log::info('Activity export written, but the requesting user could not be resolved.', [
                'disk' => $disk,
                'path' => $path,
                'rows' => $rows,
            ]);

            return;
        }

        $channel === 'mail'
            ? $this->sendMail($user, $url, $rows)
            : $this->sendDatabase($user, $url, $rows);
    }

    public function exportFailed(mixed $userId, ?string $userClass, ?string $reason): void
    {
        Log::error('Activity export failed.', ['reason' => $reason]);

        $channel = $this->channel();

        if ($channel === null) {
            return;
        }

        $user = $this->resolveUser($userId, $userClass);

        if ($user === null) {
            return;
        }

        // The user was told the export was queued, so a failure has to reach
        // them on the same channel rather than only landing in the log.
        if ($channel === 'mail') {
            NotificationFacade::send($user, new ActivityExportFailedNotification);

            return;
        }

        $this->filamentNotification(
            title: __('filament-logger::filament-logger.export.failed_title'),
            body: __('filament-logger::filament-logger.export.failed_body'),
            url: null,
            danger: true,
        )?->sendToDatabase($user);
    }

    /**
     * A short-lived signed URL for the generated file.
     */
    public function downloadUrl(mixed $userId, string $path): ?string
    {
        if (! $this->routesEnabled()) {
            return null;
        }

        try {
            return URL::temporarySignedRoute(
                'filament-logger.exports.download',
                now()->addMinutes($this->linkMinutes()),
                ['owner' => filled($userId) ? (string) $userId : 'shared', 'path' => base64_encode($path)],
            );
        } catch (Throwable) {
            // Route not registered (for example when routes are disabled).
            return null;
        }
    }

    protected function sendMail(Authenticatable $user, ?string $url, int $rows): void
    {
        NotificationFacade::send($user, new ActivityExportReadyNotification($url, $rows));
    }

    protected function sendDatabase(Authenticatable $user, ?string $url, int $rows): void
    {
        $this->filamentNotification(
            title: __('filament-logger::filament-logger.export.ready_title'),
            body: __('filament-logger::filament-logger.export.ready_body', ['rows' => number_format($rows)]),
            url: $url,
        )?->sendToDatabase($user);
    }

    /**
     * Built reflectively so the package does not hard-depend on a Filament
     * notification API that differs between major versions.
     */
    protected function filamentNotification(string $title, string $body, ?string $url, bool $danger = false): mixed
    {
        $class = 'Filament\\Notifications\\Notification';

        if (! class_exists($class)) {
            return null;
        }

        $notification = $class::make()->title($title)->body($body);

        $notification = $danger ? $notification->danger() : $notification->success();

        if ($url !== null && class_exists('Filament\\Notifications\\Actions\\Action')) {
            $action = 'Filament\\Notifications\\Actions\\Action';

            $notification = $notification->actions([
                $action::make('download')
                    ->label(__('filament-logger::filament-logger.export.download'))
                    ->url($url),
            ]);
        }

        return $notification;
    }

    protected function resolveUser(mixed $userId, ?string $userClass): ?Authenticatable
    {
        if (blank($userId) || blank($userClass) || ! class_exists($userClass)) {
            return null;
        }

        /** @var Authenticatable|null $user */
        $user = $userClass::query()->find($userId);

        // A user model without the Notifiable trait cannot receive either
        // channel. Degrade to the log rather than fataling inside the job.
        if ($user !== null && ! method_exists($user, 'notify')) {
            Log::warning('Activity export notification skipped: the user model is not notifiable.', [
                'user' => $userClass,
            ]);

            return null;
        }

        return $user;
    }

    protected function channel(): ?string
    {
        $channel = config('filament-logger.exports.queue.notify', 'mail');

        return in_array($channel, ['database', 'mail'], true) ? $channel : null;
    }

    protected function routesEnabled(): bool
    {
        return (bool) config('filament-logger.exports.queue.routes', true);
    }

    protected function linkMinutes(): int
    {
        return max(1, (int) config('filament-logger.exports.queue.link_minutes', 1440));
    }
}
