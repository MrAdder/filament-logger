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

Threshold rules (`'type' => 'threshold'`) alert as soon as the count in the window reaches the threshold, and keep alerting while it stays above it. To keep that to one alert per spike they default their cooldown to `window_minutes`. Set `cooldown_minutes` explicitly to override.

## Alert Delivery

Alerts are dispatched from model observers, so by default a slow or unreachable webhook adds its timeout to the action being audited. Move delivery onto the queue to avoid that:

```php
'alerts' => [
    'queue' => true,
    'queue_connection' => 'redis',
    'queue_name' => 'audit-alerts',
    'webhook_timeout' => 5,
],
```

With `queue` enabled, mail alerts are queued and webhooks are dispatched as `MrAdder\FilamentLogger\Jobs\SendActivityAlertWebhook`, which retries failed deliveries three times. With it disabled the behaviour is unchanged: everything is sent inline.

Webhook responses are checked. A non-2xx response counts as a failed delivery, which releases the rule cooldown so the next matching activity retries rather than being silently suppressed.

## Alert Channels

Four channels ship with the package: `mail`, `slack`, `discord`, and `webhook`.

`webhook` is the generic channel for anything that is not Slack or Discord. It receives a structured JSON payload rather than a service-specific shape:

```php
'alerts' => [
    'webhook' => [
        'url' => env('AUDIT_WEBHOOK_URL'),
        'headers' => [
            'Authorization' => 'Bearer '.env('AUDIT_WEBHOOK_TOKEN'),
        ],
    ],
],
```

```json
{
    "title": "Destructive activity detected",
    "message": "Record deleted\nEvent: Deleted\nLog: Resource\n...",
    "rule": "Destructive Activity",
    "count": 1,
    "activity": {
        "id": 4213,
        "log_name": "Resource",
        "event": "Deleted",
        "description": "Order Deleted by Dan",
        "risk": "high",
        "risk_reasons": "destructive",
        "subject_type": "App\\Models\\Order",
        "subject_id": 42,
        "causer_type": "App\\Models\\User",
        "causer_id": 1,
        "logged_at": "2026-08-10 14:03:11"
    }
}
```

Any rule can override the endpoint for a channel with `webhook_url`, `slack_url`, or `discord_url`, which is useful for routing high-risk rules to a different destination.

## Alert Message Templates

A rule can define `title` and `message` templates. Both accept `:placeholder` tokens:

```php
'rules' => [
    'role_changes' => [
        'channels' => ['slack'],
        'risk_reasons' => ['role_change', 'permission_change'],
        'title' => '[:risk] :rule on :subject',
        'message' => ':causer changed :subject at :logged_at (:risk_reasons)',
    ],
],
```

| Placeholder | Value |
|---|---|
| `:rule` | Rule name, headline-cased |
| `:event` | Activity event |
| `:log_name` | Activity log name |
| `:description` | Activity description |
| `:risk` | Resolved risk level |
| `:risk_reasons` | Comma-separated risk reasons |
| `:subject` / `:subject_type` / `:subject_id` | Subject label and parts |
| `:causer` / `:causer_type` / `:causer_id` | Causer label and parts |
| `:logged_at` | When the activity was recorded |
| `:count` | Matching activities (always `1` outside a digest) |
| `:window` | Digest window in minutes |
| `:threshold` | Threshold for threshold rules |

Rules without templates keep the built-in wording, and the existing `label` key still works as the title.

## Digest Alerts

A busy rule can batch its matches into one alert instead of firing per event:

```php
'rules' => [
    'destructive_activity' => [
        'channels' => ['slack'],
        'events' => ['Deleted', 'Force Deleted'],
        'digest' => true,
        'digest_minutes' => 60,
        'digest_title' => ':count deletions in the last :window minutes',
        'digest_message' => 'Latest: :description',
    ],
],
```

The first matching activity opens a window. Everything matching during that window is counted, and one alert is sent when it closes, carrying `:count`.

