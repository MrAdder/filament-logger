<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use MrAdder\FilamentLogger\Models\ExportPreset;
use MrAdder\FilamentLogger\Support\ActivityExportPresetManager;

/**
 * Everything about the export preset resource that does not depend on the
 * Filament major version.
 *
 * Navigation is exposed through methods rather than the usual static
 * properties, because Filament 3 and Filament 4+ declare those properties with
 * different types and redeclaring one with a mismatched type is a fatal error.
 */
trait ExportPresetResourceCommon
{
    public static function getModel(): string
    {
        return ExportPreset::class;
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-table-cells';
    }

    public static function getNavigationGroup(): ?string
    {
        return __(config('filament-logger.resources.navigation_group', 'Settings'));
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-logger::filament-logger.nav.export_presets.label');
    }

    public static function getLabel(): string
    {
        return __('filament-logger::filament-logger.resource.label.export_preset');
    }

    public static function getPluralLabel(): string
    {
        return __('filament-logger::filament-logger.resource.label.export_presets');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ExportPresetResource\Pages\ListExportPresets::route('/'),
            'create' => ExportPresetResource\Pages\CreateExportPreset::route('/create'),
            'edit' => ExportPresetResource\Pages\EditExportPreset::route('/{record}/edit'),
        ];
    }

    /**
     * Shared form schema for export presets.
     *
     * @return array<int, mixed>
     */
    protected static function presetFormSchema(): array
    {
        return [
            TextInput::make('key')
                ->label(static::presetFieldLabel('key'))
                ->required()
                ->unique(ExportPreset::class, 'key'),
            TextInput::make('label')
                ->label(static::presetFieldLabel('label'))
                ->required(),
            TextInput::make('icon')
                ->label(static::presetFieldLabel('icon')),
            Select::make('columns')
                ->label(static::presetFieldLabel('columns'))
                ->multiple()
                ->options(ActivityExportPresetManager::columnOptions())
                ->required(),
        ];
    }

    /**
     * Shared table columns for export presets.
     *
     * @return array<int, TextColumn>
     */
    protected static function presetTableColumns(): array
    {
        return [
            TextColumn::make('key')
                ->label(static::presetFieldLabel('key'))
                ->searchable(),
            TextColumn::make('label')
                ->label(static::presetFieldLabel('label'))
                ->searchable(),
            TextColumn::make('columns')
                ->label(static::presetFieldLabel('columns'))
                ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(', ', $state) : (string) $state),
            TextColumn::make('created_at')
                ->label(static::presetFieldLabel('created'))
                ->dateTime(),
        ];
    }

    protected static function presetFieldLabel(string $key): string
    {
        return __("filament-logger::filament-logger.field.{$key}");
    }
}
