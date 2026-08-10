<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Support\ActivityAlertRules;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;

const REGISTERED_WEBHOOK = 'https://example.test/hooks/registered';

function baseAlertConfig(array $rules = []): void
{
    config()->set([
        'filament-logger.alerts.enabled' => true,
        'filament-logger.alerts.cache_store' => 'array',
        'filament-logger.alerts.webhook.url' => REGISTERED_WEBHOOK,
        'filament-logger.alerts.rules' => $rules,
    ]);
}

function logDeleted(string $description = 'Record deleted'): void
{
    $record = TestRecord::create(['name' => 'Doomed']);

    app(FilamentLogger::class)->log(
        event: 'Deleted',
        description: $description,
        options: ['logName' => 'Resource', 'subject' => $record, 'anonymous' => true],
    );
}

afterEach(function () {
    ActivityAlertRules::flush();
    Carbon::setTestNow();
    Cache::store('array')->flush();
});

it('returns the config rules when nothing is registered', function () {
    baseAlertConfig(['from_config' => ['enabled' => true]]);

    expect(ActivityAlertRules::all())->toHaveKey('from_config');
});

it('dispatches an alert for a programmatically registered rule', function () {
    baseAlertConfig();

    ActivityAlertRules::register('registered_destructive', [
        'enabled' => true,
        'channels' => ['webhook'],
        'events' => ['Deleted'],
        'title' => 'Registered rule fired',
    ]);

    Http::fake();

    logDeleted();

    Http::assertSent(fn ($request): bool => $request->url() === REGISTERED_WEBHOOK
        && $request['title'] === 'Registered rule fired');
});

it('registers several rules at once', function () {
    baseAlertConfig();

    ActivityAlertRules::registerMany([
        'first' => ['enabled' => true, 'events' => ['Deleted']],
        'second' => ['enabled' => false],
    ]);

    expect(ActivityAlertRules::all())
        ->toHaveKey('first')
        ->toHaveKey('second');
});

it('overrides a config rule when registered under the same key', function () {
    baseAlertConfig([
        'destructive' => ['enabled' => true, 'channels' => ['webhook'], 'events' => ['Deleted'], 'title' => 'From config'],
    ]);

    ActivityAlertRules::register('destructive', [
        'enabled' => true,
        'channels' => ['webhook'],
        'events' => ['Deleted'],
        'title' => 'From code',
    ]);

    Http::fake();

    logDeleted();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['title'] === 'From code');
});

it('lets a resolver remove rules entirely', function () {
    baseAlertConfig([
        'destructive' => ['enabled' => true, 'channels' => ['webhook'], 'events' => ['Deleted']],
    ]);

    ActivityAlertRules::resolveUsing(fn (array $rules): array => []);

    Http::fake();

    logDeleted();

    Http::assertNothingSent();
});

it('runs the resolver after registered rules are merged', function () {
    baseAlertConfig(['from_config' => ['enabled' => true]]);

    ActivityAlertRules::register('from_code', ['enabled' => true]);
    ActivityAlertRules::resolveUsing(fn (array $rules): array => array_keys($rules) === ['from_config', 'from_code']
        ? ['merged' => ['enabled' => true]]
        : $rules);

    expect(ActivityAlertRules::all())->toBe(['merged' => ['enabled' => true]]);
});

it('ignores a resolver that does not return an array', function () {
    baseAlertConfig(['from_config' => ['enabled' => true]]);

    ActivityAlertRules::resolveUsing(fn (array $rules): mixed => 'nonsense');

    expect(ActivityAlertRules::all())->toHaveKey('from_config');
});

it('supports a registered digest rule', function () {
    Carbon::setTestNow('2026-08-10 10:00:00');
    baseAlertConfig();

    ActivityAlertRules::register('registered_digest', [
        'enabled' => true,
        'channels' => ['webhook'],
        'events' => ['Deleted'],
        'digest' => true,
        'digest_minutes' => 30,
    ]);

    Http::fake();

    logDeleted('First');
    logDeleted('Second');

    Http::assertNothingSent();

    Carbon::setTestNow('2026-08-10 10:31:00');

    $this->artisan('filament-logger:send-alert-digests')->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request['count'] === 2);
});

it('restores config-only rules after flush', function () {
    baseAlertConfig(['from_config' => ['enabled' => true]]);

    ActivityAlertRules::register('from_code', ['enabled' => true]);
    ActivityAlertRules::flush();

    expect(ActivityAlertRules::all())->toBe(['from_config' => ['enabled' => true]]);
});
