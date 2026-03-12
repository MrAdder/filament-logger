<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\Models\Activity as ActivityModel;
use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\Support\ActivityChangesFormatter;
use MrAdder\FilamentLogger\Support\ActivityDatePreset;
use MrAdder\FilamentLogger\Support\LogDataSanitizer;
use MrAdder\FilamentLogger\Resources\ActivityResource\Pages;
use Throwable;

class ActivityResource extends Resource
{
    private const DEFAULT_DATETIME_FORMAT = 'd/m/Y H:i:s';

    protected static ?string $label = 'Activity Log';
    protected static ?string $slug = 'activity-logs';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-list';


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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make(__('filament-logger::filament-logger.resource.label.log'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('causer.name')
                            ->label(__('filament-logger::filament-logger.resource.label.user'))
                            ->placeholder('-'),

                        TextEntry::make('subject_type')
                            ->label(__('filament-logger::filament-logger.resource.label.subject'))
                            ->formatStateUsing(function ($state, ActivityModel $record): string {
                                if (! $state) {
                                    return '-';
                                }

                                return Str::of($state)->afterLast('\\')->headline().' # '.$record->subject_id;
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
                            ->dateTime(config('filament-logger.datetime_format', self::DEFAULT_DATETIME_FORMAT), config('app.timezone')),
                    ]),

                InfolistSection::make(__('Changes'))
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('changes')
                            ->label(__('Changes'))
                            ->state(fn (ActivityModel $record): array => ActivityChangesFormatter::for($record))
                            ->view('filament-logger::infolists.activity-diff'),
                    ]),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make([
                    Section::make([
                        TextInput::make('causer_id')
                            ->afterStateHydrated(function ($component, ?Model $record) {
                                /** @phpstan-ignore-next-line */
                                return $component->state($record->causer?->name);
                            })
                            ->label(__('filament-logger::filament-logger.resource.label.user')),

                        TextInput::make('subject_type')
                            ->afterStateHydrated(function ($component, ?Model $record, $state) {
                                /** @var Activity&ActivityModel $record */
                                return $state ? $component->state(Str::of($state)->afterLast('\\')->headline().' # '.$record->subject_id) : '-';
                            })
                            ->label(__('filament-logger::filament-logger.resource.label.subject')),

                        Textarea::make('description')
                            ->label(__('filament-logger::filament-logger.resource.label.description'))
                            ->rows(2)
                            ->columnSpan('full'),
                    ])
                    ->columns(2),
                ])
                ->columnSpan(['sm' => 3]),

