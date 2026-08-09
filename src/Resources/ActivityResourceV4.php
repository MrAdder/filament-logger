<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\Filter;

class ActivityResourceV4 extends BaseActivityResource
{
    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            static::makeInfolistSection(__('filament-logger::filament-logger.resource.label.log'))
                ->columns(2)
                ->schema(static::getInfolistEntries()),
            static::makeInfolistSection(__('filament-logger::filament-logger.resource.label.changes'))
                ->columnSpanFull()
                ->schema(static::getChangeEntries()),
        ]);
    }

    /**
     * @param  array<int, mixed>  $fields
     */
    protected static function configureFilterFields(Filter $filter, array $fields): Filter
    {
        return $filter->schema($fields);
    }

    protected static function makeInfolistSection(string $label): Section
    {
        return Section::make($label);
    }
}
