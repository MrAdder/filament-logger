<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

class LogDataSanitizer
{
    public static function sanitizeProperties(mixed $properties): array
    {
        if ($properties instanceof Arrayable) {
            $properties = $properties->toArray();
        }

        if (! is_array($properties)) {
            return [];
        }

        return self::sanitizeArray($properties);
    }

    public static function sanitizeNotificationRecipient(?string $recipient): ?string
    {
        if (blank($recipient) || ! config('filament-logger.notifications.log_recipient', false)) {
            return null;
        }

        if (! config('filament-logger.notifications.mask_recipient', true)) {
            return $recipient;
        }

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            [$localPart, $domain] = explode('@', $recipient, 2);

            return self::maskValue($localPart, 1, 0).'@'.$domain;
        }

        if (preg_match('/^\+?[\d\s\-\(\)]+$/', $recipient) === 1) {
            return self::maskDigits($recipient, 2);
        }

        return self::maskValue($recipient, 2, 2);
    }

    protected static function sanitizeArray(array $properties): array
    {
        $sanitized = [];

        foreach ($properties as $key => $value) {
            if (self::shouldRedactKey($key)) {
                $sanitized[$key] = config('filament-logger.redacted_placeholder', '[REDACTED]');

                continue;
            }

            if (self::isIpKey($key)) {
                $sanitized[$key] = self::sanitizeIp($value);

                continue;
            }

            if (self::isUserAgentKey($key)) {
                $sanitized[$key] = self::sanitizeUserAgent($value);

                continue;
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            $sanitized[$key] = is_array($value) ? self::sanitizeArray($value) : $value;
        }

        return $sanitized;
    }

    protected static function shouldRedactKey(mixed $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $normalizedKey = self::normalizeKey($key);
        $keySegments = array_values(array_filter(explode('_', $normalizedKey)));

        foreach (config('filament-logger.sensitive_keys', []) as $candidate) {
            if (! is_string($candidate) || blank($candidate)) {
                continue;
            }

            $normalizedCandidate = self::normalizeKey($candidate);

            if ($normalizedKey === $normalizedCandidate) {
                return true;
            }

            if (str_contains('_'.$normalizedKey.'_', '_'.$normalizedCandidate.'_')) {
                return true;
            }

            if (
                ! str_contains($normalizedCandidate, '_') &&
                in_array($normalizedCandidate, $keySegments, true)
            ) {
                return true;
            }
        }

        return false;
    }

    protected static function sanitizeIp(mixed $value): mixed
    {
        if (! is_string($value) || blank($value) || ! config('filament-logger.access.store_ip', true)) {
            return null;
        }

        if (! config('filament-logger.access.anonymize_ip', true)) {
            return $value;
        }

        return IpUtils::anonymize($value);
    }

    protected static function sanitizeUserAgent(mixed $value): mixed
    {
        if (! is_string($value) || blank($value) || ! config('filament-logger.access.store_user_agent', true)) {
            return null;
        }

        return Str::limit($value, (int) config('filament-logger.access.user_agent_max_length', 255), '');
    }

    protected static function isIpKey(mixed $key): bool
    {
        return is_string($key) && in_array(self::normalizeKey($key), ['ip', 'ip_address'], true);
    }

    protected static function isUserAgentKey(mixed $key): bool
    {
        return is_string($key) && self::normalizeKey($key) === 'user_agent';
    }

    protected static function normalizeKey(string $key): string
    {
        return (string) str($key)
            ->replace(['-', ' '], '_')
            ->snake()
            ->lower();
    }

    protected static function maskDigits(string $value, int $visibleDigits = 2): string
    {
        $digitCount = preg_match_all('/\d/u', $value);
        $digitsToMask = max($digitCount - $visibleDigits, 0);
        $maskedDigits = 0;

        return (string) preg_replace_callback('/\d/u', function (array $matches) use (&$maskedDigits, $digitsToMask) {
            if ($maskedDigits >= $digitsToMask) {
                return $matches[0];
            }

            $maskedDigits++;

            return '*';
        }, $value);
    }

    protected static function maskValue(string $value, int $visiblePrefix = 1, int $visibleSuffix = 2): string
    {
        $length = Str::length($value);

        if ($length === 0) {
            return $value;
        }

        if ($length <= ($visiblePrefix + $visibleSuffix)) {
            return str_repeat('*', max($length, 3));
        }

        $suffix = $visibleSuffix > 0 ? Str::substr($value, -$visibleSuffix) : '';

        return Str::substr($value, 0, $visiblePrefix)
            .str_repeat('*', $length - $visiblePrefix - $visibleSuffix)
            .$suffix;
    }
}
