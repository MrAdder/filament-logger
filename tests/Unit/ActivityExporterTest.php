<?php

use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Support\ActivityExporter;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity;

it('exports activity records as csv and json', function () {
    app(FilamentLogger::class)->log(
        event: 'Custom Audit Event',
        description: 'Export me',
        options: [
            'logName' => 'Custom',
            'anonymous' => true,
            'properties' => [
                'token' => 'secret-token',
                'tags' => ['export'],
            ],
        ],
    );

    $exporter = app(ActivityExporter::class);

    $csvResponse = $exporter->toCsv(Activity::query());
    $jsonResponse = $exporter->toJson(Activity::query());

    ob_start();
    $csvResponse->sendContent();
    $csv = ob_get_clean();

    ob_start();
    $jsonResponse->sendContent();
    $json = ob_get_clean();

    expect($csv)->toContain('id,log_name,event,description')
        ->and($csv)->toContain('1,Custom,"Custom Audit Event","Export me"')
        ->and($csv)->toContain('[REDACTED]')
        ->and($json)->toContain('"log_name":"Custom"')
        ->and($json)->toContain('"event":"Custom Audit Event"')
        ->and($json)->toContain('[REDACTED]');
});

it('can export stored sensitive values for authorized viewers', function () {
    config()->set('filament-logger.access.redact_ip_for_unauthorized_viewers', true);
    config()->set('filament-logger.access.anonymize_ip', false);

    Gate::policy(Activity::class, AllowSensitiveExportPolicy::class);

    $user = TestUser::query()->create([
        'name' => 'Taylor',
        'email' => 'taylor@example.com',
    ]);

    $this->actingAs($user);

    $activity = new Activity();
    $activity->forceFill([
        'log_name' => 'Custom',
        'event' => 'Sensitive Export',
        'description' => 'Export me with sensitive values',
        'properties' => [
            'token' => 'secret-token',
            'ip' => '203.0.113.22',
        ],
    ])->save();

    $response = app(ActivityExporter::class)->toJson(Activity::query());

    ob_start();
    $response->sendContent();
    $json = ob_get_clean();

    expect($json)->toContain('secret-token')
        ->and($json)->toContain('203.0.113.22');
});

class AllowSensitiveExportPolicy
{
    public function viewSensitiveData(?object $user = null, mixed $record = null): bool
    {
        return true;
    }
}
