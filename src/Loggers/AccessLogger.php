<?php

namespace MrAdder\FilamentLogger\Loggers;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use MrAdder\FilamentLogger\FilamentLogger as FilamentLoggerManager;
use MrAdder\FilamentLogger\Support\LogDataSanitizer;

class AccessLogger
{
    public function handle(mixed $event): void
    {
        if (! $this->shouldLogEvent($event)) {
            return;
        }

        [$eventName, $description, $properties, $causer] = match (true) {
            $event instanceof Login => [
                'Login',
                Filament::getUserName($event->user).' logged in',
                $this->buildRequestProperties(request(), ['guard' => $event->guard]),
                $event->user,
            ],
            $event instanceof Logout => [
                'Logout',
                Filament::getUserName($event->user).' logged out',
                $this->buildRequestProperties(request(), ['guard' => $event->guard]),
                $event->user,
            ],
            $event instanceof Failed => [
                'Failed Login',
                $event->user
                    ? Filament::getUserName($event->user).' failed to log in'
                    : 'Authentication failed',
                $this->buildRequestProperties(request(), [
                    'guard' => $event->guard,
                    'identifiers' => $this->extractIdentifiers($event->credentials),
                ]),
                $event->user,
            ],
            $event instanceof Lockout => [
                'Lockout',
                'Authentication locked out',
                $this->buildRequestProperties($event->request, [
                    'identifiers' => $this->extractIdentifiers($event->request->all()),
                ]),
                null,
            ],
            $event instanceof PasswordReset => [
                'Password Reset',
                Filament::getUserName($event->user).' reset their password',
                $this->buildRequestProperties(request()),
                $event->user,
            ],
            $this->isTwoFactorRecoveryEvent($event) => [
                'Two Factor Recovery',
                Filament::getUserName($event->user).' used a two-factor recovery code',
                $this->buildRequestProperties(request(), ['used_recovery_code' => true]),
                $event->user,
            ],
            default => [null, null, [], null],
        };

        if (! is_string($eventName) || ! is_string($description)) {
            return;
        }

        app(FilamentLoggerManager::class)->log(
            event: $eventName,
            description: $description,
            options: [
                'properties' => $properties,
                'logName' => config('filament-logger.access.log_name'),
                'causer' => ($causer instanceof Model && $causer->exists) ? $causer : null,
                'anonymous' => ! ($causer instanceof Model && $causer->exists),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function buildRequestProperties(?Request $request, array $extra = []): array
    {
        $request = $request ?? request();

        $properties = array_merge([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $extra);

        return array_filter(
            LogDataSanitizer::sanitizeProperties($properties),
            static fn (mixed $value): bool => filled($value),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function extractIdentifiers(array $values): array
    {
        $identifiers = [];

        foreach (config('filament-logger.access.identifier_keys', ['email', 'username', 'login']) as $key) {
            if (array_key_exists($key, $values)) {
                $identifiers[$key] = $values[$key];
            }
        }

        return $identifiers;
    }

    protected function isTwoFactorRecoveryEvent(mixed $event): bool
    {
        return is_object($event)
            && class_basename($event) === 'RecoveryCodeReplaced'
            && property_exists($event, 'user')
            && ($event->user instanceof Authenticatable);
    }

    protected function shouldLogEvent(mixed $event): bool
    {
        $allowedGuards = config('filament-logger.access.guards');

        if ($allowedGuards === null) {
            return true;
        }

        if (! is_object($event) || ! property_exists($event, 'guard')) {
            return true;
        }

        return in_array($event->guard, (array) $allowedGuards, true);
    }
}
