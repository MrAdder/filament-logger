<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use MrAdder\FilamentLogger\Resources\ActivityResource\Pages;
use MrAdder\FilamentLogger\Resources\ActivityResource\Support\ActivityResourceTableOptions;
use MrAdder\FilamentLogger\Support\ActivityChangesFormatter;
use MrAdder\FilamentLogger\Support\ActivityDatePreset;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Activity as ActivityModel;

abstract class BaseActivityResource extends Resource
{
    private const DEFAULT_DATETIME_FORMAT = 'd/m/Y H:i:s';

    protected static ?string $label = 'Activity Log';

    protected static ?string $slug = 'activity-logs';

    public static function getCluster(): ?string
    {
        return config('filament-logger.resources.cluster');
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        if (! static::hasRequiredPolicyAbility('viewAny')) {
            return false;
        }

        return parent::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        if (! static::hasRequiredPolicyAbility('view')) {
            return false;
        }

        return parent::canView($record);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::getTableColumns())
            ->defaultSort('created_at', 'desc')
            ->filters(static::getTableFilters());
    }

    public static function getPages(): array
    {
        return [
            'index' => static::getListActivitiesPage()::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }

    public static function getModel(): string
    {
        return ActivitylogServiceProvider::determineActivityModel();
    }

    public static function getLabel(): string
    {
        return __('filament-logger::filament-logger.resource.label.log');
    }

    public static function getPluralLabel(): string
    {
        return __('filament-logger::filament-logger.resource.label.logs');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(config('filament-logger.resources.navigation_group', 'Settings'));
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-logger::filament-logger.nav.log.label');
    }

    public static function getNavigationIcon(): string
    {
        return __('filament-logger::filament-logger.nav.log.icon');
    }

    public static function isScopedToTenant(): bool
    {
        return config('filament-logger.scoped_to_tenant', true);
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-logger.navigation_sort');
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
                ->label(__('filament-logger::filament-logger.resource.label.type'))
                ->formatStateUsing(fn (?string $state): string => $state ? ucwords($state) : '-')
                ->sortable(),

            TextColumn::make('event')
                ->label(__('filament-logger::filament-logger.resource.label.event'))
                ->sortable(),

            TextColumn::make('properties.risk')
                ->label('Risk')
                ->badge()
                ->toggleable()
                ->color(fn (?string $state): string => match ($state) {
                    'high' => 'danger',
                    'medium' => 'warning',
                    default => 'gray',
                })
                ->formatStateUsing(fn (?string $state): string => $state ? Str::headline($state) : '-'),

            TextColumn::make('description')
                ->label(__('filament-logger::filament-logger.resource.label.description'))
                ->toggleable()
                ->toggledHiddenByDefault()
                ->wrap(),

            TextColumn::make('subject_type')
                ->label(__('filament-logger::filament-logger.resource.label.subject'))
                ->formatStateUsing(function ($state, Model $record) {
                    /** @var Activity&ActivityModel $record */
                    if (! $state) {
                        return '-';
                    }

                    return Str::of($state)->afterLast('\\')->headline() . ' # ' . $record->subject_id;
                }),

            TextColumn::make('causer.name')
                ->label(__('filament-logger::filament-logger.resource.label.user')),

            TextColumn::make('created_at')
                ->label(__('filament-logger::filament-logger.resource.label.logged_at'))
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
            SelectFilter::make('log_name')
                ->label(__('filament-logger::filament-logger.resource.label.type'))
                ->options(ActivityResourceTableOptions::logNames()),

            SelectFilter::make('subject_type')
                ->label(__('filament-logger::filament-logger.resource.label.subject_type'))
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
                        ->label('Risk')
                        ->options([
                            'high' => 'High',
                            'medium' => 'Medium',
                            'low' => 'Low',
                        ]),
                ],
            ),

            static::configureFilterFields(
                Filter::make('properties->old')
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['old'] ?? null)) {
                            return null;
                        }

                        return __('filament-logger::filament-logger.resource.label.old_attributes') . $data['old'];
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['old'] ?? null)) {
                            return $query;
                        }

                        return $query->where('properties->old', 'like', "%{$data['old']}%");
                    }),
                [
                    TextInput::make('old')
                        ->label(__('filament-logger::filament-logger.resource.label.old'))
                        ->hint(__('filament-logger::filament-logger.resource.label.properties_hint')),
                ],
            ),

            static::configureFilterFields(
                Filter::make('properties->attributes')
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['new'] ?? null)) {
                            return null;
                        }

                        return __('filament-logger::filament-logger.resource.label.new_attributes') . $data['new'];
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['new'] ?? null)) {
                            return $query;
                        }

                        return $query->where('properties->attributes', 'like', "%{$data['new']}%");
                    }),
                [
                    TextInput::make('new')
                        ->label(__('filament-logger::filament-logger.resource.label.new'))
                        ->hint(__('filament-logger::filament-logger.resource.label.properties_hint')),
                ],
            ),

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
                        ->label(__('filament-logger::filament-logger.resource.label.logged_at'))
                        ->displayFormat(config('filament-logger.date_format', 'd/m/Y')),
                    Select::make('preset')
                        ->label('Date Preset')
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
                ->label(__('filament-logger::filament-logger.resource.label.user'))
                ->placeholder('-'),

            TextEntry::make('subject_type')
                ->label(__('filament-logger::filament-logger.resource.label.subject'))
                ->formatStateUsing(function ($state, ActivityModel $record): string {
                    if (! $state) {
                        return '-';
                    }

                    return Str::of($state)->afterLast('\\')->headline() . ' # ' . $record->subject_id;
                }),

            TextEntry::make('description')
                ->label(__('filament-logger::filament-logger.resource.label.description'))
                ->columnSpanFull()
                ->copyable()
                ->prose(),

            TextEntry::make('log_name')
                ->label(__('filament-logger::filament-logger.resource.label.type'))
                ->badge()
                ->formatStateUsing(fn (?string $state): string => $state ? ucwords($state) : '-'),

            TextEntry::make('event')
                ->label(__('filament-logger::filament-logger.resource.label.event'))
                ->badge()
                ->formatStateUsing(fn (?string $state): string => $state ? ucwords($state) : '-'),

            TextEntry::make('created_at')
                ->label(__('filament-logger::filament-logger.resource.label.logged_at'))
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
                ->label(__('Changes'))
                ->state(fn (ActivityModel $record): array => ActivityChangesFormatter::for($record))
                ->view('filament-logger::infolists.activity-diff'),
        ];
    }

    protected static function defaultDateTimeFormat(): string
    {
        return config('filament-logger.datetime_format', self::DEFAULT_DATETIME_FORMAT);
    }

    protected static function getListActivitiesPage(): string
    {
        return class_exists(\Filament\Schemas\Schema::class)
            ? Pages\ListActivities::class
            : Pages\ListActivitiesV3::class;
    }

    abstract protected static function configureFilterFields(Filter $filter, array $fields): Filter;

    abstract protected static function makeInfolistSection(string $label);

    protected static function hasRequiredPolicyAbility(string $ability): bool
    {
        if (! config('filament-logger.authorization.strict', true)) {
            return true;
        }

        $policy = Gate::getPolicyFor(static::getModel());

        return ($policy !== null) && method_exists($policy, $ability);
    }
}
