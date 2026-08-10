<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * The Filament 4 and 5 shape of the export preset resource.
 *
 * Filament 3 moves actions to the Tables\Actions namespace and uses Form rather
 * than Schema, so it has its own definition in
 * {@see ExportPresetResourceDefinitionV3}.
 */
trait ExportPresetResourceDefinition
{
    use ExportPresetResourceCommon;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(static::presetFormSchema());
    }

    public static function table(Table $table): Table
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
}
