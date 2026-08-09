<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Support;

use Filament\Forms\Components\KeyValue;
use Illuminate\Database\Eloquent\Model;
use MrAdder\FilamentLogger\Support\ActivityViewerPrivacy;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Activity as ActivityModel;

final class ActivityResourceFormSchema
{
    public static function hasPropertySection(?Model $record): bool
    {
        return $record instanceof ActivityModel && $record->properties->count() > 0;
    }

    /**
     * @return array<int, KeyValue>
     */
    public static function propertySection(?Model $record): array
    {
        if (! $record instanceof ActivityModel) {
            return [];
        }

        /** @var Activity&ActivityModel $record */
        $properties = ActivityViewerPrivacy::sanitizeProperties(
            $record->properties->except(['attributes', 'old']), $record);

        $schema = [];

        if (count($properties) > 0) {
            $schema[] = KeyValue::make('properties')
                ->afterStateHydrated(fn (KeyValue $component) => $component->state($properties))
                ->label(__('filament-logger::filament-logger.resource.label.properties'))
                ->columnSpan('full');
        }

        if ($old = ActivityViewerPrivacy::sanitizeProperties($record->properties->get('old') ?? [], $record)) {
            $schema[] = KeyValue::make('old')
                ->afterStateHydrated(fn (KeyValue $component) => $component->state($old))
                ->label(__('filament-logger::filament-logger.resource.label.old'));
        }

        if ($attributes = ActivityViewerPrivacy::sanitizeProperties($record->properties->get('attributes') ?? [], $record)) {
            $schema[] = KeyValue::make('attributes')
                ->afterStateHydrated(fn (KeyValue $component) => $component->state($attributes))
                ->label(__('filament-logger::filament-logger.resource.label.new'));
        }

        return $schema;
    }
}
