# Activity Review UI

## Activity Screen Features

The activity list includes:

- saved review tabs
- date preset shortcuts
- structured diff views
- CSV and JSON exports
- dashboard widgets for trends and hotspots

## Saved Filters and Date Presets

Saved filters are configured as tabs on the list page:

```php
'activity_filters' => [
    'date_presets' => [
        'today' => 'Today',
        'last_24_hours' => 'Last 24 Hours',
        'last_7_days' => 'Last 7 Days',
        'last_30_days' => 'Last 30 Days',
        'this_month' => 'This Month',
    ],
    'saved' => [
        'high_risk' => [
            'label' => 'High Risk',
            'icon' => 'heroicon-o-shield-exclamation',
            'risk' => ['high'],
        ],
        'destructive' => [
            'label' => 'Deletes',
            'icon' => 'heroicon-o-trash',
            'events' => ['Deleted', 'Force Deleted'],
        ],
        'auth_issues' => [
            'label' => 'Auth Issues',
            'icon' => 'heroicon-o-lock-closed',
            'log_names' => ['Access'],
            'events' => ['Failed Login', 'Lockout'],
        ],
        'failed_logins' => [
            'label' => 'Failed Logins',
            'icon' => 'heroicon-o-exclamation-triangle',
            'log_names' => ['Access'],
            'events' => ['Failed Login'],
            'date_preset' => 'last_7_days',
        ],
        'destructive_recent' => [
            'label' => 'Recent Destructive',
            'icon' => 'heroicon-o-fire',
            'events' => ['Deleted', 'Force Deleted'],
            'date_preset' => 'last_7_days',
        ],
        'auth_anomalies' => [
            'label' => 'Auth Anomalies',
            'icon' => 'heroicon-o-finger-print',
            'log_names' => ['Access'],
            'events' => ['Failed Login', 'Lockout', 'Two Factor Recovery'],
            'date_preset' => 'last_30_days',
        ],
    ],
],
```

![High-risk activity tab](/art/activity-review-high-risk-tab.png)

![Deletes review tab](/art/activity-review-deletes-tab.png)

![Authentication issues tab](/art/activity-review-auth-issues-tab.png)

## Structured Diff Views

The activity detail page presents old and new values side by side, keeps larger metadata readable, and makes privacy protections visible when sensitive values are redacted.

![Structured diff view](/art/view-screenshot-1.png)

![Redacted structured diff view](/art/activity-review-redacted-diff.png)

![Redacted changes table](/art/activity-review-redacted-changes.png)

## Exporting Audit Data

The activity screen includes CSV and JSON export actions that use the current table filters and sorting.

![Export menu options](/art/activity-review-export-menu.png)

You can customize the export columns and chunk size:

```php
'exports' => [
    'enabled' => true,
    'chunk_size' => 500,
    'columns' => [
        'id',
        'log_name',
        'event',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'causer_name',
        'risk',
        'tags',
        'properties',
        'created_at',
    ],
],
```

## Dashboard Widgets

Dashboard widgets help spot spikes and risky behavior quickly:

```php
'dashboard' => [
    'enabled' => true,
    'lookback_days' => 30,
    'top_limit' => 5,
],
```

The current widgets cover:

- total activity
- high-risk activity
- failed logins
- unique actors
- activity trend over time
- top users
- top events
- high-risk actions

Widget stat cards and chart headings can be used as drill-down shortcuts into the activity review page. Each shortcut opens the resource with the matching saved preset tab active.

![Activity overview dashboard widgets](/art/activity-review-dashboard-widgets.png)

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
