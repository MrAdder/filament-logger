<?php

use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Support\ActivityExporter;
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
