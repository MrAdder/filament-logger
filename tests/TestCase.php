<?php

namespace MrAdder\FilamentLogger\Tests;

use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use MrAdder\FilamentLogger\FilamentLoggerServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(
            Panel::make()
                ->id('test')
                ->path('test')
        );

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'MrAdder\\FilamentLogger\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            FilamentServiceProvider::class,
            FilamentLoggerServiceProvider::class,
            LivewireServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_filament-logger_table.php.stub';
        $migration->up();
        */
    }
}
