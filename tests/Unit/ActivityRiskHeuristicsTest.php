<?php

use MrAdder\FilamentLogger\Support\ActivityRiskResolver;

function resolver(): ActivityRiskResolver
{
    return app(ActivityRiskResolver::class);
}

function enrichChange(string $event, array $changed): array
{
    return resolver()->enrich($event, ['attributes' => $changed]);
}

it('keeps classifying destructive events as high risk', function () {
    $properties = resolver()->enrich('Deleted');

    expect($properties['risk'])->toBe('high')
        ->and($properties['risk_reasons'])->toContain('destructive');
});

it('keeps classifying failed logins as high risk', function () {
    $properties = resolver()->enrich('Failed Login');

    expect($properties['risk'])->toBe('high')
        ->and($properties['risk_reasons'])->toContain('auth_failure');
});

it('keeps classifying role changes as high risk', function () {
    $properties = enrichChange('Updated', ['roles' => ['admin']]);

    expect($properties['risk'])->toBe('high')
        ->and($properties['risk_reasons'])->toContain('role_change');
});

it('flags permission changes', function () {
    $properties = enrichChange('Updated', ['is_super_admin' => true]);

    expect($properties['risk_reasons'])->toContain('permission_change')
        ->and($properties['risk'])->toBe('high');
});

it('flags credential changes', function () {
    $properties = enrichChange('Updated', ['email' => 'new@example.test']);

    expect($properties['risk_reasons'])->toContain('credential_change')
        ->and($properties['risk'])->toBe('high');
});

it('flags two factor changes', function () {
    $properties = enrichChange('Updated', ['two_factor_secret' => '[REDACTED]']);

    expect($properties['risk_reasons'])->toContain('two_factor_change')
        ->and($properties['risk'])->toBe('high');
});

it('flags account status changes as medium rather than high', function () {
    $properties = enrichChange('Updated', ['suspended_at' => '2026-08-10 10:00:00']);

    expect($properties['risk_reasons'])->toContain('account_status_change')
        ->and($properties['risk'])->toBe('medium');
});

it('takes the most severe level when several heuristics match', function () {
    $properties = enrichChange('Updated', [
        'suspended_at' => '2026-08-10 10:00:00',
        'email' => 'new@example.test',
    ]);

    expect($properties['risk_reasons'])
        ->toContain('account_status_change')
        ->toContain('credential_change')
        ->and($properties['risk'])->toBe('high');
});

it('flags heuristics that match on the event name', function () {
    $properties = resolver()->enrich('Two Factor Recovery');

    expect($properties['risk_reasons'])->toContain('two_factor_change');
});

it('leaves ordinary changes unclassified', function () {
    $properties = enrichChange('Updated', ['name' => 'Renamed', 'notes' => 'x']);

    expect($properties)->not->toHaveKey('risk')
        ->and($properties)->not->toHaveKey('risk_reasons');
});

it('never overrides an explicit risk level', function () {
    $properties = resolver()->enrich('Deleted', [], [], explicitRisk: 'low');

    expect($properties['risk'])->toBe('low');
});

it('can have a heuristic disabled through config', function () {
    config()->set('filament-logger.risk.heuristics.credential_change', []);

    $properties = enrichChange('Updated', ['email' => 'new@example.test']);

    expect($properties['risk_reasons'] ?? [])->not->toContain('credential_change');
});

it('supports configuring medium risk events', function () {
    config()->set('filament-logger.risk.medium.events', ['Viewed']);

    expect(resolver()->enrich('Viewed')['risk'])->toBe('medium');
});
