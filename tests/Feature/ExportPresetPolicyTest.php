<?php

use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\Models\ExportPreset;
use MrAdder\FilamentLogger\Policies\ExportPresetPolicy;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;

function presetPolicy(): ExportPresetPolicy
{
    return new ExportPresetPolicy;
}

function makePreset(): ExportPreset
{
    return ExportPreset::create([
        'key' => 'high_risk',
        'label' => 'High risk',
        'columns' => ['id'],
        'visibility' => 'global',
    ]);
}

function grantManageAbility(bool $granted): TestUser
{
    Gate::define(
        config('filament-logger.exports.manage_ability', 'manageExportPresets'),
        fn ($user = null): bool => $granted,
    );

    return TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);
}

it('allows every action for a user holding the manage ability', function () {
    $user = grantManageAbility(true);
    $preset = makePreset();

    expect(presetPolicy()->viewAny($user))->toBeTrue()
        ->and(presetPolicy()->view($user, $preset))->toBeTrue()
        ->and(presetPolicy()->create($user))->toBeTrue()
        ->and(presetPolicy()->update($user, $preset))->toBeTrue()
        ->and(presetPolicy()->delete($user, $preset))->toBeTrue()
        ->and(presetPolicy()->restore($user, $preset))->toBeTrue()
        ->and(presetPolicy()->forceDelete($user, $preset))->toBeTrue();
});

it('denies every action for a user without the manage ability', function () {
    $user = grantManageAbility(false);
    $preset = makePreset();

    expect(presetPolicy()->viewAny($user))->toBeFalse()
        ->and(presetPolicy()->view($user, $preset))->toBeFalse()
        ->and(presetPolicy()->create($user))->toBeFalse()
        ->and(presetPolicy()->update($user, $preset))->toBeFalse()
        ->and(presetPolicy()->delete($user, $preset))->toBeFalse()
        ->and(presetPolicy()->restore($user, $preset))->toBeFalse()
        ->and(presetPolicy()->forceDelete($user, $preset))->toBeFalse();
});

it('honours a custom manage ability from config', function () {
    config()->set('filament-logger.exports.manage_ability', 'administerAuditPresets');

    Gate::define('administerAuditPresets', fn ($user = null): bool => true);

    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    expect(presetPolicy()->viewAny($user))->toBeTrue();
});

it('denies when the configured ability is not defined at all', function () {
    config()->set('filament-logger.exports.manage_ability', 'neverDefinedAbility');

    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    expect(presetPolicy()->viewAny($user))->toBeFalse();
});
