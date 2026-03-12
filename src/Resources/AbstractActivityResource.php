<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\Resources\ActivityResource\Pages;
use Spatie\Activitylog\ActivitylogServiceProvider;

abstract class AbstractActivityResource extends Resource
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

    protected static function hasRequiredPolicyAbility(string $ability): bool
    {
        if (! config('filament-logger.authorization.strict', true)) {
            return true;
        }

        $policy = Gate::getPolicyFor(static::getModel());

        return ($policy !== null) && method_exists($policy, $ability);
    }
}
