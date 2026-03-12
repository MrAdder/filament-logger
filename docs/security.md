# Security and Authorization

## Strict Authorization

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

If you need the legacy behavior:

```php
'authorization' => [
    'strict' => false,
],
```

## Default Privacy Behavior

The package ships with safer defaults:

- sensitive keys such as passwords, tokens, secrets, and recovery codes are redacted
- access logs anonymize IP addresses
- user agents are trimmed
- notification recipients are not logged unless explicitly enabled
- activity resource access requires explicit policies when strict authorization is enabled

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

## Historical Records

Changes to redaction rules do not automatically rewrite historical activity entries already stored in your database. If older records contain sensitive values, you should scrub or backfill them separately.
