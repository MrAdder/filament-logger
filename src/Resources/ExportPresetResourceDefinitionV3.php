<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Forms\Form;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;

/**
 * The Filament 3 shape of the export preset resource.
 *
 * Filament 3 takes a Form rather than a Schema, keeps its actions under
 * Tables\Actions, and configures them with actions()/bulkActions() instead of
 * recordActions()/toolbarActions().
 */
trait ExportPresetResourceDefinitionV3
{
    use ExportPresetResourceCommon;

    public static function form(Form $form): Form
    {
        return $form->schema(static::presetFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::presetTableColumns())
            ->filters([])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
