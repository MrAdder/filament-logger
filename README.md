Maintained fork of the original package by Z3d0X.

# Filament Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mradder/filament-logger.svg?style=for-the-badge)](https://packagist.org/packages/mradder/filament-logger)
[![Total Downloads](https://img.shields.io/packagist/dt/mradder/filament-logger.svg?style=for-the-badge)](https://packagist.org/packages/mradder/filament-logger)

Audit activity inside Filament using [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog).

This package gives you a ready-made Filament activity log resource plus automatic logging for resource changes, selected models, auth activity, and notifications.

## Highlights

- Filament activity log resource with searchable filters and a structured diff view
- Resource and model lifecycle logging, including create, update, delete, restore, force-delete, and replicate flows
- Auth event logging for login, logout, failed login, lockout, password reset, and 2FA recovery usage
- Notification logging
- Sensitive data redaction, anonymized IP logging, and stricter authorization defaults
- Configurable ignored fields per model and per resource
- Built-in pruning command for retention by age and log name

## Requirements

| Package | Version |
|---|---|
| PHP | `^8.4` |
| Filament | `^3.0` |
| Laravel contracts | `^11.0` or `^12.0` |

## Installation

Install the package:

```bash
composer require mradder/filament-logger
```

Publish the package config and the Spatie activity log migrations:

```bash
php artisan filament-logger:install
```

Run your migrations:

```bash
php artisan migrate
```

Register the activity log resource in your panel provider:

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

## Authorization

The activity log resource is intentionally strict by default.

If no policy is registered for the activity model, or the policy does not implement `viewAny` and `view`, the resource is denied instead of falling back to Filament's permissive default behavior.

After generating a policy, register it in your auth service provider:

```php
<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Activity::class => ActivityPolicy::class,
    ];
}
```

If you use a custom activity model in `config/activitylog.php`, map that model instead.

If you need the old behavior for a legacy install:

```php
'authorization' => [
    'strict' => false,
],
```

## What Gets Logged

Out of the box the package logs:

- Filament resource model events
- auth/access activity
- notification events

If you want to log additional non-resource models, register them in `filament-logger.models.register`.

## Default Privacy and Security Behavior

The package ships with a few safer defaults:

- sensitive keys such as passwords, tokens, secrets, and recovery codes are redacted before storage
- access logs anonymize IP addresses
- user agents are trimmed
- notification recipients are not logged unless explicitly enabled
- the activity resource requires explicit policies when strict authorization is enabled

Example privacy-related overrides:

```php
'redacted_placeholder' => '[REDACTED]',

'access' => [
    'store_ip' => true,
    'anonymize_ip' => true,
    'store_user_agent' => true,
    'user_agent_max_length' => 255,
],

'notifications' => [
    'log_recipient' => false,
    'mask_recipient' => true,
],
```

## Configuration Guide

### Resource Logging

Resource observers are enabled by default and can ignore noisy attributes globally, per model, or per Filament resource:

```php
'resources' => [
    'enabled' => true,
    'ignore' => ['updated_at', 'remember_token'],
    'ignore_for_models' => [
        App\Models\User::class => ['last_seen_at', 'login_count'],
    ],
    'ignore_for_resources' => [
        App\Filament\Resources\UserResource::class => ['last_seen_at', 'login_count'],
    ],
],
```

### Model Logging

Register additional Eloquent models that are not managed through Filament resources:

```php
'models' => [
    'enabled' => true,
    'register' => [
        App\Models\User::class,
    ],
    'ignore' => ['updated_at', 'remember_token'],
    'ignore_for' => [
        App\Models\User::class => ['last_seen_at', 'login_count'],
    ],
],
```

### Access Logging

Auth event logging is configurable per event:

```php
'access' => [
    'events' => [
        'login' => true,
        'logout' => true,
        'failed' => true,
        'lockout' => true,
        'password_reset' => true,
        'two_factor_recovery' => true,
    ],
],
```

The 2FA recovery event is only registered when the Fortify event class is available.

### Diff Formatting

The activity detail page renders old/new values using a structured diff view. You can adjust how large values are displayed:

```php
'diff' => [
    'collapse_after' => 120,
    'pretty_print_json' => true,
],
```

## Retention and Pruning

Prune old activity records with:

```bash
php artisan filament-logger:prune
```

Examples:

```bash
php artisan filament-logger:prune --days=90
php artisan filament-logger:prune --days=90 --log-name=Access --log-name=Resource
php artisan filament-logger:prune --days=90 --except-log-name=Notification
php artisan filament-logger:prune --dry-run
```

You can also set defaults in config:

```php
'pruning' => [
    'days' => 365,
    'only' => [],
    'except' => [],
],
```

Recommended scheduler entry:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('filament-logger:prune')->daily();
```

## Screenshots

<img alt="logger-index" src="https://raw.githubusercontent.com/mradder/filament-logger/main/art/list-screenshot.png">
<img alt="logger-detail-1" src="https://raw.githubusercontent.com/mradder/filament-logger/main/art/view-screenshot-1.png">
<img alt="logger-detail-2" src="https://raw.githubusercontent.com/mradder/filament-logger/main/art/view-screenshot-2.png">

## Translations

Publish translations with:

```bash
php artisan vendor:publish --tag="filament-logger-translations"
```

## Activity Model Resolution

The Filament resource resolves the activity model from `activitylog.activity_model` in `config/activitylog.php`.

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
