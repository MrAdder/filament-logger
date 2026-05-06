# Configuration Guide

## What Gets Logged

Out of the box the package logs:

- Filament resource model events
- auth and access activity
- notification events

If you want to log additional non-resource models, register them in `filament-logger.models.register`.

## Resource Logging

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

## Model Logging

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

## Access Logging

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

In multi-guard applications, you can scope guard-based access logging with `access.guards`:

```php
'access' => [
    'guards' => ['web'],
],
```

Guard filter behavior:

- `null` (default): log access events for all guards
- `['web']` (or any list): for events with a `guard` property (`Login`, `Logout`, `Failed`), only listed guards are logged
- events without a `guard` property (`Lockout`, `PasswordReset`, and optional Fortify recovery-code events) continue to be logged

This keeps panel access logs focused in apps that also have storefront or customer guards.

You can also redact stored IP addresses at view/export time for users who do not pass the sensitive-data policy ability:

```php
'authorization' => [
    'sensitive_ability' => 'viewSensitiveData',
],

'access' => [
    'store_ip' => true,
    'anonymize_ip' => false,
    'redact_ip_for_unauthorized_viewers' => true,
],
```

This is useful when security reviewers need full IP addresses but most admin users should only see `[REDACTED]`.

## Sensitive Key Redaction

You can extend the list of redactable keys in config.

This applies recursively across `old`, `attributes`, metadata, exports, and the activity detail view.

```php
'redacted_placeholder' => '[REDACTED]',

'sensitive_keys' => [
    'password',
    'api_token',
    'client_secret',
    'webhook_url',
    'authorization',
    'ip_address',
],
```

The matcher normalizes key names, so values like `request_authorization`, `client-secret`, and nested `profile.ip_address` payloads will also be caught.

## Diff Formatting

The activity detail page renders old and new values using a structured diff view. You can adjust how large values are displayed:

```php
'diff' => [
    'collapse_after' => 120,
    'pretty_print_json' => true,
],
```

## Risk Tagging

High-risk activity can be tagged automatically based on specific events or changed attributes:

```php
'risk' => [
    'high' => [
        'events' => [
            'Deleted',
            'Force Deleted',
            'Failed Login',
            'Lockout',
        ],
        'change_keys' => [
            'role',
            'role_id',
            'roles',
            'permission',
            'permissions',
        ],
    ],
],
```

## Alert Throttling

Sensitive activity alerts can be throttled per rule to reduce noise from repeated matching events:

```php
'alerts' => [
    'cache_store' => 'redis',
    'rules' => [
        'destructive_activity' => [
            'events' => ['Deleted', 'Force Deleted'],
            'cooldown_minutes' => 10,
        ],
    ],
],
```

Cooldown keys are stored in the default cache store unless you set `alerts.cache_store`.

## Custom Log Names

You can define your own log names and colors:

```php
'custom' => [
    [
        'log_name' => 'Security',
        'color' => 'danger',
    ],
],

'custom_events' => [
    'default_log_name' => 'Custom',
    'color' => 'primary',
],
```
