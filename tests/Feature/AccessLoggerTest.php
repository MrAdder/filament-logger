<?php

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use MrAdder\FilamentLogger\Loggers\AccessLogger;
use MrAdder\FilamentLogger\Tests\Fixtures\Events\RecoveryCodeReplaced;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use Spatie\Activitylog\Models\Activity;

it('logs the expanded set of auth events with sanitized metadata', function () {
    $user = TestUser::query()->create([
        'name' => 'Taylor',
        'email' => 'taylor@example.com',
    ]);

    $request = Request::create('/login', 'POST', [
        'email' => 'taylor@example.com',
        'password' => 'super-secret',
    ]);
    $request->server->set('REMOTE_ADDR', '192.168.1.44');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Test Browser)');
    $this->app->instance('request', $request);

    $logger = new AccessLogger;

    $logger->handle(new Login('web', $user, false));
    $logger->handle(new Failed('web', $user, [
        'email' => 'taylor@example.com',
        'password' => 'super-secret',
    ]));
    $logger->handle(new Lockout($request));
    $logger->handle(new Logout('web', $user));
    $logger->handle(new PasswordReset($user));
    $logger->handle(new RecoveryCodeReplaced($user));

    expect(Activity::query()->orderBy('id')->pluck('event')->all())
        ->toBe([
            'Login',
            'Failed Login',
            'Lockout',
            'Logout',
            'Password Reset',
            'Two Factor Recovery',
        ]);

    $failedLogin = Activity::query()->where('event', 'Failed Login')->firstOrFail();
    $failedProperties = $failedLogin->properties->toArray();

    expect($failedLogin->causer_id)->toBe($user->getKey())
        ->and(data_get($failedProperties, 'identifiers.email'))->toBe('taylor@example.com')
        ->and(data_get($failedProperties, 'ip'))->toBe('192.168.1.0')
        ->and(data_get($failedProperties, 'user_agent'))->toBe('Mozilla/5.0 (Test Browser)');

    $recovery = Activity::query()->where('event', 'Two Factor Recovery')->firstOrFail();

    expect(data_get($recovery->properties->toArray(), 'used_recovery_code'))->toBeTrue();
});

it('filters guard-based access events when an allow-list is configured', function () {
    config()->set('filament-logger.access.guards', ['web']);

    $user = TestUser::query()->create([
        'name' => 'Taylor',
        'email' => 'taylor@example.com',
    ]);

    $request = Request::create('/login', 'POST', [
        'email' => 'taylor@example.com',
        'password' => 'super-secret',
    ]);
    $request->server->set('REMOTE_ADDR', '192.168.1.44');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Test Browser)');
    $this->app->instance('request', $request);

    $logger = new AccessLogger;

    $logger->handle(new Login('customer', $user, false));

    expect(Activity::query()->count())->toBe(0);

    $logger->handle(new Login('web', $user, false));

    expect(Activity::query()->count())->toBe(1)
        ->and(Activity::query()->firstOrFail()->event)->toBe('Login');
});
