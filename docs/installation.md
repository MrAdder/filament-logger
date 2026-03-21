# Installation and Setup

## Requirements

| Package | Version |
|---|---|
| PHP | `^8.2` |
| Filament | `^3.0`, `^4.3.1`, or `^5.0` |
| Laravel contracts | `^11.0`, `^12.0`, or `^13.0` |

Filament 4 support starts at `4.3.1` because earlier `4.x` releases were affected by an upstream security issue fixed in `4.3.1`. See the [Filament security advisory](https://github.com/filamentphp/filament/security/advisories/GHSA-pvcv-q3q7-266g) and the [v4.3.1 release notes](https://github.com/filamentphp/filament/releases/tag/v4.3.1).

The package includes compatibility shims for Filament `3.x`, `4.x`, and `5.x`, and the supported range is verified against the CI matrix. If a future Filament release introduces a new breaking API change, a follow-up package update may still be required.

## Install the Package

```bash
composer require mradder/filament-logger
```

## Publish Config and Migrations

```bash
php artisan filament-logger:install
```

This publishes the package config and the Spatie Activitylog migration stub.

## Run Migrations

```bash
php artisan migrate
```

## Register the Activity Resource

Add the activity resource to your Filament panel:

```php
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->resources([
            config('filament-logger.activity_resource'),
        ]);
}
```

## Activity Model Resolution

The Filament resource resolves the activity model from `activitylog.activity_model` in `config/activitylog.php`.

If you use a custom activity model, the resource and authorization checks will follow that model automatically.

## Translations

Publish translations with:

```bash
php artisan vendor:publish --tag="filament-logger-translations"
```
