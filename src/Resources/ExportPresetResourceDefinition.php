<?php

namespace MrAdder\FilamentLogger\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables;
use MrAdder\FilamentLogger\Models\ExportPreset;
use UnitEnum;

trait ExportPresetResourceDefinition
{
    use ExportPresetResourceCommon;

    protected static ?string $model = ExportPreset::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Export Presets';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(static::presetFormSchema());
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns(static::presetTableColumns())
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
