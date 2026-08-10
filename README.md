> **Community-maintained continuation of the original Filament Logger package by [Z3d0X](https://github.com/Z3d0X).**

# Filament Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mradder/filament-logger?style=for-the-badge)](https://packagist.org/packages/mradder/filament-logger)
[![Total Downloads](https://img.shields.io/packagist/dt/mradder/filament-logger?style=for-the-badge)](https://packagist.org/packages/mradder/filament-logger)
[![Tests](https://img.shields.io/github/actions/workflow/status/MrAdder/filament-logger/run-tests.yml?branch=main&style=for-the-badge&label=tests)](https://github.com/MrAdder/filament-logger/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/MrAdder/filament-logger/phpstan.yml?branch=main&style=for-the-badge&label=phpstan)](https://github.com/MrAdder/filament-logger/actions/workflows/phpstan.yml)
[![Docs](https://img.shields.io/github/actions/workflow/status/MrAdder/filament-logger/deploy-docs.yml?branch=main&style=for-the-badge&label=docs)](https://github.com/MrAdder/filament-logger/actions/workflows/deploy-docs.yml)
[![Quality Gate](https://img.shields.io/sonar/quality_gate/MrAdder_filament-logger?server=https%3A%2F%2Fsonarcloud.io&style=for-the-badge&label=quality%20gate)](https://sonarcloud.io/summary/new_code?id=MrAdder_filament-logger)
[![License](https://img.shields.io/packagist/l/mradder/filament-logger?style=for-the-badge)](https://packagist.org/packages/mradder/filament-logger)

Filament Logger is an audit log and activity log package for Filament admin panels.

Built on [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog), it adds a ready-made Filament activity resource plus automatic logging for resources, models, auth events, notifications, and custom domain events.

![Filament Logger package card](art/package-card.png)

Use it when you need to:

- review admin activity and security events inside Filament
- export audit data for compliance, support, or incident response
- trigger alerts for destructive or high-risk actions
- log custom domain events without building your own audit UI

## Highlights

- Ready-made Filament audit log resource with searchable filters, structured diffs, saved review tabs, and date presets
- CSV and JSON exports for filtered audit data
- Dashboard widgets for top users, top events, activity spikes, and high-risk actions
- Resource and model lifecycle logging, including create, update, delete, restore, force-delete, and replicate flows
- Auth event logging for login, logout, failed login, lockout, password reset, and 2FA recovery usage
- Notification logging plus alerting hooks for mail, Slack, and Discord webhooks
- Custom event API for domain-specific audit events
- Sensitive data redaction, anonymized IP logging, and stricter authorization defaults
- Configurable ignored fields per model and per resource
- Built-in pruning command for retention by age and log name

## Requirements

| Package | Version |
|---|---|
| PHP | `^8.2` |
| Filament | `^3.0`, `^4.3.1`, or `^5.0` |
| Laravel contracts | `^11.0`, `^12.0`, or `^13.0` |

Filament 4 support starts at `4.3.1` because earlier `4.x` releases were affected by an upstream security issue fixed in `4.3.1`. See the [Filament security advisory](https://github.com/filamentphp/filament/security/advisories/GHSA-pvcv-q3q7-266g) and the [v4.3.1 release notes](https://github.com/filamentphp/filament/releases/tag/v4.3.1).

The package includes compatibility shims for Filament `3.x`, `4.x`, and `5.x`, and the supported range is verified against the CI matrix. If a future Filament release introduces a new breaking API change, a follow-up package update may still be required.

## Quick Start

Install the package:

```bash
composer require mradder/filament-logger
```

Publish config and the Spatie activity log migrations:

```bash
php artisan filament-logger:install
php artisan migrate
```

Register the activity resource in your panel provider:

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

## Documentation

- [Documentation Site](https://mradder.github.io/filament-logger/)
- [Installation and Setup](https://mradder.github.io/filament-logger/installation)
- [Security and Authorization](https://mradder.github.io/filament-logger/security)
- [Configuration Guide](https://mradder.github.io/filament-logger/configuration)
- [Activity Review UI](https://mradder.github.io/filament-logger/activity-review)
- [Custom Events and Alerts](https://mradder.github.io/filament-logger/custom-events)
- [Recipes: multi-panel, tenancy, alerts, custom events](https://mradder.github.io/filament-logger/recipes)
- [Extending](https://mradder.github.io/filament-logger/extending)
- [Roadmap](https://mradder.github.io/filament-logger/roadmap)

## Filtering By Auth Guard

In multi-guard applications, you can scope access-event logging to specific guards.

Set `access.guards` to an allow-list:

```php
'access' => [
    // ...
    'guards' => ['web'],
],
```

Behavior:

- `null` (default): log access events from all guards (backward compatible behavior)
- `['web']` (or any list): for events that include a `guard` property (`Login`, `Logout`, `Failed`), only the listed guards are logged
- events without a `guard` property (`Lockout`, `PasswordReset`, and optional Fortify recovery-code events) continue to be logged

Example for a Filament panel + storefront app:

```php
'access' => [
    // Log only panel auth events from the web guard.
    'guards' => ['web'],
],
```

## Screenshots

<img alt="Filament Logger dashboard widgets" src="https://raw.githubusercontent.com/MrAdder/filament-logger/main/art/activity-review-dashboard-widgets.png">
<img alt="Filament Logger high risk review tab" src="https://raw.githubusercontent.com/MrAdder/filament-logger/main/art/activity-review-high-risk-tab.png">
<img alt="Filament Logger auth issues review tab" src="https://raw.githubusercontent.com/MrAdder/filament-logger/main/art/activity-review-auth-issues-tab.png">
<img alt="Filament Logger export menu" src="https://raw.githubusercontent.com/MrAdder/filament-logger/main/art/activity-review-export-menu.png">
<img alt="Filament Logger structured diff view" src="https://raw.githubusercontent.com/MrAdder/filament-logger/main/art/view-screenshot-1.png">
<img alt="Filament Logger redacted activity diff view" src="https://raw.githubusercontent.com/MrAdder/filament-logger/main/art/activity-review-redacted-diff.png">
<img alt="Filament Logger redacted changes table" src="https://raw.githubusercontent.com/MrAdder/filament-logger/main/art/activity-review-redacted-changes.png">

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines and [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) for community expectations.

## Security

Please review [SECURITY.md](SECURITY.md) for supported versions and responsible disclosure details. Do not report vulnerabilities through public issues.

## Credits

- [Ziyaan Hassan](https://github.com/Z3d0X) - original developer
- [Daniel Green](https://github.com/MrAdder)
- [Spatie Activitylog Contributors](https://github.com/spatie/laravel-activitylog#credits)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
