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

## Search

The activity table includes a broad search box that scans multiple high-value fields to help investigations when you only have partial context. The search looks across:

- `description`
- `subject_type`
- `causer` name
- `properties` JSON (useful for tags or payload values)

Search integrates with existing filters and sorting — it's implemented as a table filter, so applying a search term will narrow results alongside any selected filters or sort order. The search performs SQL `LIKE` matching against these fields (for JSON payloads it performs a `LIKE` against the JSON column), so behavior is predictable across supported database engines.

If you need more advanced full-text search (language-aware stemming, ranking, or very large datasets), consider integrating a dedicated search engine (e.g., Meilisearch, Algolia, or database full-text indexes) and adapting the filter's query logic.
```

## Exports metadata

Exports generated by the package include a machine-readable metadata JSON blob in the response headers. The header name is `X-Activity-Export-Metadata` and contains useful context about the export such as when it was produced, which columns were included, and the applied filters.

Example header contents:

```
{
    "exported_at": "2026-05-26T12:00:00+00:00",
    "exported_by": 1,
    "exported_by_name": "alice@example.com",
    "columns": ["id","description","tags","created_at"],
    "filters": {"log_name":"Access","date_preset":"last_7_days","search":"email"},
    "source": "MrAdder\\FilamentLogger\\Resources\\ActivityResource"
}
```

Note: By default metadata is delivered in response headers (non-breaking). For embedding metadata inside exports (for example, wrapping JSON results in an envelope) consider customising the exporter in your application — embedding changes file shapes and is therefore opt-in only.

## Export presets

You can define reusable export presets that include a set of columns and optional saved filters. There are two ways to provide presets:

- Config-defined presets: add entries to `filament-logger.exports.presets` in your app config. These are available out of the box without database migrations.
- DB-backed presets: enable `filament-logger.exports.db_presets_enabled` and run the provided migration to allow creating presets from the UI. DB presets are stored in `export_presets` and can be managed by admins.

Presets can be used from the activity list page via the "Export (preset)" actions. When exporting with a preset the exporter will use the preset's `columns` and apply the preset's filters to the query. The export response will include the same `X-Activity-Export-Metadata` header described above and will include a `preset` field referencing the preset key.

