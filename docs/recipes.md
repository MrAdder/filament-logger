# Recipes

End-to-end examples for setups that take more than one config key. Each is
copyable and exercised by `tests/Feature/DocumentedRecipesTest.php`, so the code
here matches how the package actually behaves.

For the full list of options behind these examples, see the
[configuration guide](/configuration). For the hooks used below, see
[extending](/extending).

[[toc]]

## Multi-panel

### Show the activity log in several panels

The activity resource is a single class, referenced through config so the
package can swap the Filament 3 and 4+ implementations for you. Register it in
every panel that should see it:

```php
// app/Providers/Filament/AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->resources([
            config('filament-logger.activity_resource'),
        ]);
}
```

```php
// app/Providers/Filament/PartnerPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('partner')
        ->path('partner')
        ->resources([
            config('filament-logger.activity_resource'),
        ]);
}
```

Always reference `config('filament-logger.activity_resource')` rather than the
class name directly. That is what keeps the same code working across supported
Filament versions.

### Give each panel different access

Resource logging itself is global — the package observes the models behind every
panel's resources. Differentiate at the **policy** level, which is where the
current panel is available:

```php
namespace App\Policies;

use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return match (Filament::getCurrentPanel()?->getId()) {
            'admin' => $user->can('audit.view-all'),
            'partner' => false,          // partners get no audit log at all
            default => false,
        };
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->viewAny($user);
    }
}
```

Register it as normal:

```php
Gate::policy(Activity::class, ActivityPolicy::class);
```

Remember the package is strict by default: with no policy, or a policy missing
`viewAny`/`view`, access is denied. See [security](/security).

### Give each panel a different table

Use the display hooks and branch on the current panel:

```php
use Filament\Facades\Filament;
use MrAdder\FilamentLogger\Support\ActivityDisplay;

ActivityDisplay::tableColumnsUsing(function (array $columns): array {
    if (Filament::getCurrentPanel()?->getId() !== 'partner') {
        return $columns;
    }

    // Partners should not see who performed the action.
    return array_values(array_filter(
        $columns,
        fn ($column): bool => $column->getName() !== 'causer.name',
    ));
});
```

### What is not supported

Registering **two differently configured activity resources** in two panels is
not supported. The package's list and view pages resolve their resource from
`filament-logger.activity_resource`, so a second subclassed resource would still
route through the configured one. Use one resource plus the policy and display
hooks above.

## Tenancy

### Choosing an approach

`filament-logger.scoped_to_tenant` defaults to `true`, but that only takes
effect when the panel actually has tenancy configured **and** the activity model
can be scoped to a tenant. Spatie's default `Activity` model has no tenant
column, so a tenant-aware install needs one of the two setups below.

### Option A — a tenant-aware activity model

Add a tenant column to the activity table:

```php
Schema::table('activity_log', function (Blueprint $table): void {
    $table->foreignId('team_id')->nullable()->index();
});
```

Extend Spatie's model with the relationship Filament expects:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
```

Point Spatie at it — the package reads the model through
`ActivitylogServiceProvider::determineActivityModel()`, so this one key is
enough for the resource, exports, alerts, and pruning to follow:

```php
// config/activitylog.php
'activity_model' => \App\Models\Activity::class,
```

Then stamp the tenant when activity is recorded:

```php
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

Activity::creating(function (Activity $activity): void {
    $activity->team_id ??= Filament::getTenant()?->getKey();
});
```

Keep `scoped_to_tenant` set to `true` so the resource is filtered to the current
tenant.

### Option B — turn scoping off

If your activity log is deliberately global (a central audit trail an owner
reviews across tenants), turn scoping off so Filament does not try to constrain
a model that cannot be constrained:

```php
'scoped_to_tenant' => false,
```

Then restrict visibility in the policy instead, which keeps the decision in one
place:

```php
public function viewAny(User $user): bool
{
    return $user->isOwnerOf(Filament::getTenant());
}
```

## Custom events

### Record a domain event

Anything your application considers auditable can be logged without a logger
class:

```php
use MrAdder\FilamentLogger\Facades\FilamentLogger;

