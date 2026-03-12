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

class AllowSensitiveActivityPolicy
{
    public function viewSensitiveData(): bool
    {
        return true;
    }
}
