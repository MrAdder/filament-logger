Maintained fork of the original package by Z3d0X.

# Filament Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mradder/filament-logger.svg?style=for-the-badge)](https://packagist.org/packages/mradder/filament-logger)
[![Total Downloads](https://img.shields.io/packagist/dt/mradder/filament-logger.svg?style=for-the-badge)](https://packagist.org/packages/mradder/filament-logger)

Filament Logger is an audit log and activity log package for Filament admin panels.

Built on [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog), it adds a ready-made Filament activity resource plus automatic logging for resources, models, auth events, notifications, and custom domain events.

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
| PHP | `^8.4` |
| Filament | `^3.0` |
| Laravel contracts | `^11.0` or `^12.0` |

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

- [Docs Index](docs/README.md)
- [Installation and Setup](docs/installation.md)
- [Security and Authorization](docs/security.md)
- [Configuration Guide](docs/configuration.md)
- [Activity Review UI](docs/activity-review.md)
- [Custom Events and Alerts](docs/custom-events.md)
- [Releasing](docs/releasing.md)

## Screenshots

<img alt="logger-index" src="https://raw.githubusercontent.com/mradder/filament-logger/main/art/list-screenshot.png">
<img alt="logger-detail-1" src="https://raw.githubusercontent.com/mradder/filament-logger/main/art/view-screenshot-1.png">
<img alt="logger-detail-2" src="https://raw.githubusercontent.com/mradder/filament-logger/main/art/view-screenshot-2.png">

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

See [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for contribution guidelines.

## Security

Please review [our security policy](../../security/policy) for responsible disclosure details.

## Credits

- [Ziyaan Hassan](https://github.com/Z3d0X) - original developer
- [Daniel Green](https://github.com/MrAdder)
- [Spatie Activitylog Contributors](https://github.com/spatie/laravel-activitylog#credits)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
