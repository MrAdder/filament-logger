<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MrAdder\FilamentLogger\FilamentLogger;
use MrAdder\FilamentLogger\Support\ActivityAlertDigest;
use MrAdder\FilamentLogger\Support\ActivityAlertDispatcher;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;

const DIGEST_WEBHOOK = 'https://example.test/hooks/digest';

function configureDigestRule(array $overrides = []): void
{
    config()->set([
        'filament-logger.alerts.enabled' => true,
        'filament-logger.alerts.cache_store' => 'array',
        'filament-logger.alerts.webhook.url' => DIGEST_WEBHOOK,
        'filament-logger.alerts.rules' => [
            'destructive_digest' => array_merge([
                'enabled' => true,
                'channels' => ['webhook'],
                'events' => ['Deleted'],
                'digest' => true,
                'digest_minutes' => 30,
            ], $overrides),
        ],
    ]);
}

function logDeletion(string $description = 'Record deleted'): void
{
    $record = TestRecord::create(['name' => 'Doomed']);

    app(FilamentLogger::class)->log(
        event: 'Deleted',
        description: $description,
        options: ['logName' => 'Resource', 'subject' => $record, 'anonymous' => true],
    );
}

afterEach(function () {
    Carbon::setTestNow();
    Cache::store('array')->flush();
});

it('buffers matching activity instead of alerting immediately', function () {
    Carbon::setTestNow('2026-08-10 10:00:00');
    configureDigestRule();
    Http::fake();

    logDeletion('First');
    logDeletion('Second');
    logDeletion('Third');

    Http::assertNothingSent();

    expect(app(ActivityAlertDigest::class)->pendingCount())->toBe(3);
});

it('releases one alert with the batched count when the window closes', function () {
    Carbon::setTestNow('2026-08-10 10:00:00');
    configureDigestRule();
    Http::fake();

    logDeletion('First');
    logDeletion('Second');

    Carbon::setTestNow('2026-08-10 10:31:00');

    $sent = app(ActivityAlertDispatcher::class)->flushDigests();

    expect($sent)->toBe(1);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['count'] === 2
        && str_contains($request['message'], '2 matching activities'));
});

it('does not release a digest before its window closes', function () {
    Carbon::setTestNow('2026-08-10 10:00:00');
    configureDigestRule();
    Http::fake();

    logDeletion();

    Carbon::setTestNow('2026-08-10 10:10:00');

    expect(app(ActivityAlertDispatcher::class)->flushDigests())->toBe(0);

    Http::assertNothingSent();
});

it('force-releases a pending digest on demand', function () {
    Carbon::setTestNow('2026-08-10 10:00:00');
    configureDigestRule();
    Http::fake();

    logDeletion();

    expect(app(ActivityAlertDispatcher::class)->flushDigests(force: true))->toBe(1);

    Http::assertSentCount(1);
});

it('releases opportunistically when new activity arrives after the window', function () {
    Carbon::setTestNow('2026-08-10 10:00:00');
    configureDigestRule();
    Http::fake();

    logDeletion('First');

    Carbon::setTestNow('2026-08-10 10:45:00');

    // No scheduler involved: the next matching activity closes the old window.
    logDeletion('Second');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['count'] === 1);
});

it('starts a fresh window after a digest is released', function () {
    Carbon::setTestNow('2026-08-10 10:00:00');
    configureDigestRule();
    Http::fake();

    logDeletion('First');

    Carbon::setTestNow('2026-08-10 10:31:00');
    app(ActivityAlertDispatcher::class)->flushDigests();

    logDeletion('Second');

    expect(app(ActivityAlertDigest::class)->pendingCount())->toBe(1);
});

it('supports a digest specific title', function () {
    Carbon::setTestNow('2026-08-10 10:00:00');
    configureDigestRule(['digest_title' => ':count deletions this hour']);
    Http::fake();

    logDeletion();
    logDeletion();

    Carbon::setTestNow('2026-08-10 10:31:00');
    app(ActivityAlertDispatcher::class)->flushDigests();

    Http::assertSent(fn ($request): bool => $request['title'] === '2 deletions this hour');
});

it('leaves non-digest rules alerting immediately', function () {
    configureDigestRule(['digest' => false]);
    Http::fake();

    logDeletion();

    Http::assertSentCount(1);
    expect(app(ActivityAlertDigest::class)->pendingCount())->toBe(0);
});

it('reports nothing to send when no digest rules are configured', function () {
    config()->set([
        'filament-logger.alerts.enabled' => true,
        'filament-logger.alerts.rules' => [],
    ]);

    $this->artisan('filament-logger:send-alert-digests')
        ->expectsOutputToContain('No alert rules are configured as digests')
        ->assertSuccessful();
});

it('releases due digests from the scheduled command', function () {
    Carbon::setTestNow('2026-08-10 10:00:00');
    configureDigestRule();
    Http::fake();

    logDeletion();

    Carbon::setTestNow('2026-08-10 10:31:00');

    $this->artisan('filament-logger:send-alert-digests')
        ->expectsOutputToContain('Sent 1 alert digest')
        ->assertSuccessful();

    Http::assertSentCount(1);
});
