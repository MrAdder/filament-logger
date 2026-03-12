<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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

class ActivityResourceV4 extends BaseActivityResource
{
    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('filament-logger::filament-logger.resource.label.log'))
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
                ]),

            Section::make(__('Changes'))
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('changes')
                        ->label(__('Changes'))
                        ->state(fn (ActivityModel $record): array => ActivityChangesFormatter::for($record))
                        ->view('filament-logger::infolists.activity-diff'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('log_name')
                    ->label(__('filament-logger::filament-logger.resource.label.type'))
                    ->options(ActivityResourceTableOptions::logNames()),

                SelectFilter::make('subject_type')
                    ->label(__('filament-logger::filament-logger.resource.label.subject_type'))
                    ->options(ActivityResourceTableOptions::subjectTypes()),

                Filter::make('risk')
                    ->schema([
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
                    ->schema([
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
                    ->schema([
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
                    ->schema([
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
}
