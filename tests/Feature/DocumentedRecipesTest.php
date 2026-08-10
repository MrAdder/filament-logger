<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use MrAdder\FilamentLogger\Facades\FilamentLogger;
use MrAdder\FilamentLogger\Resources\ActivityResource;
use MrAdder\FilamentLogger\Support\ActivityDisplay;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TenantActivity;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestTeam;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * Every example in docs/recipes.md is exercised here, so a recipe cannot
 * silently stop working. When a recipe changes, change the matching test.
 */

// ---------------------------------------------------------------- multi panel

it('can attach the same activity resource to more than one panel', function () {
    // The exact line the recipe tells you to add to each PanelProvider.
    $admin = Panel::make()->id('admin')->path('admin')
        ->resources([config('filament-logger.activity_resource')]);

    $partner = Panel::make()->id('partner')->path('partner')
        ->resources([config('filament-logger.activity_resource')]);

    expect($admin->getResources())->toContain(ActivityResource::class)
        ->and($partner->getResources())->toContain(ActivityResource::class);
});

it('resolves the configured resource to a concrete class', function () {
    // Recipes reference the resource through config rather than by class name,
    // because the package swaps the Filament 3 and 4+ implementations.
    $configured = config('filament-logger.activity_resource');

    expect(class_exists($configured))->toBeTrue()
        ->and(is_a($configured, ActivityResource::class, true))->toBeTrue();
});

it('lets a policy grant activity access per panel', function () {
    config()->set('filament-logger.authorization.strict', true);
    Gate::policy(ActivityResource::getModel(), PanelAwareActivityPolicy::class);

    Filament::setCurrentPanel(Panel::make()->id('admin')->path('admin'));
    expect(ActivityResource::canViewAny())->toBeTrue();

    Filament::setCurrentPanel(Panel::make()->id('partner')->path('partner'));
    expect(ActivityResource::canViewAny())->toBeFalse();
});

it('lets display hooks vary the resource per panel', function () {
    ActivityDisplay::tableColumnsUsing(function (array $columns): array {
        return Filament::getCurrentPanel()?->getId() === 'partner'
            ? array_values(array_filter($columns, fn ($column): bool => $column->getName() !== 'causer.name'))
            : $columns;
    });

    $names = fn (): array => array_map(
        fn ($column): string => $column->getName(),
        (new ReflectionMethod(ActivityResource::class, 'getTableColumns'))->invoke(null),
    );

    Filament::setCurrentPanel(Panel::make()->id('admin')->path('admin'));
    expect($names())->toContain('causer.name');

    Filament::setCurrentPanel(Panel::make()->id('partner')->path('partner'));
    expect($names())->not->toContain('causer.name');
});

// ------------------------------------------------------------------- tenancy

it('uses a custom activity model when one is configured', function () {
    config()->set('activitylog.activity_model', TenantActivity::class);

    expect(ActivityResource::getModel())->toBe(TenantActivity::class);
});

it('records activity against a tenant column on a custom model', function () {
    Schema::table('activity_log', function (Blueprint $table): void {
        $table->unsignedBigInteger('team_id')->nullable();
    });

    Schema::create('teams', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('activitylog.activity_model', TenantActivity::class);

    $team = TestTeam::create(['name' => 'Acme']);
    $record = TestRecord::create(['name' => 'Invoice']);

    FilamentLogger::log(
        event: 'Updated',
        description: 'Invoice Updated',
        options: ['logName' => 'Resource', 'subject' => $record, 'anonymous' => true],
    );

    $activity = TenantActivity::latest('id')->first();
    $activity->team_id = $team->getKey();
    $activity->save();

    expect($activity)->toBeInstanceOf(TenantActivity::class)
        ->and($activity->team)->not->toBeNull()
        ->and($activity->team->name)->toBe('Acme')
        ->and(TenantActivity::where('team_id', $team->getKey())->count())->toBe(1);
});

it('honours the tenant scoping switch', function () {
    config()->set('filament-logger.scoped_to_tenant', true);
    expect(ActivityResource::isScopedToTenant())->toBeTrue();

    config()->set('filament-logger.scoped_to_tenant', false);
    expect(ActivityResource::isScopedToTenant())->toBeFalse();
});

// ------------------------------------------------------------- custom events

it('records a custom domain event with risk, tags and a diff', function () {
    $record = TestRecord::create(['name' => 'Order 4021']);

    FilamentLogger::log(
        event: 'Refund Issued',
        description: 'Refund issued for order 4021',
        options: [
            'logName' => 'Billing',
            'subject' => $record,
            'anonymous' => true,
            'risk' => 'medium',
            'tags' => ['billing', 'refund'],
            'properties' => [
                'old' => ['status' => 'paid'],
                'attributes' => ['status' => 'refunded'],
                'amount' => 4200,
            ],
        ],
    );

    $activity = ActivityModel::latest('id')->first();
    $properties = $activity->properties->toArray();

    expect($activity->log_name)->toBe('Billing')
        ->and($activity->event)->toBe('Refund Issued')
        ->and($activity->subject_id)->toBe($record->getKey())
        ->and($properties['risk'])->toBe('medium')
        ->and($properties['tags'])->toBe(['billing', 'refund'])
        ->and($properties['old']['status'])->toBe('paid')
        ->and($properties['attributes']['status'])->toBe('refunded');
});

it('redacts sensitive keys inside custom event properties', function () {
    FilamentLogger::log(
        event: 'Api Token Rotated',
        description: 'Rotated integration token',
        options: [
            'logName' => 'Security',
            'anonymous' => true,
            'properties' => ['api_token' => 'super-secret-value', 'integration' => 'stripe'],
        ],
    );

    $properties = ActivityModel::latest('id')->first()->properties->toArray();

    expect($properties['api_token'])->toBe('[REDACTED]')
        ->and($properties['integration'])->toBe('stripe');
});

// -------------------------------------------------------------------- alerts

it('runs a custom event through a matching alert rule', function () {
    config()->set([
        'filament-logger.alerts.enabled' => true,
        'filament-logger.alerts.cache_store' => 'array',
        'filament-logger.alerts.webhook.url' => 'https://example.test/hooks/billing',
        'filament-logger.alerts.rules' => [
            'large_refund' => [
                'enabled' => true,
                'channels' => ['webhook'],
                'log_names' => ['Billing'],
                'events' => ['Refund Issued'],
                'title' => 'Refund issued on :log_name',
                'message' => ':description (risk :risk)',
            ],
        ],
    ]);

    Http::fake();

    FilamentLogger::log(
        event: 'Refund Issued',
        description: 'Refund issued for order 4021',
        options: ['logName' => 'Billing', 'anonymous' => true, 'risk' => 'medium'],
    );

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.test/hooks/billing'
        && $request['title'] === 'Refund issued on Billing'
        && $request['message'] === 'Refund issued for order 4021 (risk medium)');
});

class PanelAwareActivityPolicy
{
    public function viewAny(?object $user = null): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin';
    }

    public function view(?object $user = null, mixed $record = null): bool
    {
        return $this->viewAny($user);
    }
}
