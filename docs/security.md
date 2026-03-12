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

## Sensitive Data Visibility

You can optionally grant trusted reviewers access to stored sensitive values by adding a `viewSensitiveData` ability to your activity policy.

The activity detail view, key-value property sections, and exports will respect this ability automatically.

```php
<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->isAdmin();
    }

    public function viewSensitiveData(User $user, Activity $activity): bool
    {
        return $user->can('activity.view-sensitive');
    }
}
```

If you want to use a different ability name:

```php
'authorization' => [
    'sensitive_ability' => 'viewAuditSecrets',
],
```

For IP addresses, the most flexible setup is to store full IPs and only redact them for viewers who do not pass the sensitive-data check:

```php
'access' => [
    'store_ip' => true,
    'anonymize_ip' => false,
    'redact_ip_for_unauthorized_viewers' => true,
],
```

This keeps full IPs available for privileged reviewers while showing `[REDACTED]` to other users.

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

'sensitive_keys' => [
    'password',
    'api_token',
    'client_secret',
    'webhook_url',
    'authorization',
    'ip_address',
],

'authorization' => [
    'sensitive_ability' => 'viewSensitiveData',
],

'access' => [
    'store_ip' => true,
    'anonymize_ip' => true,
    'redact_ip_for_unauthorized_viewers' => false,
    'store_user_agent' => true,
    'user_agent_max_length' => 255,
],

'notifications' => [
    'log_recipient' => false,
    'mask_recipient' => true,
],
```

You can add your own redactable keys at any time. The key matcher is recursive and normalization-aware, so it will catch common variants such as `client-secret`, `request_authorization`, or nested `profile.ip_address` values.

## Historical Records

Changes to redaction rules do not automatically rewrite historical activity entries already stored in your database. If older records contain sensitive values, you should scrub or backfill them separately.

The same limitation applies to sensitive-data permissions: they can only reveal values that are still present in stored activity properties. If a value was already redacted or anonymized before it was written to the database, no permission can recover the original value later.
