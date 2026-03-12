<?php

use MrAdder\FilamentLogger\Support\LogDataSanitizer;

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
        'ip' => '192.168.1.22',
        'user_agent' => 'Firefox/123.456',
    ]);

    expect($sanitized['password'])->toBe('[MASKED]')
        ->and($sanitized['profile']['api_token'])->toBe('[MASKED]')
        ->and($sanitized['profile']['nickname'])->toBe('Taylor')
        ->and($sanitized['tokenable_id'])->toBe(7)
        ->and($sanitized['ip'])->toBe('192.168.1.0')
        ->and($sanitized['user_agent'])->toBe('Firefox/');
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
