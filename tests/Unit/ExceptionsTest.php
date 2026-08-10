<?php

use MrAdder\FilamentLogger\Exceptions\ActivityExportFailed;
use MrAdder\FilamentLogger\Exceptions\AlertWebhookFailed;
use MrAdder\FilamentLogger\Exceptions\FilamentLoggerException;

it('lets an application catch every package failure through one base type', function () {
    expect(ActivityExportFailed::streamUnavailable())->toBeInstanceOf(FilamentLoggerException::class)
        ->and(AlertWebhookFailed::status('https://example.test/hook', 500))->toBeInstanceOf(FilamentLoggerException::class)
        ->and(new FilamentLoggerException)->toBeInstanceOf(RuntimeException::class);
});

it('describes an export stream failure', function () {
    expect(ActivityExportFailed::streamUnavailable()->getMessage())
        ->toContain('temporary stream');
});

it('reports the webhook status without leaking the url', function () {
    // Slack and Discord webhook URLs carry a secret in the path, so only the
    // host belongs in an exception message that may be logged.
    $exception = AlertWebhookFailed::status('https://hooks.slack.test/services/T000/B000/SUPERSECRET', 503);

    expect($exception->getMessage())->toContain('hooks.slack.test')
        ->and($exception->getMessage())->toContain('503')
        ->and($exception->getMessage())->not->toContain('SUPERSECRET');
});

it('falls back when the url has no parseable host', function () {
    expect(AlertWebhookFailed::status('not-a-url', 500)->getMessage())
        ->toContain('the configured endpoint');
});
