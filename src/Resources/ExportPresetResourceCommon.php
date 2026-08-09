<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use MrAdder\FilamentLogger\Models\ExportPreset;
use MrAdder\FilamentLogger\Support\ActivityExportPresetManager;

trait ExportPresetResourceCommon
{
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
