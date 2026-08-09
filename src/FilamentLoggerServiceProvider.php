<?php

namespace MrAdder\FilamentLogger;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use MrAdder\FilamentLogger\Commands\PruneActivitiesCommand;
use MrAdder\FilamentLogger\FilamentLogger as FilamentLoggerManager;
use MrAdder\FilamentLogger\Loggers\ResourceLogger;
use MrAdder\FilamentLogger\Models\ExportPreset;
use MrAdder\FilamentLogger\Policies\ExportPresetPolicy;
use MrAdder\FilamentLogger\Support\ActivityAlertDispatcher;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;
use MrAdder\FilamentLogger\Support\ActivityExporter;
use MrAdder\FilamentLogger\Support\ActivityRiskResolver;
use MrAdder\FilamentLogger\Support\ObserverRegistrar;
use MrAdder\FilamentLogger\Support\PreviousAttributesStore;
use MrAdder\FilamentLogger\Support\ReplicationContextStore;
use MrAdder\FilamentLogger\Widgets\ActivityOverviewWidget;
use MrAdder\FilamentLogger\Widgets\ActivityTrendChartWidget;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopEventsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopUsersChartWidget;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentLoggerServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-logger';

    /**
     * @return array<int, class-string>
     */
    protected function getResources(): array
    {
        return [
            config('filament-logger.activity_resource'),
            \MrAdder\FilamentLogger\Resources\ExportPresetResource::class,
        ];
    }

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasTranslations()
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                '2026_05_26_000000_create_export_presets_table',
                '2026_05_26_000001_add_filament_logger_indexes_to_activity_log_table',
            ])
            ->hasCommand(PruneActivitiesCommand::class)
            ->hasInstallCommand(function (InstallCommand $installCommand) {
                $installCommand
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('MrAdder/filament-logger')
                    ->startWith(function (InstallCommand $installCommand) {
                        $installCommand->call('vendor:publish', [
                            '--provider' => "Spatie\Activitylog\ActivitylogServiceProvider",
                            '--tag' => 'activitylog-migrations',
                        ]);
                    });
            });
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        $this->app->singleton(ActivityRiskResolver::class);
        $this->app->singleton(ActivityAlertDispatcher::class);
        $this->app->singleton(ActivityExporter::class);
        $this->app->singleton(ActivityAnalytics::class);
        $this->app->singleton(FilamentLoggerManager::class);
    }

    public function registeringPackage(): void
    {
        parent::registeringPackage();

        // Register policy for export presets so Filament + Gates honor management permissions
        Gate::policy(ExportPreset::class, ExportPresetPolicy::class);
    }

    public function bootingPackage(): void
    {
        parent::bootingPackage();

        if (config('filament-logger.access.enabled')) {
            $this->registerAuthEventListeners();
        }

        if (config('filament-logger.notifications.enabled')) {
            Event::listen(NotificationSent::class, config('filament-logger.notifications.logger'));
            Event::listen(NotificationFailed::class, config('filament-logger.notifications.logger'));
        }

        $this->configureReplicateAction();
        $this->registerOctaneStateFlushing();
    }

    /**
     * The lifecycle stores are static, so under Octane they would otherwise
     * carry state between requests handled by the same worker.
     *
     * The events are referenced by name rather than guarded with class_exists()
     * so that Octane stays an optional dependency: an event that is never
     * dispatched simply never fires its listener.
     */
    protected function registerOctaneStateFlushing(): void
    {
        $events = [
            'Laravel\\Octane\\Events\\RequestTerminated',
            'Laravel\\Octane\\Events\\TaskTerminated',
        ];

        foreach ($events as $event) {
            Event::listen($event, function (): void {
                PreviousAttributesStore::flush();
            });
        }
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        $this->registerWidgetComponents();

        if (config('filament-logger.resources.enabled', true)) {
            $exceptResources = [...config('filament-logger.resources.exclude', []), config('filament-logger.activity_resource')];

            $loggableResources = collect(Filament::getPanels())
                ->flatMap(fn (Panel $panel) => $panel->getResources())
                ->unique()
                ->filter(function ($resource) use ($exceptResources) {
                    return ! in_array($resource, $exceptResources);
                });

            foreach ($loggableResources as $resource) {
                ObserverRegistrar::register($resource::getModel(), $this->resolveResourceObserver($resource));
            }
        }

        if (config('filament-logger.models.enabled', true) && ! empty(config('filament-logger.models.register'))) {
            foreach (config('filament-logger.models.register', []) as $model) {
                ObserverRegistrar::register($model, config('filament-logger.models.logger'));
            }
        }
    }

    protected function registerAuthEventListeners(): void
    {
        $logger = config('filament-logger.access.logger');
        $events = [
            Login::class => 'login',
            Logout::class => 'logout',
            Failed::class => 'failed',
            Lockout::class => 'lockout',
            PasswordReset::class => 'password_reset',
        ];

        foreach ($events as $eventClass => $configKey) {
            if (config("filament-logger.access.events.{$configKey}", true)) {
                Event::listen($eventClass, $logger);
            }
        }

        $twoFactorRecoveryEvent = 'Laravel\\Fortify\\Events\\RecoveryCodeReplaced';

        if (
            config('filament-logger.access.events.two_factor_recovery', true) &&
            class_exists($twoFactorRecoveryEvent)
        ) {
            Event::listen($twoFactorRecoveryEvent, $logger);
        }
    }

    protected function resolveResourceObserver(string $resource): object|string
    {
        $logger = config('filament-logger.resources.logger');

        if (is_a($logger, ResourceLogger::class, true)) {
            return new $logger($resource);
        }

        return $logger;
    }

    protected function configureReplicateAction(): void
    {
        $replicateActionClass = $this->resolveReplicateActionClass();

        if ($replicateActionClass === null || ! method_exists($replicateActionClass, 'configureUsing')) {
            return;
        }

        $replicateActionClass::configureUsing(function (object $action): void {
            if (! method_exists($action, 'beforeReplicaSaved')) {
                return;
            }

            $action->beforeReplicaSaved(function (Model $record, Model $replica): void {
                ReplicationContextStore::remember($replica, $record);
            });
        });
    }

    protected function resolveReplicateActionClass(): ?string
    {
        if (class_exists(\Filament\Tables\Actions\ReplicateAction::class)) {
            return \Filament\Tables\Actions\ReplicateAction::class;
        }

        if (class_exists(\Filament\Actions\ReplicateAction::class)) {
            return \Filament\Actions\ReplicateAction::class;
        }

        return null;
    }

    protected function registerWidgetComponents(): void
    {
        $components = [
            'mr-adder.filament-logger.widgets.activity-overview-widget' => ActivityOverviewWidget::class,
            'mr-adder.filament-logger.widgets.activity-trend-chart-widget' => ActivityTrendChartWidget::class,
            'mr-adder.filament-logger.widgets.top-users-chart-widget' => TopUsersChartWidget::class,
            'mr-adder.filament-logger.widgets.top-events-chart-widget' => TopEventsChartWidget::class,
            'mr-adder.filament-logger.widgets.high-risk-actions-chart-widget' => HighRiskActionsChartWidget::class,
        ];

        foreach ($components as $name => $componentClass) {
            if (class_exists($componentClass)) {
                Livewire::component($name, $componentClass);
            }
        }
    }
}
