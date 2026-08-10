<?php

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use MrAdder\FilamentLogger\Models\ExportPreset;
use MrAdder\FilamentLogger\Resources\ExportPresetResource;
use MrAdder\FilamentLogger\Resources\ExportPresetResourceV3;
use MrAdder\FilamentLogger\Resources\ExportPresetResourceV4;

/**
 * The resource has a per-Filament-version definition. These tests build its
 * schema and table on whichever version is installed, which is what catches a
 * v4-only API leaking into the shared trait.
 */
it('resolves to the definition for the installed filament version', function () {
    $expected = class_exists(Schema::class)
        ? ExportPresetResourceV4::class
        : ExportPresetResourceV3::class;

    expect(is_a(ExportPresetResource::class, $expected, true))->toBeTrue()
        ->and(is_a(ExportPresetResource::class, Resource::class, true))->toBeTrue();
});

it('points at the export preset model', function () {
    expect(ExportPresetResource::getModel())->toBe(ExportPreset::class);
});

it('exposes translated navigation labels', function () {
    expect(ExportPresetResource::getNavigationLabel())->toBe('Export Presets')
        ->and(ExportPresetResource::getLabel())->toBe('Export preset')
        ->and(ExportPresetResource::getPluralLabel())->toBe('Export presets')
        ->and(ExportPresetResource::getNavigationIcon())->toBe('heroicon-o-table-cells');
});

it('builds its form on the installed filament version', function () {
    $schema = (new ReflectionMethod(ExportPresetResource::class, 'presetFormSchema'))->invoke(null);

    $names = array_map(fn ($field): string => $field->getName(), $schema);

    expect($names)->toBe(['key', 'label', 'icon', 'columns']);
});

it('builds its table columns on the installed filament version', function () {
    $columns = (new ReflectionMethod(ExportPresetResource::class, 'presetTableColumns'))->invoke(null);

    $names = array_map(fn ($column): string => $column->getName(), $columns);

    expect($names)->toBe(['key', 'label', 'columns', 'created_at']);
});

it('registers create, edit and list pages', function () {
    expect(array_keys(ExportPresetResource::getPages()))->toBe(['index', 'create', 'edit']);
});

it('stores and reads a preset through the model', function () {
    ExportPreset::create([
        'key' => 'high_risk',
        'label' => 'High risk',
        'columns' => ['id', 'description'],
        'filters' => ['risk' => 'high'],
        'visibility' => 'global',
    ]);

    $preset = ExportPreset::first();

    expect($preset->key)->toBe('high_risk')
        ->and($preset->columns)->toBe(['id', 'description'])
        ->and($preset->filters)->toBe(['risk' => 'high']);
});
