# Installation and Setup

## Requirements

| Package | Version |
|---|---|
| PHP | `^8.4` |
| Filament | `^3.0` |
| Laravel contracts | `^11.0` or `^12.0` |

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
