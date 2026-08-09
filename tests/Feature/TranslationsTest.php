<?php

use Illuminate\Support\Facades\File;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Loggers\ModelLogger;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use Spatie\Activitylog\Models\Activity as ActivityModel;

function langPath(string $locale): string
{
    return dirname(__DIR__, 2)."/resources/lang/{$locale}/filament-logger.php";
}

function englishKeys(): array
{
    return array_keys(require langPath('en'));
}

function localeDirectories(): array
{
    return collect(File::directories(dirname(__DIR__, 2).'/resources/lang'))
        ->map(fn (string $path): string => basename($path))
        ->reject(fn (string $locale): bool => $locale === 'en')
        ->values()
        ->all();
}

/**
 * Every translation key referenced through one of the label helpers, mapped to
 * the prefix that helper prepends.
 */
function referencedTranslationKeys(): array
{
    $helpers = [
        'resourceLabel' => 'resource.label.',
        'actionLabel' => 'action.',
        'fieldLabel' => 'field.',
        'presetFieldLabel' => 'field.',
    ];

    $keys = [];

    foreach (File::allFiles(dirname(__DIR__, 2).'/src') as $file) {
        $contents = $file->getContents();

        foreach ($helpers as $helper => $prefix) {
            preg_match_all("/{$helper}\('([a-z0-9_.]+)'\)/", $contents, $matches);

            foreach ($matches[1] as $key) {
                $keys[$prefix.$key] = $file->getRelativePathname();
            }
        }

        preg_match_all("/filament-logger::filament-logger\.([a-z0-9_.]+)'/", $contents, $matches);

        foreach ($matches[1] as $key) {
            $keys[$key] = $file->getRelativePathname();
        }
    }

    return $keys;
}

it('defines every translation key the package references', function () {
    $available = englishKeys();

    foreach (referencedTranslationKeys() as $key => $usedIn) {
        expect(in_array($key, $available, true))
            ->toBeTrue("Missing translation key '{$key}' referenced by {$usedIn}.");
    }
});

it('does not define stale keys in other locales', function () {
    $available = englishKeys();

    foreach (localeDirectories() as $locale) {
        foreach (array_keys(require langPath($locale)) as $key) {
            expect(in_array($key, $available, true))->toBeTrue(
                "Locale '{$locale}' defines '{$key}', which no longer exists in the English source.",
            );
        }
    }
});

it('returns a flat array of strings for every locale', function () {
    foreach (array_merge(['en'], localeDirectories()) as $locale) {
        $translations = require langPath($locale);

        expect($translations)->toBeArray();

        foreach ($translations as $key => $value) {
            expect($key)->toBeString()
                ->and($value)->toBeString("Locale '{$locale}' key '{$key}' is not a string.");
        }
    }
});

it('builds model activity descriptions from translations', function () {
    app('translator')->addLines([
        'filament-logger.log.description' => ':event van :model',
    ], 'nl', 'filament-logger');

    app()->setLocale('nl');

    (new ModelLogger)->created(TestRecord::create(['name' => 'Widget']));

    expect(ActivityModel::latest('id')->first()->description)->toBe('Created van Test Record');
});

it('lets an application override activity descriptions', function () {
    FilamentLogger::describeUsing(
        fn ($model, string $event, string $logName): string => "[{$logName}] {$event}: ".$model->name,
    );

    (new ModelLogger)->created(TestRecord::create(['name' => 'Widget']));

    expect(ActivityModel::latest('id')->first()->description)->toBe('[Model] Created: Widget');

    FilamentLogger::describeUsing(null);
});

it('falls back to the built-in description when the override returns null', function () {
    FilamentLogger::describeUsing(fn (): ?string => null);

    (new ModelLogger)->created(TestRecord::create(['name' => 'Widget']));

    expect(ActivityModel::latest('id')->first()->description)->toContain('Test Record Created');

    FilamentLogger::describeUsing(null);
});
