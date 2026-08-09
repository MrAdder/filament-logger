<?php

use MrAdder\FilamentLogger\Models\ExportPreset;
use MrAdder\FilamentLogger\Support\ActivityExporter;
use MrAdder\FilamentLogger\Support\ActivityExportPresetManager;
use Spatie\Activitylog\Models\Activity as ActivityModel;

it('saves and applies a db export preset and exporter uses its columns', function () {
    config(['filament-logger.exports.db_presets_enabled' => true]);

    ExportPreset::create([
        'key' => 'short',
        'label' => 'Short Export',
        'columns' => ['id', 'description', 'created_at'],
        'filters' => ['events' => ['updated']],
    ]);

    $saved = ActivityExportPresetManager::saved();

    expect(array_key_exists('short', $saved))->toBeTrue();

    // create an activity to export
    ActivityModel::create([
        'log_name' => 'default',
        'description' => 'Short export test',
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'event' => 'updated',
        'properties' => [],
    ]);

    $presetDef = $saved['short'];

    $columns = $presetDef['columns'];

    $exporter = app(ActivityExporter::class);

    $response = $exporter->toCsv(ActivityModel::query(), $columns, ['preset' => 'short']);

    $header = $response->headers->get('X-Activity-Export-Metadata');

    expect($header)->not->toBeNull();

    $meta = json_decode($header, true);

    expect($meta['columns'])->toEqual($columns);
});
