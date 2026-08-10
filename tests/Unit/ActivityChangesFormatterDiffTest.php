<?php

use MrAdder\FilamentLogger\Support\ActivityChangesFormatter;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * The structured diff is what a reviewer actually reads on the detail page, so
 * these cover the shapes it has to render rather than only the happy path.
 */
function diffFor(array $properties): array
{
    return ActivityChangesFormatter::for(ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Changed',
        'event' => 'Updated',
        'properties' => $properties,
    ]));
}

function rowFor(array $result, string $field): ?array
{
    return collect($result['rows'])->firstWhere('field', $field);
}

it('returns an empty structure for a missing activity', function () {
    expect(ActivityChangesFormatter::for(null))->toBe(['rows' => [], 'metadata' => []]);
});

it('pairs old and new values for a changed field', function () {
    $row = rowFor(diffFor([
        'old' => ['status' => 'paid'],
        'attributes' => ['status' => 'refunded'],
    ]), 'status');

    expect($row['old']['display'])->toBe('paid')
        ->and($row['new']['display'])->toBe('refunded')
        ->and($row['is_nested'])->toBeFalse()
        ->and($row['group'])->toBe('status');
});

it('marks a field absent from one side rather than showing it as null', function () {
    $result = diffFor([
        'old' => [],
        'attributes' => ['status' => 'refunded'],
    ]);

    $row = rowFor($result, 'status');

    expect($row['old']['empty'])->toBeTrue()
        ->and($row['old']['display'])->toBe('-')
        ->and($row['new']['empty'])->toBeFalse();
});

it('distinguishes an explicit null from an absent value', function () {
    $row = rowFor(diffFor([
        'old' => ['deleted_at' => null],
        'attributes' => ['deleted_at' => '2026-08-10'],
    ]), 'deleted_at');

    expect($row['old']['display'])->toBe('null')
        ->and($row['old']['empty'])->toBeFalse();
});

it('renders booleans readably', function () {
    $row = rowFor(diffFor([
        'old' => ['is_active' => true],
        'attributes' => ['is_active' => false],
    ]), 'is_active');

    expect($row['old']['display'])->toBe('true')
        ->and($row['new']['display'])->toBe('false');
});

it('flattens nested payloads into dotted paths', function () {
    $result = diffFor([
        'old' => ['meta' => ['address' => ['city' => 'Leeds']]],
        'attributes' => ['meta' => ['address' => ['city' => 'York']]],
    ]);

    $row = rowFor($result, 'meta.address.city');

    expect($row)->not->toBeNull()
        ->and($row['old']['display'])->toBe('Leeds')
        ->and($row['new']['display'])->toBe('York')
        ->and($row['is_nested'])->toBeTrue()
        ->and($row['group'])->toBe('meta');
});

it('keeps a scalar list as a single value rather than splitting it', function () {
    $row = rowFor(diffFor([
        'old' => ['roles' => ['editor']],
        'attributes' => ['roles' => ['editor', 'admin']],
    ]), 'roles');

    expect($row)->not->toBeNull()
        ->and($row['new']['display'])->toContain('admin');
});

it('sorts fields so the diff order is stable', function () {
    $result = diffFor([
        'old' => ['zulu' => 1, 'alpha' => 1],
        'attributes' => ['zulu' => 2, 'alpha' => 2],
    ]);

    expect(array_column($result['rows'], 'field'))->toBe(['alpha', 'zulu']);
});

it('separates non-diff properties into metadata', function () {
    $result = diffFor([
        'old' => ['status' => 'paid'],
        'attributes' => ['status' => 'refunded'],
        'ticket' => 'SEC-42',
    ]);

    expect(array_column($result['rows'], 'field'))->toBe(['status'])
        ->and(collect($result['metadata'])->firstWhere('field', 'ticket')['value']['display'])->toBe('SEC-42');
});

it('marks long values expandable and summarises them', function () {
    config()->set('filament-logger.diff.collapse_after', 20);

    $row = rowFor(diffFor([
        'old' => ['note' => str_repeat('a', 100)],
        'attributes' => ['note' => 'short'],
    ]), 'note');

    expect($row['old']['expandable'])->toBeTrue()
        ->and($row['old']['char_count'])->toBe(100)
        ->and($row['old']['summary'])->toContain('lines')
        ->and($row['new']['expandable'])->toBeFalse();
});

it('pretty prints embedded json when enabled', function () {
    config()->set('filament-logger.diff.pretty_print_json', true);

    $row = rowFor(diffFor([
        'old' => ['payload' => '{"a":1,"b":2}'],
        'attributes' => ['payload' => '{"a":9,"b":2}'],
    ]), 'payload');

    expect($row['old']['display'])->toContain("\n")
        ->and($row['old']['line_count'])->toBeGreaterThan(1);
});

it('leaves embedded json alone when pretty printing is disabled', function () {
    config()->set('filament-logger.diff.pretty_print_json', false);

    $row = rowFor(diffFor([
        'old' => ['payload' => '{"a":1,"b":2}'],
        'attributes' => ['payload' => '{"a":9}'],
    ]), 'payload');

    expect($row['old']['display'])->toBe('{"a":1,"b":2}')
        ->and($row['old']['line_count'])->toBe(1);
});

it('redacts sensitive values inside the diff', function () {
    $row = rowFor(diffFor([
        'old' => ['password' => 'old-secret'],
        'attributes' => ['password' => 'new-secret'],
    ]), 'password');

    expect($row['old']['display'])->toBe('[REDACTED]')
        ->and($row['new']['display'])->toBe('[REDACTED]');
});

it('handles an activity with no properties at all', function () {
    expect(diffFor([]))->toBe(['rows' => [], 'metadata' => []]);
});
