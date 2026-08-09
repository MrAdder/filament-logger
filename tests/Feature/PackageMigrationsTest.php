<?php

use Illuminate\Support\ServiceProvider;

/**
 * The test suite builds its schema by hand, so a migration that the package
 * never registers would still look fine here. These tests assert the
 * registration itself.
 */
function publishableMigrationPaths(): array
{
    $groups = ServiceProvider::$publishGroups;

    return array_keys($groups['filament-logger-migrations'] ?? []);
}

it('publishes the export presets migration', function () {
    $matches = array_filter(
        publishableMigrationPaths(),
        fn (string $path): bool => str_contains($path, 'create_export_presets_table'),
    );

    expect($matches)->not->toBeEmpty();
});

it('publishes the optional activity log index migration', function () {
    $matches = array_filter(
        publishableMigrationPaths(),
        fn (string $path): bool => str_contains($path, 'add_filament_logger_indexes_to_activity_log_table'),
    );

    expect($matches)->not->toBeEmpty();
});

it('ships every migration file it registers', function () {
    $paths = publishableMigrationPaths();

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        expect(file_exists($path))->toBeTrue("Registered migration is missing from disk: {$path}");
    }
});

it('registers every migration file that ships in the package', function () {
    $shipped = glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [];
    $registered = publishableMigrationPaths();

    expect($shipped)->not->toBeEmpty();

    foreach ($shipped as $file) {
        $name = basename($file);

        $isRegistered = collect($registered)
            ->contains(fn (string $path): bool => basename($path) === $name);

        expect($isRegistered)->toBeTrue("Migration {$name} ships in the package but is never registered.");
    }
});
