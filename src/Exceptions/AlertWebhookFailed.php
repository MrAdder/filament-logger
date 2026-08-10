<?php

namespace MrAdder\FilamentLogger\Exceptions;

class AlertWebhookFailed extends FilamentLoggerException
{
    public static function status(string $url, int $status): self
    {
        return new self(sprintf(
            'Activity alert webhook to %s failed with status %d.',
            parse_url($url, PHP_URL_HOST) ?: 'the configured endpoint',
            $status,
        ));
    }
}
