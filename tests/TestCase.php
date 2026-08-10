<?php

namespace MrAdder\FilamentLogger\Tests;

use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\FilamentLoggerServiceProvider;
use MrAdder\FilamentLogger\Support\ActivityAlertRules;
use MrAdder\FilamentLogger\Support\ActivityDisplay;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider as SpatieActivitylogServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // The extension registries are static. Tests run in random order, so a
        // hook registered by one file must not leak into another.
        ActivityDisplay::flush();
        ActivityAlertRules::flush();
        FilamentLogger::describeUsing(null);

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
        // Signed routes (queued export downloads) need an encryption key.
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('auth.providers.users.model', Fixtures\Models\TestUser::class);

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

        /** @var Migration $migration */
        $migration = require dirname(__DIR__).'/database/migrations/2026_05_26_000000_create_export_presets_table.php';
        $migration->up();
    }
}
