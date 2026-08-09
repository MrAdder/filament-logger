<?php

use Illuminate\Support\Str;
use MrAdder\FilamentLogger\Support\ActivityExporter;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use Spatie\Activitylog\Models\Activity as ActivityModel;

function makeActivity(array $attributes = []): ActivityModel
{
    $record = TestRecord::create(['name' => 'Subject '.Str::random(6)]);

    return ActivityModel::create(array_merge([
        'log_name' => 'Resource',
        'description' => 'Updated email address for user',
        'subject_type' => $record::class,
        'subject_id' => $record->getKey(),
        'event' => 'Updated',
        'properties' => ['tags' => ['email']],
    ], $attributes));
}

function captureExport(callable $exporter): string
{
    $response = $exporter();

    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('produces parseable json when metadata is embedded', function () {
    makeActivity();
    makeActivity(['description' => 'Second entry']);

    $body = captureExport(fn () => app(ActivityExporter::class)->toJson(
        ActivityModel::query(),
        ['id', 'description'],
        ['embed' => true, 'preset' => 'high_risk'],
    ));

    $decoded = json_decode($body, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded)->toBeArray()
        ->and($decoded)->toHaveKeys(['metadata', 'rows'])
        ->and($decoded['metadata']['preset'])->toBe('high_risk')
        ->and($decoded['rows'])->toHaveCount(2);
});

it('still produces a bare json array when metadata is not embedded', function () {
    makeActivity();

    $body = captureExport(fn () => app(ActivityExporter::class)->toJson(
        ActivityModel::query(),
        ['id', 'description'],
    ));

    $decoded = json_decode($body, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded)->toBeArray()
        ->and($decoded)->toHaveCount(1)
        ->and($decoded[0])->toHaveKey('description');
});

it('neutralises spreadsheet formulas in csv exports', function () {
    makeActivity(['description' => '=1+1']);
    makeActivity(['description' => '+SUM(A1:A9)']);
    makeActivity(['description' => '-2+3']);
    makeActivity(['description' => '@SUM(A1)']);
    makeActivity(['description' => 'Ordinary description']);

    $body = captureExport(fn () => app(ActivityExporter::class)->toCsv(
        ActivityModel::query(),
        ['description'],
    ));

    expect($body)->toContain("'=1+1")
        ->and($body)->toContain("'+SUM(A1:A9)")
        ->and($body)->toContain("'-2+3")
        ->and($body)->toContain("'@SUM(A1)")
        ->and($body)->toContain('Ordinary description')
        ->and($body)->not->toContain("\n=1+1");
});

it('keeps request filters out of the metadata response header', function () {
    makeActivity();

    $response = app(ActivityExporter::class)->toCsv(
        ActivityModel::query(),
        ['id'],
        [
            'preset' => 'high_risk',
            'filters' => ['search' => ['query' => 'secret-internal-term']],
        ],
    );

    $header = $response->headers->get('X-Activity-Export-Metadata');

    expect($header)->not->toBeNull()
        ->and($header)->not->toContain('secret-internal-term');

    $meta = json_decode((string) $header, true);

    expect($meta)->toHaveKey('preset')
        ->and($meta)->not->toHaveKey('filters');
});

it('omits the metadata header when it would exceed the size limit', function () {
    makeActivity();

    $response = app(ActivityExporter::class)->toCsv(
        ActivityModel::query(),
        ['id'],
        ['source' => str_repeat('a', 5000)],
    );

    expect($response->headers->get('X-Activity-Export-Metadata'))->toBeNull();
});
