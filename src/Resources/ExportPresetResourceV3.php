<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\MultiSelect;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use MrAdder\FilamentLogger\Models\ExportPreset;

class ExportPresetResourceV3 extends Resource
{
    protected static ?string $model = ExportPreset::class;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Export Presets';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('key')->required()->unique(ExportPreset::class, 'key'),
            TextInput::make('label')->required(),
            TextInput::make('icon'),
            MultiSelect::make('columns')
                ->options(collect(config('filament-logger.exports.columns'))->mapWithKeys(fn($c) => [$c => $c])->toArray())
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label('Key')->searchable(),
                TextColumn::make('label')->label('Label')->searchable(),
                TextColumn::make('columns')->label('Columns')->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state),
                TextColumn::make('created_at')->label('Created')->dateTime(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \MrAdder\FilamentLogger\Resources\ExportPresetResource\Pages\ListExportPresets::route('/'),
            'create' => \MrAdder\FilamentLogger\Resources\ExportPresetResource\Pages\CreateExportPreset::route('/create'),
            'edit' => \MrAdder\FilamentLogger\Resources\ExportPresetResource\Pages\EditExportPreset::route('/{record}/edit'),
        ];
    }
}
