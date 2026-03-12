<?php

namespace MrAdder\FilamentLogger\Tests;

use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Panel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use MrAdder\FilamentLogger\FilamentLoggerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider as SpatieActivitylogServiceProvider;

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

        $this->setUpDatabaseTables();

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
            SpatieActivitylogServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function setUpDatabaseTables(): void
    {
        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create(config('activitylog.table_name', 'activity_log'), function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->nullableMorphs('causer');
            $table->string('event')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        Schema::create('test_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('counter')->default(0);
            $table->string('remember_token')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
