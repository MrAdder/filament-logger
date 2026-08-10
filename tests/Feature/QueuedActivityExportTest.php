<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use MrAdder\FilamentLogger\Jobs\GenerateActivityExport;
use MrAdder\FilamentLogger\Notifications\ActivityExportFailedNotification;
use MrAdder\FilamentLogger\Notifications\ActivityExportReadyNotification;
use MrAdder\FilamentLogger\Resources\ActivityResource;
use MrAdder\FilamentLogger\Support\ActivityExporter;
use MrAdder\FilamentLogger\Support\ActivityExportNotifier;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity as ActivityModel;
use Symfony\Component\HttpKernel\Exception\HttpException;

function exportsPageClass(): string
{
    return (new ReflectionMethod(ActivityResource::class, 'getListActivitiesPage'))->invoke(null);
}

/**
 * A list page with its table booted, so getTableQueryForExport() works.
 *
 * Livewire normally calls bootedInteractsWithTable() during the request
 * lifecycle; doing it directly keeps these tests independent of the Livewire
 * test harness while still exercising the real query path.
 */
function bootedPage(): object
{
    $class = exportsPageClass();

    $page = new $class;
    $page->bootedInteractsWithTable();

    return $page;
}

function seedActivities(int $count): void
{
    foreach (range(1, $count) as $index) {
        ActivityModel::create([
            'log_name' => 'Resource',
            'description' => "Activity {$index}",
            'event' => 'Updated',
        ]);
    }
}

function enableQueuedExports(array $overrides = []): void
{
    config()->set(array_merge([
        'filament-logger.exports.enabled' => true,
        'filament-logger.exports.ability' => null,
        'filament-logger.exports.queue.enabled' => true,
        'filament-logger.exports.queue.threshold' => 3,
        'filament-logger.exports.queue.disk' => 'exports',
        'filament-logger.exports.queue.notify' => 'mail',
    ], $overrides));
}

beforeEach(function () {
    Storage::fake('exports');
});

it('streams the export directly when it is below the threshold', function () {
    enableQueuedExports();
    seedActivities(2);

    Bus::fake();

    $response = bootedPage()->exportCsv();

    expect($response)->not->toBeNull();
    Bus::assertNotDispatched(GenerateActivityExport::class);
});

it('queues the export when it is above the threshold', function () {
    enableQueuedExports();
    seedActivities(5);

    Bus::fake();

    // Nothing to stream back: the file arrives via notification.
    expect(bootedPage()->exportCsv())->toBeNull();

    Bus::assertDispatched(GenerateActivityExport::class, fn (GenerateActivityExport $job): bool => $job->format === 'csv');
});

it('always queues when the threshold is zero', function () {
    enableQueuedExports(['filament-logger.exports.queue.threshold' => 0]);
    seedActivities(1);

    Bus::fake();

    expect(bootedPage()->exportJson())->toBeNull();

    Bus::assertDispatched(GenerateActivityExport::class, fn (GenerateActivityExport $job): bool => $job->format === 'json');
});

it('never queues while the feature is disabled', function () {
    enableQueuedExports(['filament-logger.exports.queue.enabled' => false]);
    seedActivities(50);

    Bus::fake();

    expect(bootedPage()->exportCsv())->not->toBeNull();

    Bus::assertNotDispatched(GenerateActivityExport::class);
});

it('writes the generated file to the configured disk', function () {
    enableQueuedExports();
    seedActivities(4);

    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    Notification::fake();

    (new GenerateActivityExport(
        format: 'csv',
        criteria: [],
        columns: ['id', 'description'],
        metadata: [],
        userId: $user->getKey(),
        userClass: $user::class,
    ))->handle(app(ActivityExporter::class), app(ActivityExportNotifier::class));

    $files = Storage::disk('exports')->allFiles("filament-logger/exports/{$user->getKey()}");

    expect($files)->toHaveCount(1);

    $contents = Storage::disk('exports')->get($files[0]);

    expect($contents)->toContain('id,description')
        ->and($contents)->toContain('Activity 1')
        ->and(substr_count(trim($contents), "\n"))->toBe(4);
});

it('applies the serialized criteria when rebuilding the query', function () {
    enableQueuedExports();
    seedActivities(3);
    ActivityModel::create(['log_name' => 'Access', 'description' => 'Failed login', 'event' => 'Failed Login']);

    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    Notification::fake();

    (new GenerateActivityExport(
        format: 'csv',
        criteria: ['log_name' => 'Access'],
        columns: ['id', 'description'],
        userId: $user->getKey(),
        userClass: $user::class,
    ))->handle(app(ActivityExporter::class), app(ActivityExportNotifier::class));

    $files = Storage::disk('exports')->allFiles("filament-logger/exports/{$user->getKey()}");
    $contents = Storage::disk('exports')->get($files[0]);

    expect($contents)->toContain('Failed login')
        ->and($contents)->not->toContain('Activity 1');
});

