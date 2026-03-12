<?php

use MrAdder\FilamentLogger\Facades\FilamentLogger;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity;

it('logs domain specific events without a custom logger class', function () {
    $user = TestUser::query()->create([
        'name' => 'Avery',
        'email' => 'avery@example.com',
    ]);

    $record = TestRecord::query()->create([
        'name' => 'Project Alpha',
    ]);

    FilamentLogger::log(
        event: 'Role Escalated',
        description: 'Elevated user privileges for incident response',
        options: [
            'logName' => 'Security',
            'causer' => $user,
            'subject' => $record,
            'properties' => [
                'old' => ['role' => 'editor'],
                'attributes' => ['role' => 'admin'],
                'ticket' => 'SEC-42',
            ],
            'tags' => ['security', 'roles'],
        ],
    );

    $activity = Activity::query()->latest('id')->firstOrFail();
    $properties = $activity->properties->toArray();

    expect($activity->event)->toBe('Role Escalated')
        ->and($activity->log_name)->toBe('Security')
        ->and($activity->causer_id)->toBe($user->getKey())
        ->and($activity->subject_id)->toBe($record->getKey())
        ->and(data_get($properties, 'ticket'))->toBe('SEC-42')
        ->and(data_get($properties, 'risk'))->toBe('high')
        ->and(data_get($properties, 'risk_reasons'))->toContain('role_change')
        ->and(data_get($properties, 'tags'))->toBe(['security', 'roles']);
});
