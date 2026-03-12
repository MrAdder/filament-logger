<?php

namespace MrAdder\FilamentLogger;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Tables\Actions\ReplicateAction;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use MrAdder\FilamentLogger\FilamentLogger as FilamentLoggerManager;
use MrAdder\FilamentLogger\Commands\PruneActivitiesCommand;
use MrAdder\FilamentLogger\Loggers\ResourceLogger;
use MrAdder\FilamentLogger\Support\ActivityAlertDispatcher;
use MrAdder\FilamentLogger\Support\ActivityAnalytics;
use MrAdder\FilamentLogger\Support\ActivityExporter;
use MrAdder\FilamentLogger\Support\ActivityRiskResolver;
use MrAdder\FilamentLogger\Support\ObserverRegistrar;
use MrAdder\FilamentLogger\Support\ReplicationContextStore;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentLoggerServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-logger';

    protected function getResources(): array
    {
        return [
            config('filament-logger.activity_resource')
        ];
    }

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasTranslations()
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(PruneActivitiesCommand::class)
            ->hasInstallCommand(function (InstallCommand $installCommand) {
                $installCommand
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('z3d0x/filament-logger')
                    ->startWith(function (InstallCommand $installCommand) {
                        $installCommand->call('vendor:publish', [
                            '--provider' => "Spatie\Activitylog\ActivitylogServiceProvider",
                            '--tag' => "activitylog-migrations"
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

        ReplicateAction::configureUsing(function (ReplicateAction $action): void {
            $action->beforeReplicaSaved(function (Model $record, Model $replica): void {
                ReplicationContextStore::remember($replica, $record);
            });
        });
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        if (config('filament-logger.resources.enabled', true)) {
            $exceptResources = [...config('filament-logger.resources.exclude'), config('filament-logger.activity_resource')];

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
}
