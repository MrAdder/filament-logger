<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use MrAdder\FilamentLogger\Support\ActivityExportNotifier;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;

function exportPath(mixed $ownerId, string $file = 'activity-export.csv'): string
{
    return "filament-logger/exports/{$ownerId}/{$file}";
}

function storeExportFor(mixed $ownerId, string $contents = "id,description\n1,Deleted"): string
{
    $path = exportPath($ownerId);

    Storage::disk('exports')->put($path, $contents);

    return $path;
}

function signedDownloadUrl(mixed $ownerId, ?string $path = null): string
{
    return app(ActivityExportNotifier::class)->downloadUrl($ownerId, $path ?? exportPath($ownerId));
}

beforeEach(function () {
    Storage::fake('exports');

    config()->set([
        'filament-logger.exports.enabled' => true,
        'filament-logger.exports.ability' => null,
        'filament-logger.exports.queue.disk' => 'exports',
    ]);
});

it('serves the file to the user it was generated for', function () {
    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);
    storeExportFor($user->getKey());

    $this->actingAs($user)
        ->get(signedDownloadUrl($user->getKey()))
        ->assertOk()
        ->assertDownload('activity-export.csv');
});

it('rejects an unsigned url', function () {
    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);
    $path = storeExportFor($user->getKey());

    $unsigned = route('filament-logger.exports.download', [
        'owner' => $user->getKey(),
        'path' => base64_encode($path),
    ]);

    $this->actingAs($user)->get($unsigned)->assertForbidden();
});

it('rejects a guest even with a valid signature', function () {
    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);
    storeExportFor($user->getKey());

    $this->get(signedDownloadUrl($user->getKey()))->assertForbidden();
});

it('rejects a signed link forwarded to another user', function () {
    $owner = TestUser::create(['name' => 'Owner', 'email' => 'owner@example.test']);
    $other = TestUser::create(['name' => 'Other', 'email' => 'other@example.test']);

    storeExportFor($owner->getKey());

    // The signature is valid, but it is not this user's export.
    $this->actingAs($other)
        ->get(signedDownloadUrl($owner->getKey()))
        ->assertForbidden();
});

it('rejects a user without the export ability', function () {
    config()->set('filament-logger.exports.ability', 'exportActivity');
    Gate::define('exportActivity', fn ($user = null): bool => false);

    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);
    storeExportFor($user->getKey());

    $this->actingAs($user)
        ->get(signedDownloadUrl($user->getKey()))
        ->assertForbidden();
});

it('rejects a path that escapes the owner directory', function () {
    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    Storage::disk('exports')->put('filament-logger/exports/999/secret.csv', 'other user data');

    $url = signedDownloadUrl($user->getKey(), 'filament-logger/exports/999/secret.csv');

    $this->actingAs($user)->get($url)->assertForbidden();
});

it('rejects a traversal sequence', function () {
    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    $url = signedDownloadUrl($user->getKey(), 'filament-logger/exports/'.$user->getKey().'/../../../.env');

    $this->actingAs($user)->get($url)->assertForbidden();
});

it('returns 404 for a signed link to a file that no longer exists', function () {
    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    $this->actingAs($user)
        ->get(signedDownloadUrl($user->getKey()))
        ->assertNotFound();
});

it('returns 404 when exports are disabled', function () {
    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);
    storeExportFor($user->getKey());

    $url = signedDownloadUrl($user->getKey());

    config()->set('filament-logger.exports.enabled', false);

    $this->actingAs($user)->get($url)->assertNotFound();
});

it('does not build a download url when routes are disabled', function () {
    config()->set('filament-logger.exports.queue.routes', false);

    expect(app(ActivityExportNotifier::class)->downloadUrl(1, exportPath(1)))->toBeNull();
});
