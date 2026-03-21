<?php

use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\Support\ActivityChangesFormatter;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity;

const CHANGES_FORMATTER_OLD_IP = '203.0.113.22';
const CHANGES_FORMATTER_NEW_IP = '198.51.100.14';
const CHANGES_FORMATTER_REDACTED = '[REDACTED]';

it('shows redacted values to viewers without sensitive data access', function () {
    config()->set('filament-logger.access.redact_ip_for_unauthorized_viewers', true);
    config()->set('filament-logger.access.anonymize_ip', false);

    $activity = new Activity();
    $activity->forceFill([
        'properties' => [
            'old' => [
                'api_token' => 'secret-token',
                'ip_address' => CHANGES_FORMATTER_OLD_IP,
            ],
            'attributes' => [
                'api_token' => 'another-secret-token',
                'ip_address' => CHANGES_FORMATTER_NEW_IP,
            ],
        ],
    ]);

    $formatted = ActivityChangesFormatter::for($activity);
    $rows = collect($formatted['rows'])->keyBy('field');

    expect($rows['api_token']['old']['display'])->toBe(CHANGES_FORMATTER_REDACTED)
        ->and($rows['api_token']['new']['display'])->toBe(CHANGES_FORMATTER_REDACTED)
        ->and($rows['ip_address']['old']['display'])->toBe(CHANGES_FORMATTER_REDACTED)
        ->and($rows['ip_address']['new']['display'])->toBe(CHANGES_FORMATTER_REDACTED);
});

it('shows stored sensitive values to viewers with sensitive data access', function () {
    config()->set('filament-logger.access.redact_ip_for_unauthorized_viewers', true);
    config()->set('filament-logger.access.anonymize_ip', false);

    Gate::policy(Activity::class, AllowSensitiveActivityPolicy::class);

    $user = TestUser::query()->create([
        'name' => 'Taylor',
        'email' => 'taylor@example.com',
    ]);

    $this->actingAs($user);

    $activity = new Activity();
    $activity->forceFill([
        'properties' => [
            'old' => [
                'api_token' => 'secret-token',
                'ip_address' => CHANGES_FORMATTER_OLD_IP,
            ],
            'attributes' => [
                'api_token' => 'another-secret-token',
                'ip_address' => CHANGES_FORMATTER_NEW_IP,
            ],
        ],
    ]);

    $formatted = ActivityChangesFormatter::for($activity);
    $rows = collect($formatted['rows'])->keyBy('field');

    expect($rows['api_token']['old']['display'])->toBe('secret-token')
        ->and($rows['api_token']['new']['display'])->toBe('another-secret-token')
        ->and($rows['ip_address']['old']['display'])->toBe(CHANGES_FORMATTER_OLD_IP)
        ->and($rows['ip_address']['new']['display'])->toBe(CHANGES_FORMATTER_NEW_IP);
});

it('flattens nested change payloads into readable field paths', function () {
    $activity = new Activity();
    $activity->forceFill([
        'properties' => [
            'old' => [
                'profile' => [
                    'settings' => [
                        'timezone' => 'UTC',
                        'locale' => 'en',
                    ],
                ],
            ],
            'attributes' => [
                'profile' => [
                    'settings' => [
                        'timezone' => 'Africa/Johannesburg',
                        'locale' => 'en',
                    ],
                ],
            ],
        ],
    ]);

    $formatted = ActivityChangesFormatter::for($activity);
    $rows = collect($formatted['rows'])->keyBy('field');

    expect($rows)->toHaveKeys([
        'profile.settings.locale',
        'profile.settings.timezone',
    ])
        ->and($rows['profile.settings.timezone']['is_nested'])->toBeTrue()
        ->and($rows['profile.settings.timezone']['group'])->toBe('profile')
        ->and($rows['profile.settings.timezone']['old']['display'])->toBe('UTC')
        ->and($rows['profile.settings.timezone']['new']['display'])->toBe('Africa/Johannesburg');
});

it('marks large nested values as expandable with size metadata', function () {
    config()->set('filament-logger.diff.collapse_after', 40);

    $activity = new Activity();
    $activity->forceFill([
        'properties' => [
            'old' => [
                'payload' => [
                    'tags' => [
                        'security',
                        'audit',
                        'compliance',
                        'investigation',
                    ],
                ],
            ],
            'attributes' => [
                'payload' => [
                    'tags' => [
                        'security',
                        'audit',
                        'compliance',
                        'investigation',
                        'urgent',
                    ],
                ],
            ],
        ],
    ]);

    $formatted = ActivityChangesFormatter::for($activity);
    $rows = collect($formatted['rows'])->keyBy('field');
    $oldPayload = $rows['payload.tags']['old'];
    $newPayload = $rows['payload.tags']['new'];

    expect($oldPayload['expandable'])->toBeTrue()
        ->and($newPayload['expandable'])->toBeTrue()
        ->and($oldPayload['line_count'])->toBeGreaterThan(0)
        ->and($newPayload['line_count'])->toBeGreaterThan(0)
        ->and($newPayload['char_count'])->toBeGreaterThan($oldPayload['char_count'])
        ->and($newPayload['summary'])->toContain('lines');
});

class AllowSensitiveActivityPolicy
{
    public function viewSensitiveData(): bool
    {
        return true;
    }
}
