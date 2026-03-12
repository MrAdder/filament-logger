<?php

use MrAdder\FilamentLogger\Support\LogDataSanitizer;

const SANITIZER_SOURCE_IP = '192.168.1.22';
const SANITIZER_SOURCE_USER_AGENT = 'Firefox/123.456';

it('redacts configured sensitive keys recursively', function () {
    config()->set('filament-logger.redacted_placeholder', '[MASKED]');
    config()->set('filament-logger.access.store_ip', true);
    config()->set('filament-logger.access.anonymize_ip', true);
    config()->set('filament-logger.access.store_user_agent', true);
    config()->set('filament-logger.access.user_agent_max_length', 8);

    $sanitized = LogDataSanitizer::sanitizeProperties([
        'password' => 'secret',
        'profile' => [
            'api_token' => 'abc123',
            'nickname' => 'Taylor',
        ],
        'tokenable_id' => 7,
        'ip' => SANITIZER_SOURCE_IP,
        'user_agent' => SANITIZER_SOURCE_USER_AGENT,
    ]);

    expect($sanitized['password'])->toBe('[MASKED]')
        ->and($sanitized['profile']['api_token'])->toBe('[MASKED]')
        ->and($sanitized['profile']['nickname'])->toBe('Taylor')
        ->and($sanitized['tokenable_id'])->toBe(7)
        ->and($sanitized['ip'])->toBe('192.168.1.0')
        ->and($sanitized['user_agent'])->toBe('Firefox/');
});

it('can preserve sensitive values for authorized viewers', function () {
    config()->set('filament-logger.access.store_ip', true);
    config()->set('filament-logger.access.anonymize_ip', true);
    config()->set('filament-logger.access.store_user_agent', true);
    config()->set('filament-logger.access.user_agent_max_length', 8);

    $sanitized = LogDataSanitizer::sanitizeProperties([
        'password' => 'secret',
        'profile' => [
            'api_token' => 'abc123',
        ],
        'ip' => SANITIZER_SOURCE_IP,
        'user_agent' => SANITIZER_SOURCE_USER_AGENT,
    ], [
        'preserve_sensitive_data' => true,
    ]);

    expect($sanitized['password'])->toBe('secret')
        ->and($sanitized['profile']['api_token'])->toBe('abc123')
        ->and($sanitized['ip'])->toBe(SANITIZER_SOURCE_IP)
        ->and($sanitized['user_agent'])->toBe(SANITIZER_SOURCE_USER_AGENT);
});

it('can redact ip addresses for unauthorized viewers', function () {
    config()->set('filament-logger.redacted_placeholder', '[MASKED]');
    config()->set('filament-logger.access.store_ip', true);
    config()->set('filament-logger.access.anonymize_ip', false);

    $sanitized = LogDataSanitizer::sanitizeProperties([
        'ip_address' => '203.0.113.22',
    ], [
        'redact_ip_addresses' => true,
    ]);

    expect($sanitized['ip_address'])->toBe('[MASKED]');
});

it('can redact additional configured keys', function () {
    config()->set('filament-logger.redacted_placeholder', '[MASKED]');
    config()->set('filament-logger.sensitive_keys', [
        'webhook_url',
        'authorization',
        'ip_address',
    ]);

    $sanitized = LogDataSanitizer::sanitizeProperties([
        'webhook_url' => 'https://hooks.example.test/demo',
        'request_authorization' => 'Bearer super-secret-token',
        'profile' => [
            'ip_address' => '203.0.113.22',
        ],
        'safe_value' => 'visible',
    ]);

    expect($sanitized['webhook_url'])->toBe('[MASKED]')
        ->and($sanitized['request_authorization'])->toBe('[MASKED]')
        ->and($sanitized['profile']['ip_address'])->toBe('[MASKED]')
        ->and($sanitized['safe_value'])->toBe('visible');
});

it('omits notification recipients unless explicitly enabled', function () {
    config()->set('filament-logger.notifications.log_recipient', false);

    expect(LogDataSanitizer::sanitizeNotificationRecipient('alice@example.com'))->toBeNull();
});

it('masks notification recipients when recipient logging is enabled', function () {
    config()->set('filament-logger.notifications.log_recipient', true);
    config()->set('filament-logger.notifications.mask_recipient', true);

    expect(LogDataSanitizer::sanitizeNotificationRecipient('alice@example.com'))
        ->toBe('a****@example.com');
});

it('can keep notification recipients unmasked when configured', function () {
    config()->set('filament-logger.notifications.log_recipient', true);
    config()->set('filament-logger.notifications.mask_recipient', false);

    expect(LogDataSanitizer::sanitizeNotificationRecipient('alice@example.com'))
        ->toBe('alice@example.com');
});
