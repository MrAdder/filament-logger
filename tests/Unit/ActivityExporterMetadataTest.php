<?php

use MrAdder\FilamentLogger\Support\ActivityExporter;
use Spatie\Activitylog\Models\Activity as ActivityModel;

it('adds export metadata header for csv exports', function () {
    ActivityModel::create([
        'log_name' => 'default',
        'description' => 'Updated email address for user',
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'event' => 'updated',
        'properties' => ['tags' => ['email', 'user']],
    ]);

    $exporter = app(ActivityExporter::class);

    $response = $exporter->toCsv(ActivityModel::query());

    $header = $response->headers->get('X-Activity-Export-Metadata');

    expect($header)->not->toBeNull();

    $meta = json_decode($header, true);

    expect($meta)->toBeArray();
    expect($meta)->toHaveKey('exported_at');
    expect($meta['columns'])->toContain('id');
});