it('notifies the requesting user by mail when the export is ready', function () {
    enableQueuedExports();
    seedActivities(2);

    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    Notification::fake();

    (new GenerateActivityExport(
        format: 'csv',
        userId: $user->getKey(),
        userClass: $user::class,
    ))->handle(app(ActivityExporter::class), app(ActivityExportNotifier::class));

    Notification::assertSentTo($user, ActivityExportReadyNotification::class);
});

it('scopes generated files to the requesting user', function () {
    enableQueuedExports();
    seedActivities(1);

    $first = TestUser::create(['name' => 'One', 'email' => 'one@example.test']);
    $second = TestUser::create(['name' => 'Two', 'email' => 'two@example.test']);

    Notification::fake();

    foreach ([$first, $second] as $user) {
        (new GenerateActivityExport(
            format: 'csv',
            userId: $user->getKey(),
            userClass: $user::class,
        ))->handle(app(ActivityExporter::class), app(ActivityExportNotifier::class));
    }

    expect(Storage::disk('exports')->allFiles("filament-logger/exports/{$first->getKey()}"))->toHaveCount(1)
        ->and(Storage::disk('exports')->allFiles("filament-logger/exports/{$second->getKey()}"))->toHaveCount(1);
});

it('prunes generated exports past their retention window', function () {
    enableQueuedExports();

    Storage::disk('exports')->put('filament-logger/exports/1/old.csv', 'a,b');
    Storage::disk('exports')->put('filament-logger/exports/1/new.csv', 'a,b');

    // Age one file beyond the retention window.
    $path = Storage::disk('exports')->path('filament-logger/exports/1/old.csv');
    touch($path, now()->subDays(30)->getTimestamp());

    $this->artisan('filament-logger:prune-exports', ['--days' => 7])
        ->assertSuccessful();

    expect(Storage::disk('exports')->exists('filament-logger/exports/1/old.csv'))->toBeFalse()
        ->and(Storage::disk('exports')->exists('filament-logger/exports/1/new.csv'))->toBeTrue();
});

it('reports without deleting on a dry run', function () {
    enableQueuedExports();

    Storage::disk('exports')->put('filament-logger/exports/1/old.csv', 'a,b');
    touch(Storage::disk('exports')->path('filament-logger/exports/1/old.csv'), now()->subDays(30)->getTimestamp());

    $this->artisan('filament-logger:prune-exports', ['--days' => 7, '--dry-run' => true])
        ->expectsOutputToContain('Would delete 1 export file')
        ->assertSuccessful();

    expect(Storage::disk('exports')->exists('filament-logger/exports/1/old.csv'))->toBeTrue();
});

it('ships mail as the packaged default notify channel', function () {
    $packaged = require dirname(__DIR__, 2).'/config/filament-logger.php';

    expect($packaged['exports']['queue']['notify'])->toBe('mail');
});

it('reports a failed export back to the user by mail', function () {
    enableQueuedExports();

    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    Notification::fake();

    (new GenerateActivityExport(
        format: 'csv',
        userId: $user->getKey(),
        userClass: $user::class,
    ))->failed(new RuntimeException('disk unavailable'));

    Notification::assertSentTo($user, ActivityExportFailedNotification::class);
});

it('does not send the mail failure notification on the database channel', function () {
    enableQueuedExports(['filament-logger.exports.queue.notify' => 'database']);

    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    Notification::fake();

    (new GenerateActivityExport(
        format: 'csv',
        userId: $user->getKey(),
        userClass: $user::class,
    ))->failed(new RuntimeException('disk unavailable'));

    Notification::assertNotSentTo($user, ActivityExportFailedNotification::class);
});

it('stays silent when notifications are disabled', function () {
    enableQueuedExports(['filament-logger.exports.queue.notify' => null]);

    $user = TestUser::create(['name' => 'Auditor', 'email' => 'auditor@example.test']);

    Notification::fake();

    (new GenerateActivityExport(
        format: 'csv',
        userId: $user->getKey(),
        userClass: $user::class,
    ))->failed(new RuntimeException('disk unavailable'));

    Notification::assertNothingSent();
});

it('refuses a direct export call without the export ability', function () {
    enableQueuedExports(['filament-logger.exports.ability' => 'exportActivity']);
    Gate::define('exportActivity', fn ($user = null): bool => false);

    expect(fn () => bootedPage()->exportCsv())
        ->toThrow(HttpException::class);
});