class RefundOrder
{
    public function handle(Order $order, int $amountInCents): void
    {
        $order->refund($amountInCents);

        FilamentLogger::log(
            event: 'Refund Issued',
            description: "Refund issued for order {$order->reference}",
            options: [
                'logName' => 'Billing',
                'subject' => $order,
                'causer' => auth()->user(),
                'risk' => 'medium',
                'tags' => ['billing', 'refund'],
                'properties' => [
                    'old' => ['status' => 'paid'],
                    'attributes' => ['status' => 'refunded'],
                    'amount' => $amountInCents,
                ],
            ],
        );
    }
}
```

Using `old` and `attributes` is what makes the entry render in the structured
diff view rather than as a flat blob.

### Make it visible in the review UI

Give the log name a colour so it is distinguishable in the table:

```php
'custom' => [
    ['log_name' => 'Billing', 'color' => 'warning'],
],
```

Sensitive keys inside `properties` are redacted automatically, so a token or
password in a custom payload never reaches the database:

```php
FilamentLogger::log(
    event: 'Api Token Rotated',
    description: 'Rotated integration token',
    options: [
        'logName' => 'Security',
        'properties' => ['api_token' => 'super-secret', 'integration' => 'stripe'],
    ],
);

// stored: ['api_token' => '[REDACTED]', 'integration' => 'stripe']
```

### Add a review tab for it

```php
'activity_filters' => [
    'saved' => [
        'billing' => [
            'label' => 'Billing',
            'icon' => 'heroicon-o-banknotes',
            'log_names' => ['Billing'],
            'date_preset' => 'last_30_days',
        ],
    ],
],
```

## Alerts

### From nothing to a working Slack alert

**1. Enable alerts and pick a delivery route.** Queue them so a slow webhook
never delays the action being audited:

```php
// config/filament-logger.php
'alerts' => [
    'enabled' => true,
    'queue' => true,
    'cache_store' => 'redis',
    'slack' => [
        'webhook_url' => env('AUDIT_SLACK_WEBHOOK'),
    ],
],
```

**2. Write the rule.** This one fires on the custom event from above:

```php
'rules' => [
    'large_refund' => [
        'enabled' => true,
        'channels' => ['slack'],
        'log_names' => ['Billing'],
        'events' => ['Refund Issued'],
        'title' => 'Refund issued on :log_name',
        'message' => ':description (risk :risk)',
        'cooldown_minutes' => 5,
    ],
],
```

**3. Make sure a worker is running**, since `queue` is on:

```bash
php artisan queue:work
```

### Batch a noisy rule instead

Deletions during a bulk cleanup would otherwise produce one alert each:

```php
'destructive_activity' => [
    'enabled' => true,
    'channels' => ['slack'],
    'events' => ['Deleted', 'Force Deleted'],
    'digest' => true,
    'digest_minutes' => 60,
    'digest_title' => ':count deletions in the last :window minutes',
],
```

Schedule the release so digests arrive on time:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('filament-logger:send-alert-digests')->everyFiveMinutes();
```

### Send somewhere that is not Slack or Discord

The generic `webhook` channel posts structured JSON, so it can drive PagerDuty,
an internal service, or an automation platform:

```php
'alerts' => [
    'webhook' => [
        'url' => env('AUDIT_WEBHOOK_URL'),
        'headers' => ['Authorization' => 'Bearer '.env('AUDIT_WEBHOOK_TOKEN')],
    ],
    'rules' => [
        'account_takeover_signals' => [
            'enabled' => true,
            'channels' => ['webhook'],
            'risk_reasons' => ['credential_change', 'two_factor_change'],
            'title' => 'Account security change on :subject',
        ],
    ],
],
```

`risk_reasons` matches the [risk heuristics](/configuration#risk-heuristics), so
this rule fires on password, email, or two-factor changes without listing every
event yourself.

### Register a rule from code

When a rule cannot be expressed in config — one built from the database, for
example — register it instead:

```php
use MrAdder\FilamentLogger\Support\ActivityAlertRules;

foreach (AlertSubscription::active()->get() as $subscription) {
    ActivityAlertRules::register("subscription_{$subscription->id}", [
        'enabled' => true,
        'channels' => ['webhook'],
        'webhook_url' => $subscription->endpoint,
        'events' => $subscription->events,
    ]);
}
```

### Verify it before trusting it

```bash
php artisan tinker
```

```php
MrAdder\FilamentLogger\Facades\FilamentLogger::log(
    event: 'Refund Issued',
    description: 'Test refund alert',
    options: ['logName' => 'Billing', 'risk' => 'medium'],
);
```

With `queue` enabled, run a worker first or the alert will sit in the queue. A
webhook that returns a non-2xx status is treated as failed and releases the
rule's cooldown, so the next matching activity retries rather than being
silently swallowed.
