# Extending Filament Logger

These are the supported extension points. They are covered by tests and are
stable for the whole `1.x` series, so you can build on them without patching
package internals or subclassing the resource.

Every hook is optional. With none registered the package behaves exactly as it
does out of the box.

## Where to register

Register hooks once, from the `boot()` method of a service provider:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MrAdder\FilamentLogger\Support\ActivityAlertRules;
use MrAdder\FilamentLogger\Support\ActivityDisplay;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ActivityDisplay::causerLabelUsing(
            fn (?Model $causer): ?string => $causer?->full_name,
        );

        ActivityAlertRules::register('contract_signed', [
            'enabled' => true,
            'channels' => ['webhook'],
            'events' => ['Contract Signed'],
        ]);
    }
}
```

## Two kinds of hook

**Label hooks** return a string, or `null` to fall back to the built-in
formatting. Returning `null` is what lets you customise one model without having
to handle every other case.

**Schema hooks** receive the built-in array and return the array to use, so a
single callback can append, replace, reorder, or remove. A hook that returns
something other than an array is ignored rather than breaking the page.

## Display

`MrAdder\FilamentLogger\Support\ActivityDisplay`

### Subject labels

```php
use Illuminate\Database\Eloquent\Model;

ActivityDisplay::subjectLabelUsing(function (?string $subjectType, mixed $subjectId, Model $activity): ?string {
    return $subjectType === \App\Models\Order::class
        ? 'Order '.\App\Models\Order::find($subjectId)?->reference
        : null;   // everything else keeps "Model # 1"
});
```

### Causer labels

```php
ActivityDisplay::causerLabelUsing(function (?Model $causer, Model $activity): ?string {
    return $causer?->full_name ?? 'System';
});
```

This is also the supported way to support a user model that has no `name`
column — the causer column reads `name` by default.

### Table columns

```php
use Filament\Tables\Columns\TextColumn;

ActivityDisplay::tableColumnsUsing(function (array $columns): array {
    $columns[] = TextColumn::make('properties.tenant')->label('Tenant');

    return $columns;
});
```

Removing a column works the same way:

```php
ActivityDisplay::tableColumnsUsing(
    fn (array $columns): array => array_values(array_filter(
        $columns,
        fn ($column): bool => $column->getName() !== 'description',
    )),
);
```

### Detail page entries

```php
ActivityDisplay::infolistEntriesUsing(function (array $entries): array {
    $entries[] = TextEntry::make('properties.request_id')->label('Request ID');

    return $entries;
});
```

### Filters

```php
use Filament\Tables\Filters\SelectFilter;

ActivityDisplay::filtersUsing(function (array $filters): array {
    $filters[] = SelectFilter::make('tenant')
        ->options(fn (): array => Tenant::pluck('name', 'id')->all())
        ->query(fn ($query, array $data) => filled($data['value'] ?? null)
            ? $query->where('properties->tenant', $data['value'])
            : $query);

    return $filters;
});
```

### Review tabs

Tabs are keyed by their filter preset name, so you can drop specific ones:

```php
ActivityDisplay::tabsUsing(
    fn (array $tabs): array => collect($tabs)->except(['auth_anomalies'])->all(),
);
```

Adding a tab is normally better done through
`filament-logger.activity_filters.saved` in config, which builds the tab and its
query for you. Use this hook when the tab needs logic config cannot express.

### Dashboard widgets

```php
ActivityDisplay::widgetsUsing(function (array $widgets): array {
    $widgets[] = \App\Filament\Widgets\TenantActivityWidget::class;

    return $widgets;
});
```

Returning `[]` hides the dashboard, the same as setting
`filament-logger.dashboard.enabled` to `false`.

### Testing

`ActivityDisplay::flush()` clears every registered hook. Call it in `tearDown`
so hooks registered by one test do not leak into the next.

## Alert rules

`MrAdder\FilamentLogger\Support\ActivityAlertRules`

Config remains the primary way to define alert rules. This registry exists for
rules that a config file cannot express — built from the database, or shipped by
another package.

```php
ActivityAlertRules::register('contract_signed', [
    'enabled' => true,
    'channels' => ['webhook'],
    'events' => ['Contract Signed'],
    'title' => 'Contract signed for :subject',
]);

ActivityAlertRules::registerMany([
    'plan_downgrade' => ['enabled' => true, 'channels' => ['mail']],
    'refund_issued' => ['enabled' => true, 'channels' => ['slack']],
]);
```

Registered rules are merged **over** config rules, so registering under an
existing key replaces that rule.

For full control, `resolveUsing()` runs last and receives the merged set, which
means it can remove rules as well as add them:

```php
ActivityAlertRules::resolveUsing(function (array $rules): array {
    return app()->environment('production')
        ? $rules
        : [];   // no alerts outside production
});
```

Registered rules support everything config rules do, including digests,
thresholds, cooldowns, and message templates. See the
[configuration guide](/configuration) for the full rule shape.

`ActivityAlertRules::flush()` clears registered rules and the resolver, for
tests.

## Activity descriptions

The hooks above change how activity is *displayed*. To change the description
that is **written** to the log, use `FilamentLogger::describeUsing()` instead —
see [Activity Descriptions](/configuration#activity-descriptions). The
distinction matters: `describeUsing` affects stored data and only applies to
activity recorded after it is registered, while `ActivityDisplay` affects
presentation and applies to every record, including historical ones.
