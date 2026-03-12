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

Supported built-in channels:

- `mail`
- `slack`
- `discord`

## Channel Setup

### Mail

Filament Logger uses Laravel's normal mail configuration for delivery, so you should configure your mailer in `.env` first:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=mailer@example.com
MAIL_PASSWORD=secret
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="audit@example.com"
MAIL_FROM_NAME="Filament Logger"
```

Then set the recipients for alert emails:

```php
'alerts' => [
    'enabled' => true,
    'mail' => [
        'to' => [
            'security@example.com',
            'ops@example.com',
        ],
    ],
],
```

### Slack

Create an incoming webhook in Slack and store it in `.env`:

```dotenv
FILAMENT_LOGGER_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

Then reference it in your package config:

```php
'alerts' => [
    'enabled' => true,
    'slack' => [
        'webhook_url' => env('FILAMENT_LOGGER_SLACK_WEBHOOK_URL'),
    ],
],
```

### Discord

Create a Discord webhook and store it in `.env`:

```dotenv
FILAMENT_LOGGER_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/...
```

Then reference it in your package config:

```php
'alerts' => [
    'enabled' => true,
    'discord' => [
        'webhook_url' => env('FILAMENT_LOGGER_DISCORD_WEBHOOK_URL'),
    ],
],
```

### Rule Channel Selection

Each alert rule chooses its delivery channels with the `channels` key:

```php
'alerts' => [
    'rules' => [
        'destructive_activity' => [
            'channels' => ['mail', 'slack', 'discord'],
        ],
    ],
],
```

If a rule references a channel that is not configured, that channel will not be able to deliver notifications for the rule.

```php
'alerts' => [
    'enabled' => true,
    'cache_store' => 'redis',
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
            'cooldown_minutes' => 10,
        ],
        'role_changes' => [
            'channels' => ['mail'],
            'risk_reasons' => ['role_change'],
            'cooldown_minutes' => 15,
        ],
        'failed_login_spike' => [
            'type' => 'threshold',
            'log_names' => ['Access'],
            'events' => ['Failed Login'],
            'threshold' => 5,
            'window_minutes' => 10,
            'cooldown_minutes' => 15,
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

Any rule can also use:

- `cooldown_minutes`

When a cooldown is configured, repeated matches for the same rule and activity pattern are suppressed until the cooldown window expires. The optional `alerts.cache_store` setting lets you place those cooldown keys in a dedicated cache store.

The built-in defaults cover destructive actions, role and permission changes, and repeated failed login attempts.
