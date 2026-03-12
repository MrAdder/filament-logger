# Custom Events and Alerts

## Custom Event API

You can log domain-specific events without creating a dedicated logger class:

```php
use MrAdder\FilamentLogger\Facades\FilamentLogger;

FilamentLogger::log(
    event: 'Role Escalated',
    description: 'Elevated user privileges for incident response',
    options: [
        'logName' => 'Security',
        'causer' => auth()->user(),
        'subject' => $user,
        'properties' => [
            'old' => ['role' => 'editor'],
            'attributes' => ['role' => 'admin'],
            'ticket' => 'SEC-42',
        ],
        'tags' => ['security', 'roles'],
    ],
);
```

Custom events can include:

- a custom log name
- a subject model
- a causer
- structured properties
- tags
- an explicit risk level
- a custom timestamp

## Sensitive Activity Alerts

Sensitive activity alerts can be sent by mail or webhook when configurable rules match.

```php
'alerts' => [
    'enabled' => true,
    'mail' => [
        'to' => ['security@example.com'],
    ],
    'slack' => [
        'webhook_url' => 'https://hooks.slack.com/services/...',
    ],
    'discord' => [
        'webhook_url' => 'https://discord.com/api/webhooks/...',
    ],
    'rules' => [
        'destructive_activity' => [
            'channels' => ['mail', 'slack', 'discord'],
            'events' => ['Deleted', 'Force Deleted'],
        ],
        'role_changes' => [
            'channels' => ['mail'],
            'risk_reasons' => ['role_change'],
        ],
        'failed_login_spike' => [
            'type' => 'threshold',
            'log_names' => ['Access'],
            'events' => ['Failed Login'],
            'threshold' => 5,
            'window_minutes' => 10,
        ],
    ],
],
```

## Rule Matching

Alert rules can match against:

- `log_names`
- `events`
- `subject_types`
- `risk`
- `risk_reasons`
- `tags`
- `description_contains`

Threshold rules can also use:

- `threshold`
- `window_minutes`

The built-in defaults cover destructive actions, role and permission changes, and repeated failed login attempts.
