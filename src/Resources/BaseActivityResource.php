<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MrAdder\FilamentLogger\Resources\ActivityResource\Support\ActivityResourceTableOptions;
use MrAdder\FilamentLogger\Support\ActivityChangesFormatter;
use MrAdder\FilamentLogger\Support\ActivityDatePreset;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Activity as ActivityModel;

abstract class BaseActivityResource extends AbstractActivityResource
{
    /**
     * Registered by FilamentLoggerServiceProvider::configurePackage() via
     * hasViews(). Larastan builds its view-string list from the view finder's
     * directories and drops the namespace prefix, so it cannot recognise a
     * namespaced package view — see phpstan-baseline.neon.
     */
    protected const CHANGES_VIEW = 'filament-logger::infolists.activity-diff';

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::getTableColumns())
            ->defaultSort('created_at', 'desc')
            ->filters(static::getTableFilters());
    }

    /**
     * @return array<int, TextColumn>
     */
    protected static function getTableColumns(): array
    {
        return [
            TextColumn::make('log_name')
                ->badge()
                ->colors(ActivityResourceTableOptions::logNameColors())
                ->label(static::resourceLabel('type'))
                ->formatStateUsing(fn (?string $state): string => static::formatTitleCaseState($state))
                ->sortable(),

            TextColumn::make('event')
                ->label(static::resourceLabel('event'))
                ->sortable(),

            TextColumn::make('properties.risk')
                ->label(static::resourceLabel('risk'))
                ->badge()
                ->toggleable()
                ->color(fn (?string $state): string => match ($state) {
                    'high' => 'danger',
                    'medium' => 'warning',
                    default => 'gray',
                })
                ->formatStateUsing(fn (?string $state): string => static::riskLabel($state)),

            TextColumn::make('description')
                ->label(static::resourceLabel('description'))
                ->toggleable()
                ->toggledHiddenByDefault()
                ->wrap(),

            TextColumn::make('subject_type')
                ->label(static::resourceLabel('subject'))
                ->formatStateUsing(fn ($state, Model $record): string => static::formatSubjectState($state, $record)),

            TextColumn::make('causer.name')
                ->label(static::resourceLabel('user')),

            TextColumn::make('created_at')
                ->label(static::resourceLabel('logged_at'))
                ->dateTime(static::defaultDateTimeFormat(), config('app.timezone'))
                ->sortable(),
        ];
    }

    /**
     * @return array<int, SelectFilter|Filter>
     */
    protected static function getTableFilters(): array
    {
        return [
            // Search across high-value fields: description, causer name, subject type and properties
            Filter::make('search')
                ->schema([
                    TextInput::make('query')
                        ->label(static::resourceLabel('search'))
                        ->placeholder(__('filament-logger::filament-logger.resource.placeholder.search')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    $search = $data['query'] ?? null;

                    if (! filled($search)) {
                        return $query;
                    }

                    return $query->where(function (Builder $q) use ($search) {
                        $q->where('description', 'like', "%{$search}%")
                            ->orWhere('subject_type', 'like', "%{$search}%")
                            ->orWhereHas('causer', function (Builder $q2) use ($search) {
                                $q2->where('name', 'like', "%{$search}%");
                            });

                        // A LIKE over the JSON properties column can never use
                        // an index, so it is opt-out for large activity tables.
                        if (config('filament-logger.search.include_properties', true)) {
                            $q->orWhere('properties', 'like', "%{$search}%");
                        }
                    });
                }),
            SelectFilter::make('log_name')
                ->label(static::resourceLabel('type'))
                ->options(ActivityResourceTableOptions::logNames()),

            SelectFilter::make('subject_type')
                ->label(static::resourceLabel('subject_type'))
                ->options(ActivityResourceTableOptions::subjectTypes()),

            static::configureFilterFields(
                Filter::make('risk')
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['risk'] ?? null)) {
                            return $query;
                        }

                        return $query->where('properties->risk', $data['risk']);
                    }),
                [
                    Select::make('risk')
                        ->label(static::resourceLabel('risk'))
                        ->options(static::riskOptions()),
                ],
            ),

            static::makePropertyValueFilter('properties->old', 'old', 'old', 'old_attributes'),

            static::makePropertyValueFilter('properties->attributes', 'new', 'new', 'new_attributes'),

            static::configureFilterFields(
                Filter::make('created_at')
                    ->query(function (Builder $query, array $data): Builder {
                        $query->when(
                            $data['logged_at'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', $date),
                        );

                        return ActivityDatePreset::apply($query, $data['preset'] ?? null);
                    }),
                [
                    DatePicker::make('logged_at')
                        ->label(static::resourceLabel('logged_at'))
                        ->displayFormat(config('filament-logger.date_format', 'd/m/Y')),
                    Select::make('preset')
                        ->label(static::resourceLabel('date_preset'))
                        ->options(ActivityDatePreset::options()),
                ],
            ),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function getInfolistEntries(): array
    {
        return [
            TextEntry::make('causer.name')
                ->label(static::resourceLabel('user'))
                ->placeholder('-'),

            TextEntry::make('subject_type')
                ->label(static::resourceLabel('subject'))
                ->formatStateUsing(fn ($state, Model $record): string => static::formatSubjectState($state, $record)),

            TextEntry::make('description')
                ->label(static::resourceLabel('description'))
                ->columnSpanFull()
                ->copyable()
                ->prose(),

            static::makeBadgeTextEntry('log_name', 'type'),

            static::makeBadgeTextEntry('event', 'event'),

            TextEntry::make('created_at')
                ->label(static::resourceLabel('logged_at'))
                ->dateTime(static::defaultDateTimeFormat(), config('app.timezone')),
        ];
    }

    /**
     * @return array<int, ViewEntry>
     */
    protected static function getChangeEntries(): array
    {
        return [
            ViewEntry::make('changes')
                ->label(static::resourceLabel('changes'))
                ->hiddenLabel()
                ->state(fn (ActivityModel $record): array => ActivityChangesFormatter::for($record))
                ->view(self::CHANGES_VIEW),
        ];
    }

    /**
     * @param  array<int, mixed>  $fields
     */
    abstract protected static function configureFilterFields(Filter $filter, array $fields): Filter;

    /**
     * @return mixed
     */
    abstract protected static function makeInfolistSection(string $label);

    protected static function makePropertyValueFilter(
        string $propertyPath,
        string $field,
        string $labelKey,
        string $indicatorKey,
    ): Filter {
        return static::configureFilterFields(
            Filter::make($propertyPath)
                ->indicateUsing(function (array $data) use ($field, $indicatorKey): ?string {
                    $value = $data[$field] ?? null;

                    if (! $value) {
                        return null;
                    }

                    return static::resourceLabel($indicatorKey).$value;
                })
                ->query(function (Builder $query, array $data) use ($field, $propertyPath): Builder {
                    $value = $data[$field] ?? null;

                    if (! $value) {
                        return $query;
                    }

                    return $query->where($propertyPath, 'like', "%{$value}%");
                }),
            [
                TextInput::make($field)
                    ->label(static::resourceLabel($labelKey))
                    ->hint(static::resourceLabel('properties_hint')),
            ],
        );
    }

    protected static function makeBadgeTextEntry(string $name, string $labelKey): TextEntry
    {
        return TextEntry::make($name)
            ->label(static::resourceLabel($labelKey))
            ->badge()
            ->formatStateUsing(fn (?string $state): string => static::formatTitleCaseState($state));
    }

    protected static function formatSubjectState(?string $state, Model $record): string
    {
        /** @var Activity&ActivityModel $record */
        if (! $state) {
            return '-';
        }

        return Str::of($state)->afterLast('\\')->headline().' # '.$record->subject_id;
    }

    protected static function formatTitleCaseState(?string $state): string
    {
        return $state ? ucwords($state) : '-';
    }

    protected static function resourceLabel(string $key): string
    {
        return __("filament-logger::filament-logger.resource.label.{$key}");
    }

    /**
     * @return array<string, string>
     */
    protected static function riskOptions(): array
    {
        return [
            'high' => __('filament-logger::filament-logger.risk.high'),
            'medium' => __('filament-logger::filament-logger.risk.medium'),
            'low' => __('filament-logger::filament-logger.risk.low'),
        ];
    }

    protected static function riskLabel(?string $state): string
    {
        if (! $state) {
            return '-';
        }

        return static::riskOptions()[$state] ?? Str::headline($state);
    }
}
