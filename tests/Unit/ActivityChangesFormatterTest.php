<?php

use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\Support\ActivityChangesFormatter;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity;

it('shows redacted values to viewers without sensitive data access', function () {
    config()->set('filament-logger.access.redact_ip_for_unauthorized_viewers', true);
    config()->set('filament-logger.access.anonymize_ip', false);

    $activity = new Activity();
    $activity->forceFill([
        'properties' => [
            'old' => [
                'api_token' => 'secret-token',
                'ip_address' => '203.0.113.22',
            ],
            'attributes' => [
                'api_token' => 'another-secret-token',
                'ip_address' => '198.51.100.14',
            ],
        ],
    ]);

    $formatted = ActivityChangesFormatter::for($activity);
    $rows = collect($formatted['rows'])->keyBy('field');

    expect($rows['api_token']['old']['display'])->toBe('[REDACTED]')
        ->and($rows['api_token']['new']['display'])->toBe('[REDACTED]')
        ->and($rows['ip_address']['old']['display'])->toBe('[REDACTED]')
        ->and($rows['ip_address']['new']['display'])->toBe('[REDACTED]');
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
                'ip_address' => '203.0.113.22',
            ],
            'attributes' => [
                'api_token' => 'another-secret-token',
                'ip_address' => '198.51.100.14',
            ],
        ],
    ]);

    $formatted = ActivityChangesFormatter::for($activity);
    $rows = collect($formatted['rows'])->keyBy('field');

    expect($rows['api_token']['old']['display'])->toBe('secret-token')
        ->and($rows['api_token']['new']['display'])->toBe('another-secret-token')
        ->and($rows['ip_address']['old']['display'])->toBe('203.0.113.22')
        ->and($rows['ip_address']['new']['display'])->toBe('198.51.100.14');
});

class AllowSensitiveActivityPolicy
{
    public function viewSensitiveData(?object $user = null, mixed $record = null): bool
    {
        return true;
    }
}
