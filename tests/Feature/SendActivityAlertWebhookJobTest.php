<?php

use Illuminate\Support\Facades\Http;
use MrAdder\FilamentLogger\Exceptions\AlertWebhookFailed;
use MrAdder\FilamentLogger\Jobs\SendActivityAlertWebhook;

const JOB_WEBHOOK = 'https://example.test/hooks/job';

it('posts the payload with any configured headers', function () {
    Http::fake([JOB_WEBHOOK => Http::response('', 200)]);

    (new SendActivityAlertWebhook(JOB_WEBHOOK, ['title' => 'Alert'], 5, ['X-Token' => 'abc']))->handle();

    Http::assertSent(fn ($request): bool => $request->url() === JOB_WEBHOOK
        && $request->hasHeader('X-Token', 'abc')
        && $request['title'] === 'Alert');
});

it('throws a dedicated exception on a rejected webhook', function () {
    Http::fake([JOB_WEBHOOK => Http::response('nope', 500)]);

    expect(fn () => (new SendActivityAlertWebhook(JOB_WEBHOOK, ['title' => 'Alert']))->handle())
        ->toThrow(AlertWebhookFailed::class);
});

it('succeeds quietly on any 2xx', function () {
    Http::fake([JOB_WEBHOOK => Http::response('', 204)]);

    (new SendActivityAlertWebhook(JOB_WEBHOOK, ['title' => 'Alert']))->handle();

    Http::assertSentCount(1);
});

it('retries a few times with a backoff', function () {
    $job = new SendActivityAlertWebhook(JOB_WEBHOOK, []);

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 60]);
});

it('carries its configured timeout', function () {
    expect((new SendActivityAlertWebhook(JOB_WEBHOOK, [], 30))->timeout)->toBe(30)
        ->and((new SendActivityAlertWebhook(JOB_WEBHOOK, []))->timeout)->toBe(5);
});