                Group::make([
                    Section::make([
                        Placeholder::make('log_name')
                            ->content(function (?Model $record): string {
                                /** @var Activity&ActivityModel $record */
                                return $record->log_name ? ucwords($record->log_name) : '-';
                            })
                            ->label(__('filament-logger::filament-logger.resource.label.type')),

                        Placeholder::make('event')
                            ->content(function (?Model $record): string {
                                /** @phpstan-ignore-next-line */
                                return $record?->event ? ucwords($record?->event) : '-';
                            })
                            ->label(__('filament-logger::filament-logger.resource.label.event')),

                        Placeholder::make('created_at')
                            ->label(__('filament-logger::filament-logger.resource.label.logged_at'))
                            ->content(function (?Model $record): string {
                                /** @var Activity&ActivityModel $record */
                                return $record->created_at ? "{$record->created_at->format(config('filament-logger.datetime_format', self::DEFAULT_DATETIME_FORMAT))}" : '-';
                            }),
                    ])
                ]),
                Section::make()
                    ->columns()
                    ->visible(fn (?Model $record): bool => static::hasPropertySection($record))
                    ->schema(fn (?Model $record): array => static::getPropertySectionSchema($record)),
            ])
            ->columns(['sm' => 4, 'lg' => null]);
    }

    protected static function hasPropertySection(?Model $record): bool
    {
        return $record instanceof ActivityModel && $record->properties->count() > 0;
    }

    /**
     * @return array<int, KeyValue>
     */
    protected static function getPropertySectionSchema(?Model $record): array
    {
        if (! $record instanceof ActivityModel) {
            return [];
        }

        /** @var Activity&ActivityModel $record */
        $properties = LogDataSanitizer::sanitizeProperties(
            $record->properties->except(['attributes', 'old'])
        );

        $schema = [];

        if (count($properties) > 0) {
            $schema[] = KeyValue::make('properties')
                ->afterStateHydrated(fn (KeyValue $component) => $component->state($properties))
                ->label(__('filament-logger::filament-logger.resource.label.properties'))
                ->columnSpan('full');
        }

        if ($old = LogDataSanitizer::sanitizeProperties($record->properties->get('old') ?? [])) {
            $schema[] = KeyValue::make('old')
                ->afterStateHydrated(fn (KeyValue $component) => $component->state($old))
                ->label(__('filament-logger::filament-logger.resource.label.old'));
        }

        if ($attributes = LogDataSanitizer::sanitizeProperties($record->properties->get('attributes') ?? [])) {
            $schema[] = KeyValue::make('attributes')
                ->afterStateHydrated(fn (KeyValue $component) => $component->state($attributes))
                ->label(__('filament-logger::filament-logger.resource.label.new'));
        }

        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_name')
                    ->badge()
                    ->colors(static::getLogNameColors())
                    ->label(__('filament-logger::filament-logger.resource.label.type'))
                    ->formatStateUsing(fn ($state) => ucwords($state))
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
                        if (!$state) {
                            return '-';
                        }
                        return Str::of($state)->afterLast('\\')->headline().' # '.$record->subject_id;
                    }),

                TextColumn::make('causer.name')
                    ->label(__('filament-logger::filament-logger.resource.label.user')),

                TextColumn::make('created_at')
                    ->label(__('filament-logger::filament-logger.resource.label.logged_at'))
                    ->dateTime(config('filament-logger.datetime_format', self::DEFAULT_DATETIME_FORMAT), config('app.timezone'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([])
            ->filters([
                SelectFilter::make('log_name')
                    ->label(__('filament-logger::filament-logger.resource.label.type'))
                    ->options(static::getLogNameList()),

                SelectFilter::make('subject_type')
                    ->label(__('filament-logger::filament-logger.resource.label.subject_type'))
                    ->options(static::getSubjectTypeList()),

                Filter::make('risk')
                    ->form([
                        Select::make('risk')
                            ->label('Risk')
                            ->options([
                                'high' => 'High',
                                'medium' => 'Medium',
                                'low' => 'Low',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['risk'] ?? null)) {
                            return $query;
                        }

                        return $query->where('properties->risk', $data['risk']);
                    }),

                Filter::make('properties->old')
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['old'] ?? null)) {
                            return null;
                        }

                        return __('filament-logger::filament-logger.resource.label.old_attributes') . $data['old'];
                    })
                    ->form([
                        TextInput::make('old')
                            ->label(__('filament-logger::filament-logger.resource.label.old'))
                            ->hint(__('filament-logger::filament-logger.resource.label.properties_hint')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['old'] ?? null)) {
                            return $query;
                        }

                        return $query->where('properties->old', 'like', "%{$data['old']}%");
                    }),

                Filter::make('properties->attributes')
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['new'] ?? null)) {
                            return null;
                        }

                        return __('filament-logger::filament-logger.resource.label.new_attributes') . $data['new'];
                    })
                    ->form([
                        TextInput::make('new')
                            ->label(__('filament-logger::filament-logger.resource.label.new'))
                            ->hint(__('filament-logger::filament-logger.resource.label.properties_hint')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['new'] ?? null)) {
                            return $query;
                        }

                        return $query->where('properties->attributes', 'like', "%{$data['new']}%");
                    }),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('logged_at')
                            ->label(__('filament-logger::filament-logger.resource.label.logged_at'))
                            ->displayFormat(config('filament-logger.date_format', 'd/m/Y')),
                        Select::make('preset')
                            ->label('Date Preset')
                            ->options(ActivityDatePreset::options()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $query->when(
                            $data['logged_at'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', $date),
                        );

                        return ActivityDatePreset::apply($query, $data['preset'] ?? null);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }

    public static function getModel(): string
    {
        return ActivitylogServiceProvider::determineActivityModel();
    }

    protected static function getSubjectTypeList(): array
    {
        $subjects = [];

        if (config('filament-logger.resources.enabled', true)) {
            $exceptResources = [...config('filament-logger.resources.exclude'), config('filament-logger.activity_resource')];
            $removedExcludedResources = collect(Filament::getResources())->filter(function ($resource) use ($exceptResources) {
                return ! in_array($resource, $exceptResources);
            });
            foreach ($removedExcludedResources as $resource) {
                $model = $resource::getModel();
                $subjects[$model] = Str::of(class_basename($model))->headline();
            }
        }

        try {
            static::getModel()::query()
                ->whereNotNull('subject_type')
                ->distinct()
                ->pluck('subject_type')
                ->each(function (?string $subjectType) use (&$subjects): void {
                    if (blank($subjectType)) {
                        return;
                    }

                    $subjects[$subjectType] ??= Str::of(class_basename($subjectType))->headline();
                });
        } catch (Throwable) {
            // Ignore before the activity table exists.
        }

        return $subjects;
    }

    protected static function getLogNameList(): array
    {
        $customs = [];

        foreach (config('filament-logger.custom') ?? [] as $custom) {
            $customs[$custom['log_name']] = $custom['log_name'];
        }

        $customEventLogName = config('filament-logger.custom_events.default_log_name');

        if (filled($customEventLogName)) {
            $customs[$customEventLogName] = $customEventLogName;
        }

        try {
            static::getModel()::query()
                ->whereNotNull('log_name')
                ->distinct()
                ->pluck('log_name')
                ->each(function (?string $logName) use (&$customs): void {
                    if (blank($logName)) {
                        return;
                    }

                    $customs[$logName] ??= $logName;
                });
        } catch (Throwable) {
            // Ignore before the activity table exists.
        }

        return array_merge(
            config('filament-logger.resources.enabled') ? [
                config('filament-logger.resources.log_name') => config('filament-logger.resources.log_name'),
            ] : [],
            config('filament-logger.models.enabled') ? [
                config('filament-logger.models.log_name') => config('filament-logger.models.log_name'),
            ] : [],
            config('filament-logger.access.enabled')
                ? [config('filament-logger.access.log_name') => config('filament-logger.access.log_name')]
                : [],
            config('filament-logger.notifications.enabled') ? [
                config('filament-logger.notifications.log_name') => config('filament-logger.notifications.log_name'),
            ] : [],
            $customs,
        );
    }

    protected static function getLogNameColors(): array
    {
        $customs = [];

        if (filled(config('filament-logger.custom_events.color')) && filled(config('filament-logger.custom_events.default_log_name'))) {
            $customs[config('filament-logger.custom_events.color')] = config('filament-logger.custom_events.default_log_name');
        }

        foreach (config('filament-logger.custom') ?? [] as $custom) {
            if (filled($custom['color'] ?? null)) {
                $customs[$custom['color']] = $custom['log_name'];
            }
        }

        return array_merge(
            (config('filament-logger.resources.enabled') && config('filament-logger.resources.color')) ? [
                config('filament-logger.resources.color') => config('filament-logger.resources.log_name'),
            ] : [],
            (config('filament-logger.models.enabled') && config('filament-logger.models.color')) ? [
                config('filament-logger.models.color') => config('filament-logger.models.log_name'),
            ] : [],
            (config('filament-logger.access.enabled') && config('filament-logger.access.color')) ? [
                config('filament-logger.access.color') => config('filament-logger.access.log_name'),
            ] : [],
            (config('filament-logger.notifications.enabled') &&  config('filament-logger.notifications.color')) ? [
                config('filament-logger.notifications.color') => config('filament-logger.notifications.log_name'),
            ] : [],
            $customs,
        );
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
        return __(config('filament-logger.resources.navigation_group','Settings'));
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
        return config('filament-logger.navigation_sort', null);
    }

    protected static function hasRequiredPolicyAbility(string $ability): bool
    {
        if (! config('filament-logger.authorization.strict', true)) {
            return true;
        }

        $policy = Gate::getPolicyFor(static::getModel());

        return ($policy !== null) && method_exists($policy, $ability);
    }
}
