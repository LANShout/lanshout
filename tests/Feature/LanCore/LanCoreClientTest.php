<?php

use App\Services\LanCore\Exceptions\LanCoreDisabledException;
use App\Services\LanCore\Exceptions\LanCoreRequestException;
use App\Services\LanCore\LanCoreClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'lancore.enabled' => true,
        'lancore.base_url' => 'https://lancore.test',
        'lancore.token' => 'test-integration-token',
        'lancore.timeout' => 5,
        'lancore.retries' => 0,
        'lancore.retry_delay' => 0,
    ]);
});

it('reports enabled status from config', function () {
    $client = new LanCoreClient;

    expect($client->isEnabled())->toBeTrue();

    config(['lancore.enabled' => false]);
    expect($client->isEnabled())->toBeFalse();
});

it('throws LanCoreDisabledException when disabled', function () {
    config(['lancore.enabled' => false]);

    $client = new LanCoreClient;
    $client->fetchUserByToken('some-token');
})->throws(LanCoreDisabledException::class);

it('fetches a user by token successfully', function () {
    Http::fake([
        'lancore.test/api/v1/auth/verify-token' => Http::response([
            'user' => [
                'id' => 42,
                'username' => 'mkohn',
                'display_name' => 'Matt Kohn',
                'email' => 'matt@example.com',
                'avatar_url' => 'https://lancore.test/avatars/42.jpg',
                'locale' => 'en',
            ],
        ], 200),
    ]);

    $client = new LanCoreClient;
    $user = $client->fetchUserByToken('valid-user-token');

    expect($user)->not->toBeNull()
        ->and($user->id)->toBe(42)
        ->and($user->username)->toBe('mkohn')
        ->and($user->email)->toBe('matt@example.com');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer valid-user-token')
            && str_contains($request->url(), '/api/v1/auth/verify-token');
    });
});

it('fetches a user by id successfully', function () {
    Http::fake([
        'lancore.test/api/v1/users/42' => Http::response([
            'user' => [
                'id' => 42,
                'username' => 'mkohn',
                'display_name' => 'Matt Kohn',
                'email' => 'matt@example.com',
                'avatar_url' => null,
                'locale' => 'de',
            ],
        ], 200),
    ]);

    $client = new LanCoreClient;
    $user = $client->fetchUserById(42);

    expect($user)->not->toBeNull()
        ->and($user->id)->toBe(42)
        ->and($user->locale)->toBe('de');
});

it('throws LanCoreRequestException on 401 response', function () {
    Http::fake([
        'lancore.test/api/v1/auth/verify-token' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $client = new LanCoreClient;
    $client->fetchUserByToken('invalid-token');
})->throws(LanCoreRequestException::class, 'LanCore rejected the integration token.');

it('throws LanCoreRequestException on 403 response', function () {
    Http::fake([
        'lancore.test/api/v1/auth/verify-token' => Http::response(['error' => 'Forbidden'], 403),
    ]);

    $client = new LanCoreClient;
    $client->fetchUserByToken('bad-token');
})->throws(LanCoreRequestException::class, 'LanCore rejected the integration token.');

it('throws LanCoreRequestException on 500 server error', function () {
    Http::fake([
        'lancore.test/api/v1/auth/verify-token' => Http::response('Internal Server Error', 500),
    ]);

    $client = new LanCoreClient;
    $client->fetchUserByToken('some-token');
})->throws(LanCoreRequestException::class, 'LanCore request failed with status 500.');

it('throws LanCoreRequestException when LanCore is unreachable', function () {
    Http::fake([
        'lancore.test/api/v1/auth/verify-token' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $client = new LanCoreClient;
    $client->fetchUserByToken('some-token');
})->throws(LanCoreRequestException::class, 'LanCore is unreachable.');

it('sends the integration token as a bearer header', function () {
    Http::fake([
        'lancore.test/api/v1/users/1' => Http::response([
            'user' => [
                'id' => 1,
                'username' => 'test',
                'display_name' => 'Test',
                'email' => 'test@example.com',
            ],
        ], 200),
    ]);

    $client = new LanCoreClient;
    $client->fetchUserById(1);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer test-integration-token');
    });
});
