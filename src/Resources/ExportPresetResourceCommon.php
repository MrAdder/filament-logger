<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\MultiSelect;
use MrAdder\FilamentLogger\Models\ExportPreset;

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
            TextInput::make('key')->required()->unique(ExportPreset::class, 'key'),
            TextInput::make('label')->required(),
            TextInput::make('icon'),
            MultiSelect::make('columns')
                ->options(collect(config('filament-logger.exports.columns'))->mapWithKeys(fn($c) => [$c => $c])->toArray())
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
            TextColumn::make('key')->label('Key')->searchable(),
            TextColumn::make('label')->label('Label')->searchable(),
            TextColumn::make('columns')->label('Columns')->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state),
            TextColumn::make('created_at')->label('Created')->dateTime(),
        ];
    }
}
