<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

class LogDataSanitizer
{
    /**
     * @param  array{preserve_sensitive_data?: bool, redact_ip_addresses?: bool}  $options
     * @return array<string, mixed>
     */
    public static function sanitizeProperties(mixed $properties, array $options = []): array
    {
        if ($properties instanceof Arrayable) {
            $properties = $properties->toArray();
        }

        if (! is_array($properties)) {
            return [];
        }

        return self::sanitizeArray($properties, self::sanitizeOptions($options));
    }

    public static function sanitizeNotificationRecipient(?string $recipient): ?string
    {
        if (blank($recipient) || ! config('filament-logger.notifications.log_recipient', false)) {
            return null;
        }

        if (! config('filament-logger.notifications.mask_recipient', true)) {
            return $recipient;
        }

        return match (true) {
            filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false => self::maskEmailRecipient($recipient),
            preg_match('/^\+?[\d\s\-\(\)]+$/', $recipient) === 1 => self::maskDigits($recipient, 2),
            default => self::maskValue($recipient, 2, 2),
        };
    }

    /**
     * @param  array{preserve_sensitive_data: bool, redact_ip_addresses: bool}  $options
     * @return array<string, mixed>
     */
    protected static function sanitizeArray(array $properties, array $options): array
    {
        $sanitized = [];

        foreach ($properties as $key => $value) {
            if (! $options['preserve_sensitive_data'] && self::shouldRedactKey($key)) {
                $sanitized[$key] = config('filament-logger.redacted_placeholder', '[REDACTED]');

                continue;
            }

            if (self::isIpKey($key)) {
                $sanitized[$key] = self::sanitizeIp($value, $options);

                continue;
            }

            if (self::isUserAgentKey($key)) {
                $sanitized[$key] = self::sanitizeUserAgent($value, $options);

                continue;
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            $sanitized[$key] = is_array($value) ? self::sanitizeArray($value, $options) : $value;
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
            if (self::candidateMatchesKey($normalizedKey, $keySegments, $candidate)) {
                return true;
            }
        }

        return false;
    }

    protected static function maskEmailRecipient(string $recipient): string
    {
        [$localPart, $domain] = explode('@', $recipient, 2);

        return self::maskValue($localPart, 1, 0).'@'.$domain;
    }

    protected static function candidateMatchesKey(string $normalizedKey, array $keySegments, mixed $candidate): bool
    {
        if (! is_string($candidate) || blank($candidate)) {
            return false;
        }

        $normalizedCandidate = self::normalizeKey($candidate);

        return $normalizedKey === $normalizedCandidate
            || str_contains('_'.$normalizedKey.'_', '_'.$normalizedCandidate.'_')
            || (
                ! str_contains($normalizedCandidate, '_')
                && in_array($normalizedCandidate, $keySegments, true)
            );
    }

    /**
     * @param  array{preserve_sensitive_data: bool, redact_ip_addresses: bool}  $options
     */
    protected static function sanitizeIp(mixed $value, array $options): mixed
    {
        if (! is_string($value) || blank($value) || ! config('filament-logger.access.store_ip', true)) {
            return null;
        }

        $sanitized = $value;

        if ($options['redact_ip_addresses']) {
            $sanitized = config('filament-logger.redacted_placeholder', '[REDACTED]');
        } elseif (! $options['preserve_sensitive_data'] && config('filament-logger.access.anonymize_ip', true)) {
            $sanitized = IpUtils::anonymize($value);
        }

        return $sanitized;
    }

    /**
     * @param  array{preserve_sensitive_data: bool, redact_ip_addresses: bool}  $options
     */
    protected static function sanitizeUserAgent(mixed $value, array $options): mixed
    {
        if (! is_string($value) || blank($value) || ! config('filament-logger.access.store_user_agent', true)) {
            return null;
        }

        return $options['preserve_sensitive_data']
            ? $value
            : Str::limit($value, (int) config('filament-logger.access.user_agent_max_length', 255), '');
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

    /**
     * @param  array{preserve_sensitive_data?: bool, redact_ip_addresses?: bool}  $options
     * @return array{preserve_sensitive_data: bool, redact_ip_addresses: bool}
     */
    protected static function sanitizeOptions(array $options): array
    {
        return [
            'preserve_sensitive_data' => (bool) ($options['preserve_sensitive_data'] ?? false),
            'redact_ip_addresses' => (bool) ($options['redact_ip_addresses'] ?? false),
        ];
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