Digests are released two ways. Schedule the command for reliable, on-time delivery:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('filament-logger:send-alert-digests')->everyFiveMinutes();
```

As a fallback, an expired window is also released when the next matching activity arrives, so digests still work without a scheduler — they just wait for the next event. Run `filament-logger:send-alert-digests --force` to flush everything pending immediately.

Digest rules ignore `cooldown_minutes`: the window already provides the throttling.

## Risk Heuristics

Beyond the `risk.high` events and change keys, the package detects named risk reasons that alert rules can filter on with `risk_reasons`:

| Reason | Default level | Triggered by |
|---|---|---|
| `destructive` | high | `Deleted`, `Force Deleted` |
| `auth_failure` | high | `Failed Login`, `Lockout` |
| `role_change` | high | `risk.high.change_keys` |
| `permission_change` | high | `ability`, `abilities`, `scope`, `scopes`, `is_admin`, `is_super_admin` |
| `credential_change` | high | `password`, `email`, `username`, `Password Reset` |
| `two_factor_change` | high | `two_factor_*` keys, `Two Factor Recovery` |
| `account_status_change` | medium | `status`, `active`, `is_active`, `blocked_at`, `banned_at`, `suspended_at`, `email_verified_at` |

When several match, the most severe level wins. Each is configurable under `risk.heuristics`; set one to an empty array to disable it, or add your own:

```php
'risk' => [
    'heuristics' => [
        'billing_change' => [
            'level' => 'medium',
            'change_keys' => ['plan', 'billing_email', 'card_last_four'],
        ],
    ],
],
```

An explicitly supplied risk level is never overridden by a heuristic.

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

## Activity Descriptions

Model lifecycle descriptions are built from translation lines, so they follow the application locale:

```php
'log.description' => ':model :event',
'log.description_by' => ':description by :user',
```

To change the wording globally without translating, register a callback. Returning `null` falls back to the built-in description:

```php
use MrAdder\FilamentLogger\Facades\FilamentLogger;

FilamentLogger::describeUsing(function ($subject, string $event, string $logName): ?string {
    return $subject instanceof \App\Models\Order
        ? "Order {$subject->reference} {$event}"
        : null;
});
```

## Search

The activity table includes a broad search box that scans multiple high-value fields to help investigations when you only have partial context. The search looks across:

- `description`
- `subject_type`
- `causer` name
- `properties` JSON (useful for tags or payload values)

Search integrates with existing filters and sorting — it's implemented as a table filter, so applying a search term will narrow results alongside any selected filters or sort order. The search performs SQL `LIKE` matching against these fields (for JSON payloads it performs a `LIKE` against the JSON column), so behavior is predictable across supported database engines.

A `LIKE` over the JSON `properties` column cannot use an index, which makes it the most expensive part of a broad search. On large activity tables you can drop it and keep search on the indexed columns:

```php
'search' => [
    'include_properties' => false,
],
```

If you need more advanced full-text search (language-aware stemming, ranking, or very large datasets), consider integrating a dedicated search engine (e.g., Meilisearch, Algolia, or database full-text indexes) and adapting the filter's query logic.

## Performance

The `log_name` and `subject_type` filter dropdowns are built from `SELECT DISTINCT` over the activity table. That is a full scan, and it runs on every render of the activity list, so the results are cached:

```php
'performance' => [
    'cache_store' => null,          // null uses the default store
    'filter_options_cache_ttl' => 300,  // seconds; 0 disables caching
    'filter_options_limit' => 200,      // cap on distinct values pulled
],
```

A newly seen log name or subject type appears once the cache expires. To refresh it immediately — after a bulk import or a prune, for example — call:

```php
use MrAdder\FilamentLogger\Resources\ActivityResource\Support\ActivityResourceTableOptions;

ActivityResourceTableOptions::flushCache();
```

The package also ships an optional migration adding indexes on `created_at` and `(event, created_at)` to the activity log table. Spatie's own migration only indexes `log_name` and the subject/causer morphs, but the resource sorts by `created_at` on every page load. Publish and run it if your audit trail is large:

```bash
php artisan vendor:publish --tag=filament-logger-migrations
php artisan migrate
```

## Export Authorization

Exports bypass table pagination, so they are gated separately from viewing the resource:

```php
'exports' => [
    'ability' => 'exportActivity',        // set to null to allow any viewer
    'manage_ability' => 'manageExportPresets',
],
```

Define the ability on your activity policy or as a gate. Without it the export actions are hidden, and calling the export methods directly returns a 403.

## Exports metadata

Exports generated by the package include a machine-readable metadata JSON blob in the response headers. The header name is `X-Activity-Export-Metadata` and contains context about the export such as when it was produced and which columns were included.

Example header contents:

```json
{
    "exported_at": "2026-05-26T12:00:00+00:00",
    "exported_by": 1,
    "exported_by_name": "alice@example.com",
    "columns": ["id", "description", "tags", "created_at"],
    "source": "MrAdder\\FilamentLogger\\Resources\\ActivityResource"
}
```

The applied filters are deliberately **not** in the header — they are unbounded in size and would push the response past the server's header limit. They are included in the in-file metadata instead. The header is dropped entirely if it would exceed 4 KB.

To embed metadata in the file itself, enable `exports.embed_metadata`. This changes the file shape, so it is opt-in:

- **CSV** gains a leading `#METADATA:{...}` comment line before the header row.
- **JSON** becomes an object, `{"metadata": {...}, "rows": [...]}`, instead of a bare array.

