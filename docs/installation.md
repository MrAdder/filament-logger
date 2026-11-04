# Installation and Setup

## Requirements

| Package | Version |
|---|---|
| PHP | `^8.2` |
| Filament | `^3.0`, `^4.3.1`, or `^5.0` |
| Laravel contracts | `^12.0` or `^13.0` |
| spatie/laravel-activitylog | `^4.5` or `^5.0` |

`spatie/laravel-activitylog` v5 itself needs PHP `8.4+` and Laravel `12+`. On PHP 8.2 and 8.3, Composer keeps you on v4, which this package continues to support in full.

Filament 4 support starts at `4.3.1` because earlier `4.x` releases were affected by an upstream security issue fixed in `4.3.1`. See the [Filament security advisory](https://github.com/filamentphp/filament/security/advisories/GHSA-pvcv-q3q7-266g) and the [v4.3.1 release notes](https://github.com/filamentphp/filament/releases/tag/v4.3.1).

### Why Laravel 11 is no longer supported

Laravel 11 support was dropped after `v1.6.3`, for four reasons:

- **Laravel 11 no longer receives security fixes.** Its security support window ended in March 2026, so new vulnerabilities are only patched in `12.x` and `13.x`.
- **Every `11.x` release has unpatched advisories.** The most serious is [CVE-2026-114509](https://packagist.org/security-advisories/PKSA-b41t-wbzk-9q51), a critical TokenGuard authentication bypass fixed only in `12.68.0` and `13.29.0`. Others include a debug-page XSS (CVE-2026-102279) and CRLF injection in the default email validation rule (CVE-2026-48019).
- **An authentication bypass breaks the audit trail.** This package records *who* did what. If the framework can be tricked into authenticating the wrong user, every activity's causer is open to doubt, and the log can no longer be relied on for security reviews or compliance.
- **Composer refuses to install it.** Composer blocks packages with known security advisories by default, so Laravel 11 cannot be installed in CI without switching that protection off. A combination the package cannot test is a combination it cannot honestly claim to support.

If you are still on Laravel 11, upgrade to Laravel `12.68.0` / `13.29.0` or later. Until you can, `composer require mradder/filament-logger:~1.6.3` keeps you on the last release that supports Laravel 11, but that release will not receive further fixes.

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

## Using spatie/laravel-activitylog v5

Nothing needs configuring: the package detects which major version is installed.

v5 stores the old and new values of a change in a new `attribute_changes` column instead of inside `properties`, so an existing install has to follow Spatie's [upgrade guide](https://github.com/spatie/laravel-activitylog/blob/main/UPGRADING.md#from-v4-to-v5) and add that column before it starts logging.

- New activity is written the way v5 expects: model changes and the `old` / `attributes` of a [custom event](custom-events.md) go to `attribute_changes`, and everything else stays in `properties`.
- Rows written before the upgrade, and any you have not moved into `attribute_changes`, still work. The detail view, exports, risk detection, the old and new value filters and search all read both places.
- Filter and export preset keys such as `properties->old` are unchanged, so saved presets keep working.

## Translations

Publish translations with:

```bash
php artisan vendor:publish --tag="filament-logger-translations"
```
