<?php

use Illuminate\Support\Carbon;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Support\ActivityDatePreset;
use MrAdder\FilamentLogger\Support\ActivityFilterPresetManager;
use Spatie\Activitylog\Models\Activity;

it('applies saved filter presets to activity queries', function () {
    app(FilamentLogger::class)->log(
        event: 'Deleted',
        description: 'High risk deletion',
        options: [
            'logName' => 'Resource',
            'anonymous' => true,
        ],
    );

    app(FilamentLogger::class)->log(
        event: 'Created',
        description: 'Normal activity',
        options: [
            'logName' => 'Resource',
            'anonymous' => true,
        ],
    );

    $query = ActivityFilterPresetManager::apply(Activity::query(), [
        'risk' => ['high'],
    ]);

    expect($query->pluck('event')->all())->toBe(['Deleted']);
});

it('resolves and applies date presets', function () {
    Carbon::setTestNow('2026-03-12 12:00:00');

    app(FilamentLogger::class)->log(
        event: 'Today Event',
        description: 'Fresh activity',
        options: [
            'logName' => 'Custom',
            'anonymous' => true,
            'createdAt' => now()->subHour(),
        ],
    );

    app(FilamentLogger::class)->log(
        event: 'Old Event',
        description: 'Older activity',
        options: [
            'logName' => 'Custom',
            'anonymous' => true,
            'createdAt' => now()->subDays(10),
        ],
    );

    $query = ActivityDatePreset::apply(Activity::query(), 'last_7_days');

    expect($query->pluck('event')->all())->toBe(['Today Event']);

    Carbon::setTestNow();
});
