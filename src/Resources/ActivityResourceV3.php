<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Infolist;
use Filament\Tables\Filters\Filter;

class ActivityResourceV3 extends BaseActivityResource
{
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            static::makeInfolistSection(__('filament-logger::filament-logger.resource.label.log'))
                ->columns(2)
                ->schema(static::getInfolistEntries()),
            static::makeInfolistSection(__('Changes'))
                ->columnSpanFull()
                ->schema(static::getChangeEntries()),
        ]);
    }

    protected static function configureFilterFields(Filter $filter, array $fields): Filter
    {
        return $filter->schema($fields);
    }

    protected static function makeInfolistSection(string $label): InfolistSection
    {
        return InfolistSection::make($label);
    }
}