With `embed_metadata` disabled (the default), JSON exports remain a bare array.

## Queued Exports

Building a large export inside a request is slow and prone to timing out. With queued exports enabled, anything above a row threshold is handed to the queue and the user is told when the file is ready; smaller exports keep streaming straight back as a download.

```php
'exports' => [
    'queue' => [
        'enabled' => true,
        'threshold' => 5000,   // rows above which an export is queued; 0 always queues
        'connection' => null,
        'name' => null,
        'disk' => 'local',
        'path' => 'filament-logger/exports',
        'notify' => 'mail',   // 'mail' | 'database' | null
        'routes' => true,
        'route_prefix' => 'filament-logger',
        'route_middleware' => ['web', 'signed'],
        'link_minutes' => 1440,
        'retention_days' => 7,
    ],
],
```

With `enabled` set to `false` (the default) nothing changes: every export streams directly, exactly as before.

### How the user gets the file

`notify` controls the feedback:

- **`mail`** (default) — emails the requesting user a download link. Chosen as the default because it needs nothing beyond the mail configuration every Laravel app already has.
- **`database`** — a Filament in-panel notification with a Download action. Nicer inside the panel, but it uses Filament's database notifications, so the host application needs Laravel's `notifications` table:

  ```bash
  php artisan make:notifications-table   # or: php artisan notifications:table
  php artisan migrate
  ```

- **`null`** — no notification; the generated path is written to the log for you to serve yourself.

Either way, the user gets an immediate "Export queued" notification when they trigger it, so nothing looks broken while the job runs. If the job fails, the failure is reported back on the same channel rather than only reaching the log.

### Download security

Download links are signed and expire after `link_minutes`. The signature alone is not treated as sufficient authority — the controller also requires that:

- a user is authenticated,
- that user is the one the export was generated for (a forwarded link fails),
- the `exports.ability` gate passes,
- the resolved path stays inside that user's export directory.

Files are written to `{path}/{user id}/`, so one user's export is never reachable through another's link. Set `routes` to `false` to skip route registration entirely and serve the files from the disk yourself.

### Retention

Generated files stay on the disk until pruned. Schedule the cleanup command:

```php
Schedule::command('filament-logger:prune-exports')->daily();
```

```bash
php artisan filament-logger:prune-exports --days=7 --dry-run
```

### Filters

A queued export reproduces the filters that were applied in the table when it was requested — search term, log name, subject type, risk, date filter and preset, and the old/new property filters — along with any selected export preset. The filter state is serialised into the job rather than the query itself, because an Eloquent builder cannot cross the queue.

### CSV and spreadsheet formulas

Cell values beginning with `=`, `+`, `-`, or `@` are prefixed with a single quote before being written. Audit descriptions carry attacker-influenced text — a model label or a failed-login identifier — and without this a crafted value would execute as a formula when the export is opened in Excel, LibreOffice, or Google Sheets.

## Export presets

You can define reusable export presets that include a set of columns and optional saved filters. There are two ways to provide presets:

- Config-defined presets: add entries to `filament-logger.exports.presets` in your app config. These are available out of the box without database migrations.
- DB-backed presets: enable `filament-logger.exports.db_presets_enabled`, then publish and run the package migrations to create the `export_presets` table:

  ```bash
  php artisan vendor:publish --tag=filament-logger-migrations
  php artisan migrate
  ```

  DB presets can then be created from the UI by users holding the `exports.manage_ability` ability.

Presets can be used from the activity list page via the "Export (preset)" actions. When exporting with a preset the exporter will use the preset's `columns` and apply the preset's filters to the query. The export response will include the same `X-Activity-Export-Metadata` header described above and will include a `preset` field referencing the preset key.

