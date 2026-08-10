<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Support;

use Filament\Forms\Components\KeyValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use MrAdder\FilamentLogger\Support\ActivityChangesFormatter;
use MrAdder\FilamentLogger\Support\ActivityViewerPrivacy;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * Builds the key-value property sections that the activity detail page used
 * before it moved to the structured diff view.
 *
 * Nothing in the package calls this any more — the detail page now renders
 * {@see ActivityChangesFormatter} through a
 * ViewEntry. It is kept because removing a public class mid-`1.x` would be a
 * breaking change.
 *
 * @deprecated since 1.6, scheduled for removal in 2.0. Use the activity detail
 *             page's diff view, or ActivityDisplay::infolistEntriesUsing() to
 *             add your own entries.
 */
final class ActivityResourceFormSchema
{
    public static function hasPropertySection(?Model $record): bool
    {
        return self::propertiesOf($record)->isNotEmpty();
    }

    /**
     * An activity recorded with no properties casts to null rather than an
     * empty collection, so every caller goes through this.
     *
     * @return Collection<string, mixed>
     */
    protected static function propertiesOf(?Model $record): Collection
    {
        if (! $record instanceof ActivityModel) {
            return collect();
        }

        /** @var Activity&ActivityModel $record */
        $properties = $record->properties;

        return $properties instanceof Collection ? $properties : collect();
    }

    /**
     * @return array<int, KeyValue>
     */
    public static function propertySection(?Model $record): array
    {
        if (! $record instanceof ActivityModel) {
            return [];
        }

        $all = self::propertiesOf($record);

        /** @var Activity&ActivityModel $record */
        $properties = ActivityViewerPrivacy::sanitizeProperties(
            $all->except(['attributes', 'old']), $record);

        $schema = [];

        if (count($properties) > 0) {
            $schema[] = KeyValue::make('properties')
                ->afterStateHydrated(fn (KeyValue $component) => $component->state($properties))
                ->label(__('filament-logger::filament-logger.resource.label.properties'))
                ->columnSpan('full');
        }

        if ($old = ActivityViewerPrivacy::sanitizeProperties($all->get('old') ?? [], $record)) {
            $schema[] = KeyValue::make('old')
                ->afterStateHydrated(fn (KeyValue $component) => $component->state($old))
                ->label(__('filament-logger::filament-logger.resource.label.old'));
        }

        if ($attributes = ActivityViewerPrivacy::sanitizeProperties($all->get('attributes') ?? [], $record)) {
            $schema[] = KeyValue::make('attributes')
                ->afterStateHydrated(fn (KeyValue $component) => $component->state($attributes))
                ->label(__('filament-logger::filament-logger.resource.label.new'));
        }

        return $schema;
    }
}
